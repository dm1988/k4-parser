<?php

namespace App\Services;

use App\Actions\BuildParserResult;
use App\DTOs\ParsedEventDTO;
use App\DTOs\ParserResultData;
use App\Enums\ParserEventType;
use App\Enums\ScheduleDocumentType;
use App\Exceptions\ParseSourceResolutionException;
use Illuminate\Http\UploadedFile;
use Throwable;

class JcaScheduleParsingService
{
    public function __construct(
        private readonly BuildParserResult $buildParserResult,
        private readonly ParserResultCache $parserResultCache,
        private readonly ScheduleFormatParser $scheduleFormatParser,
        private readonly TripInformationParser $tripInformationParser,
        private readonly ScheduleInputResolver $scheduleInputResolver,
        private readonly AirportEnrichmentService $airportEnrichmentService,
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
            'calendar_events' => $this->tripInformationParser->extractFlightsDto($text),
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
            'calendar_events' => $this->tripInformationParser->extractHotels($text),
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
            $source = $this->scheduleInputResolver->resolve($file, $text);
        } catch (Throwable $throwable) {
            throw ParseSourceResolutionException::fromThrowable($throwable, $file !== null);
        }

        $parsed = $this->scheduleFormatParser->parse(
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

        $filteredParsed = $this->airportEnrichmentService->enrich($filteredParsed);

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

        if (($result->parsed['calendar_events'] ?? []) !== []) {
            $this->parserResultCache->put($result);
        }

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
        // Place logic in enum
        if ($source === 'image') {
            return 'screenshot';
        }

        return ScheduleDocumentType::tryFrom((string) $documentType)?->parserType() ?? 'unknown';
    }
}
