<?php

namespace App\Services;

use App\Actions\BuildParserResult;
use App\DTOs\ParsedEventDTO;
use App\DTOs\ParserResultData;
use App\Enums\ParserEventType;
use App\Exceptions\ParseSourceResolutionException;
use Illuminate\Http\UploadedFile;
use Throwable;

class ScheduleParserService
{
    public function __construct(
        private readonly BuildParserResult $buildParserResult,
        private readonly ParserResultCache $parserResultCache,
        private readonly RosterDocumentParser $rosterDocumentParser,
        private readonly RosterParser $rosterParser,
        private readonly RosterSourceResolver $rosterSourceResolver,
    ) {}

    /**
     * @return array{
     *     parsed: array<string, mixed>,
     *     result: ParserResultData
     * }
     */
    public function parseFlight(string $text): array
    {
        $parsed = [
            'trip' => [],
            'calendar_events' => $this->rosterParser->extractFlightsDto($text),
        ];

        $result = $this->buildParserResult->handle(
            type: 'flight',
            source: 'text',
            documentType: null,
            parsed: $parsed,
        );

        $this->parserResultCache->put($result);

        return [
            'parsed' => $parsed,
            'result' => $result,
        ];
    }

    /**
     * @return array{
     *     parsed: array<string, mixed>,
     *     result: ParserResultData
     * }
     */
    public function parseHotel(string $text): array
    {
        $parsed = [
            'trip' => [],
            'calendar_events' => $this->rosterParser->extractHotels($text),
        ];

        $result = $this->buildParserResult->handle(
            type: 'hotel',
            source: 'text',
            documentType: null,
            parsed: $parsed,
        );

        $this->parserResultCache->put($result);

        return [
            'parsed' => $parsed,
            'result' => $result,
        ];
    }

    /**
     * @param  list<string>  $eventTypes
     * @return array{
     *     parsed: array<string, mixed>,
     *     result: ParserResultData,
     *     parser_type: string,
     *     page_count: ?int
     * }
     */
    public function parseRoster(?UploadedFile $file, ?string $text, array $eventTypes = []): array
    {
        try {
            $source = $this->rosterSourceResolver->resolve($file, $text);
        } catch (Throwable $throwable) {
            throw ParseSourceResolutionException::fromThrowable($throwable, $file !== null);
        }

        $parsed = $this->rosterDocumentParser->parse(
            $source['raw_text'],
            $source['document_type'] ?? null,
        );
        $filteredParsed = $parsed;

        if ($eventTypes !== []) {
            $filteredParsed['calendar_events'] = array_values(array_filter(
                $filteredParsed['calendar_events'] ?? [],
                fn (mixed $event): bool => in_array($this->eventType($event), $eventTypes, true),
            ));
        }

        $result = $this->buildParserResult->handle(
            type: 'roster',
            source: $source['source'],
            documentType: $source['document_type'] ?? null,
            parsed: $filteredParsed,
            filters: $eventTypes,
            file: $source['file'],
            mime: $source['mime'],
            meta: is_array($source['meta'] ?? null) ? $source['meta'] : [],
        );

        $this->parserResultCache->put($result);

        return [
            'parsed' => $parsed,
            'result' => $result,
            'parser_type' => $this->parserType($source['source'], $source['document_type'] ?? null),
            'page_count' => data_get($source, 'meta.page_count'),
        ];
    }

    private function eventType(mixed $event): string
    {
        $eventType = $event instanceof ParsedEventDTO
            ? ParserEventType::fromValue($event->type)
            : (is_array($event) ? ParserEventType::fromEvent($event) : ParserEventType::Unknown);

        if ($eventType->isFlightLike()) {
            return ParserEventType::Flight->value;
        }

        if ($event instanceof ParsedEventDTO) {
            return $event->type;
        }

        return is_array($event) ? (string) ($event['type'] ?? '') : '';
    }

    private function parserType(string $source, ?string $documentType): string
    {
        if ($source === 'image') {
            return 'screenshot';
        }

        return match ($documentType) {
            RosterSourceResolver::PDF_TYPE_TRIP_INFORMATION => 'trip_pdf',
            RosterSourceResolver::PDF_TYPE_PUBLISHED_ROSTER => 'roster_pdf',
            default => 'unknown',
        };
    }
}
