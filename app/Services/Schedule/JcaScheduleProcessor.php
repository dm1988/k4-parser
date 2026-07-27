<?php

namespace App\Services\Schedule;

use App\Actions\BuildScheduleResult;
use App\DTOs\ExtractedEventDTO;
use App\DTOs\ExtractedResultData;
use App\Enums\ScheduleDocumentType;
use App\Enums\ScheduleEventType;
use App\Exceptions\ExtractSourceResolutionException;
use App\Services\Infrastructure\EngineResultCache;
use App\Services\Schedule\Extractor\ScheduleFormatParser;
use App\Services\Schedule\Extractor\TripInformationParser;
use Illuminate\Http\UploadedFile;
use Throwable;

class JcaScheduleProcessor
{
    public function __construct(
        private readonly BuildScheduleResult $buildScheduleResult,
        private readonly EngineResultCache $engineResultCache,
        private readonly ScheduleFormatParser $scheduleFormatParser,
        private readonly TripInformationParser $tripInformationParser,
        private readonly ScheduleInputResolver $scheduleInputResolver,
        private readonly AirportEnrichmentService $airportEnrichmentService,
    ) {}

    /**
     * @return array{
     *     parsed: array<string, mixed>,
     *     result: ExtractedResultData
     * }
     */
    public function extractFlight(string $text): array
    {
        $parsed = [
            'trip' => [],
            'calendar_events' => $this->tripInformationParser->extractFlightsDto($text),
        ];

        $result = $this->buildScheduleResult->handle(
            type: 'flight',
            source: 'text',
            documentType: null,
            parsed: $parsed,
        );

        $this->engineResultCache->put($result);

        return [
            'parsed' => $parsed,
            'result' => $result,
        ];
    }

    /**
     * @return array{
     *     parsed: array<string, mixed>,
     *     result: ExtractedResultData
     * }
     */
    public function parseHotel(string $text): array
    {
        $parsed = [
            'trip' => [],
            'calendar_events' => $this->tripInformationParser->extractHotels($text),
        ];

        $result = $this->buildScheduleResult->handle(
            type: 'hotel',
            source: 'text',
            documentType: null,
            parsed: $parsed,
        );

        $this->engineResultCache->put($result);

        return [
            'parsed' => $parsed,
            'result' => $result,
        ];
    }

    /**
     * @param  list<string>  $eventTypes
     * @return array{
     *     parsed: array<string, mixed>,
     *     result: ExtractedResultData,
     *     parser_type: string,
     *     page_count: ?int
     * }
     */
    public function extractRoster(array|UploadedFile|null $file, ?string $text, array $eventTypes = []): array
    {
        $files = $file instanceof UploadedFile ? [$file] : (is_array($file) ? $file : []);
        $sources = $this->resolveSources($files, $text);
        $parsed = $this->parseSources($sources);
        $filteredParsed = $parsed;

        if ($eventTypes !== []) {
            $filteredParsed['calendar_events'] = array_values(array_filter(
                $filteredParsed['calendar_events'] ?? [],
                fn (mixed $event): bool => in_array($this->eventType($event), $eventTypes, true),
            ));
        }

        $filteredParsed = $this->airportEnrichmentService->enrich($filteredParsed);

        $result = $this->buildScheduleResult->handle(
            type: 'roster',
            source: $sources[0]['source'],
            documentType: $sources[0]['document_type'] ?? null,
            parsed: $filteredParsed,
            filters: $eventTypes,
            file: $sources[0]['file'],
            mime: $sources[0]['mime'],
            meta: $this->sourceMeta($sources),
        );

        if (($result->parsed['calendar_events'] ?? []) !== []) {
            $this->engineResultCache->put($result);
        }

        return [
            'parsed' => $parsed,
            'result' => $result,
            'parser_type' => $this->parserType($sources[0]['source'], $sources[0]['document_type'] ?? null),
            'page_count' => $this->pageCount($sources),
        ];
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array<string, mixed>>
     */
    private function resolveSources(array $files, ?string $text): array
    {
        try {
            if ($files === []) {
                return [$this->scheduleInputResolver->resolve(null, $text)];
            }

            return array_map(
                fn (UploadedFile $file): array => $this->scheduleInputResolver->resolve($file, null),
                $files,
            );
        } catch (Throwable $throwable) {
            throw ExtractSourceResolutionException::fromThrowable($throwable, $files !== []);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     * @return array<string, mixed>
     */
    private function parseSources(array $sources): array
    {
        $parsedResults = array_map(
            fn (array $source): array => $this->scheduleFormatParser->parse(
                (string) $source['raw_text'],
                $source['document_type'] ?? null,
            ),
            $sources,
        );

        $parsed = $parsedResults[0];
        $events = array_merge(...array_map(
            static fn (array $result): array => is_array($result['calendar_events'] ?? null)
                ? $result['calendar_events']
                : [],
            $parsedResults,
        ));

        $uniqueEvents = [];

        foreach ($events as $event) {
            $eventData = $event instanceof ExtractedEventDTO ? $event->toArray() : $event;
            $key = hash('sha256', serialize($eventData));
            $uniqueEvents[$key] = $event;
        }

        $events = array_values($uniqueEvents);
        usort($events, static function (mixed $first, mixed $second): int {
            $firstStart = $first instanceof ExtractedEventDTO ? $first->start : data_get($first, 'start');
            $secondStart = $second instanceof ExtractedEventDTO ? $second->start : data_get($second, 'start');

            return strcmp((string) $firstStart, (string) $secondStart);
        });

        $parsed['calendar_events'] = $events;

        return $parsed;
    }

    /** @param list<array<string, mixed>> $sources */
    private function sourceMeta(array $sources): array
    {
        $meta = is_array($sources[0]['meta'] ?? null) ? $sources[0]['meta'] : [];

        if (count($sources) > 1) {
            $meta['image_count'] = count($sources);
        }

        return $meta;
    }

    /** @param list<array<string, mixed>> $sources */
    private function pageCount(array $sources): ?int
    {
        $pageCounts = array_filter(array_map(
            static fn (array $source): ?int => data_get($source, 'meta.page_count'),
            $sources,
        ), static fn (?int $pageCount): bool => $pageCount !== null);

        return $pageCounts === [] ? null : array_sum($pageCounts);
    }

    private function eventType(mixed $event): string
    {
        $eventType = $event instanceof ExtractedEventDTO
            ? ScheduleEventType::fromValue($event->type)
            : (is_array($event) ? ScheduleEventType::fromEvent($event) : ScheduleEventType::Unknown);

        if ($eventType->isFlightLike()) {
            return ScheduleEventType::Flight->value;
        }

        if ($event instanceof ExtractedEventDTO) {
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
