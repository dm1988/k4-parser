<?php

namespace App\Http\Controllers;

use App\Actions\ExportFlightDutyCalendarEvent;
use App\DTOs\Flight;
use App\DTOs\ParsedEventDTO;
use App\Enums\MetadataKey;
use App\Enums\ParserEventType;
use App\Services\IcsCalendarService;
use App\Services\ParseRequestLogger;
use App\Services\RosterDocumentParser;
use App\Services\RosterParser;
use App\Services\RosterSourceResolver;
use App\View\Models\Parser\ParserPageViewModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ParserController extends Controller
{
    public function __construct(
        private readonly IcsCalendarService $icsCalendarService,
        private readonly ParseRequestLogger $parseRequestLogger,
        private readonly RosterDocumentParser $rosterDocumentParser,
        private readonly RosterParser $rosterParser,
        private readonly RosterSourceResolver $rosterSourceResolver,
    ) {}

    public function index()
    {
        return view('parse', [
            'viewModel' => ParserPageViewModel::fromCurrentSession(session()->getOldInput()),
        ]);
    }

    public function parseFlight(Request $request)
    {
        $this->authorizeScheduleParser($request);

        $text = $request->validate([
            'text' => ['required', 'string'],
        ])['text'];

        $startedAt = hrtime(true);
        $parseRequest = $this->parseRequestLogger->start($request->user()?->id, 'pasted_text', 'unknown');

        try {
            $parsed = [
                'trip' => [],
                'calendar_events' => $this->rosterParser->extractFlightsDto($text), // Keep as DTO array!
            ];
            $result = $this->buildResult(
                type: 'flight',
                source: 'text',
                documentType: null,
                parsed: $parsed,
            );

            $this->cacheResult($result);
            $this->parseRequestLogger->success($parseRequest, $startedAt, $parsed);
        } catch (Throwable $e) {
            $this->parseRequestLogger->error($parseRequest, $startedAt, $e);

            throw $e;
        }

        return back();
    }

    public function parseHotel(Request $request)
    {
        $this->authorizeScheduleParser($request);

        $text = $request->validate([
            'text' => ['required', 'string'],
        ])['text'];

        $startedAt = hrtime(true);
        $parseRequest = $this->parseRequestLogger->start($request->user()?->id, 'pasted_text', 'unknown');

        try {
            $parsed = [
                'trip' => [],
                'calendar_events' => $this->rosterParser->extractHotels($text),
            ];
            $result = $this->buildResult(
                type: 'hotel',
                source: 'text',
                documentType: null,
                parsed: $parsed,
            );

            $this->cacheResult($result);
            $this->parseRequestLogger->success($parseRequest, $startedAt, $parsed);
        } catch (Throwable $e) {
            $this->parseRequestLogger->error($parseRequest, $startedAt, $e);

            throw $e;
        }

        return back();
    }

    public function parseRoster(Request $request)
    {
        $this->authorizeScheduleParser($request);

        $data = $request->validate([
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,bmp,tif,tiff,webp', 'max:12288', 'required_without:text'],
            'text' => ['nullable', 'string', 'required_without:file'],
            'event_types' => ['nullable', 'array'],
            'event_types.*' => [Rule::in(ParserEventType::filterValues())],
        ]);

        $eventTypes = $data['event_types'] ?? [];
        $file = $request->file('file');
        $sourceType = $file === null
            ? 'pasted_text'
            : ($file->getMimeType() === 'application/pdf' ? 'pdf' : 'image');
        $startedAt = hrtime(true);
        $parseRequest = $this->parseRequestLogger->start(
            $request->user()?->id,
            $sourceType,
            $sourceType === 'image' ? 'screenshot' : 'unknown',
            $file,
        );

        try {
            $source = $this->rosterSourceResolver->resolve(
                $file,
                $data['text'] ?? null,
            );
        } catch (Throwable $e) {
            $this->parseRequestLogger->error($parseRequest, $startedAt, $e);
            $message = $request->hasFile('file')
                ? 'Source resolution failed: '
                : 'Roster text resolution failed: ';

            return back()->withInput()->withErrors(['file' => $message.$e->getMessage()]);
        }

        try {
            $text = $source['raw_text'];
            $parsed = $this->rosterDocumentParser->parse(
                $text,
                $source['document_type'] ?? null,
            );
            $detectedParsed = $parsed;

            if (! empty($eventTypes)) {
                $parsed['calendar_events'] = array_values(array_filter(
                    $parsed['calendar_events'] ?? [],
                    fn (mixed $event) => in_array($this->eventType($event), $eventTypes, true)
                ));
            }

            $result = $this->buildResult(
                type: 'roster',
                source: $source['source'],
                documentType: $source['document_type'] ?? null,
                parsed: $parsed,
                filters: $eventTypes,
                file: $source['file'],
                mime: $source['mime'],
                meta: is_array($source['meta'] ?? null) ? $source['meta'] : [],
            );

            $this->cacheResult($result);
            $this->parseRequestLogger->success(
                $parseRequest,
                $startedAt,
                $detectedParsed,
                $this->parserType($source['source'], $source['document_type'] ?? null),
                data_get($source, 'meta.page_count'),
            );
        } catch (Throwable $e) {
            $this->parseRequestLogger->error($parseRequest, $startedAt, $e);

            throw $e;
        }

        return back();
    }

    public function exportCalendar(Request $request)
    {
        $this->authorizeScheduleParser($request);

        $sessionResult = $this->resolveExportResult($request);

        if (! is_array($sessionResult) || ! isset($sessionResult['parsed']['calendar_events'])) {
            abort(404);
        }

        $eventTypes = $request->query('event_types', $sessionResult['filters'] ?? []);
        $events = $sessionResult['parsed']['calendar_events'];

        if ($eventTypes !== []) {
            $events = array_values(array_filter(
                $events,
                fn (mixed $event) => in_array($this->eventType($event), $eventTypes, true),
            ));
        }

        if (count($events) === 0) {
            abort(404);
        }

        $tripNumber = $sessionResult['parsed']['trip']['trip_number'] ?? null;
        $filename = 'crew-compass'.($tripNumber ? "-{$tripNumber}" : '').'.ics';

        return response($this->icsCalendarService->serialize($events, $sessionResult['parsed']['trip'] ?? []), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportCalendarEvent(Request $request, string $eventId)
    {
        $this->authorizeScheduleParser($request);

        $sessionResult = $this->resolveExportResult($request);

        if (! is_array($sessionResult) || ! isset($sessionResult['parsed']['calendar_events'])) {
            abort(404);
        }

        $event = $this->findEventByDownloadId($sessionResult['parsed']['calendar_events'], $eventId);

        if ($event === null) {
            abort(404);
        }

        $trip = $sessionResult['parsed']['trip'] ?? [];
        $tripNumber = $trip['trip_number'] ?? null;
        $slug = 'event-'.$eventId;
        $filename = 'crew-compass'.($tripNumber ? "-{$tripNumber}" : '').'-'.$slug.'.ics';

        return response($this->icsCalendarService->serialize([$event], $trip), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportFlightDutyCalendarEvent(
        Request $request,
        string $eventId,
        ExportFlightDutyCalendarEvent $exportFlightDutyCalendarEvent,
    ): Response {
        $this->authorizeScheduleParserDutyExport($request);

        $sessionResult = $this->resolveExportResult($request);

        if (! is_array($sessionResult) || ! isset($sessionResult['parsed']['calendar_events'])) {
            abort(404);
        }

        $event = $this->findEventByDownloadId($sessionResult['parsed']['calendar_events'], $eventId);

        if ($event === null) {
            abort(404);
        }

        $trip = $sessionResult['parsed']['trip'] ?? [];
        $ics = $exportFlightDutyCalendarEvent->handle($event, $trip);

        if ($ics === null) {
            abort(404);
        }

        $tripNumber = $trip['trip_number'] ?? null;
        $filename = 'crew-compass'.($tripNumber ? "-{$tripNumber}" : '').'-duty-'.$eventId.'.ics';

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function buildResult(
        string $type,
        string $source,
        ?string $documentType,
        array $parsed,
        array $filters = [],
        mixed $file = null,
        ?string $mime = null,
        array $meta = [],
    ): array {
        $parseKey = (string) Str::ulid();

        return [
            'type' => $type,
            'source' => $source,
            'document_type' => $documentType,
            'file' => $file,
            'mime' => $mime,
            'parsed' => $this->attachDownloadIds($parsed),
            'filters' => $filters,
            'meta' => $meta,
            'parse_key' => $parseKey,
        ];
    }

    private function attachDownloadIds(array $parsed): array
    {
        $events = [];

        foreach (($parsed['calendar_events'] ?? []) as $event) {
            $downloadId = (string) Str::ulid();

            // 1. If it's your concrete Flight DTO, use its internal mutation method
            if ($event instanceof Flight) {
                $events[] = $event->withDownloadId($downloadId);

                continue;
            }

            // 2. Future-proofing: Catch any other broad ParsedEventDTO types
            if ($event instanceof ParsedEventDTO) {
                $events[] = $event;

                continue;
            }

            // 3. Fallback for array matrices
            if (is_array($event)) {
                $event[MetadataKey::DownloadId->value] = $downloadId;
                $events[] = $event;
            }
        }

        $parsed['calendar_events'] = $events;

        return $parsed;
    }

    private function resolveExportResult(Request $request): mixed
    {
        $parseKey = $request->query('parse_key');

        if (is_string($parseKey) && $parseKey !== '') {
            return $this->resolveCachedResult($parseKey);
        }

        $latestParseKey = session('latest_parse_key');

        if (is_string($latestParseKey) && $latestParseKey !== '') {
            return $this->resolveCachedResult($latestParseKey);
        }

        return null;
    }

    private function findEventByDownloadId(array $events, string $eventId): mixed
    {
        foreach ($events as $event) {
            // Use abstract parent properties or ArrayAccess fallbacks smoothly
            if ($event instanceof ParsedEventDTO && $event->downloadId === $eventId) {
                return $event;
            }

            if (is_array($event) && ($event[MetadataKey::DownloadId->value] ?? null) === $eventId) {
                return $event;
            }
        }

        return null;
    }

    private function resolveCachedResult(string $parseKey): mixed
    {
        return Cache::get($this->cacheKey($parseKey))
            ?? Cache::get($this->parseKeyCacheKey($parseKey));
    }

    private function cacheResult(array $result): void
    {
        $ttlMinutes = config('cache.parsed_results_ttl', 60);
        $normalizedResult = $this->normalizeForCache($result);

        Cache::put($this->cacheKey($result['parse_key']), $normalizedResult, now()->addMinutes($ttlMinutes));
        Cache::put($this->parseKeyCacheKey($result['parse_key']), $normalizedResult, now()->addMinutes($ttlMinutes));
        session(['latest_parse_key' => $result['parse_key']]);
    }

    private function cacheKey(string $parseKey): string
    {
        return 'sessions:'.$this->sessionCacheNamespace().":parsed_results:{$parseKey}";
    }

    private function parseKeyCacheKey(string $parseKey): string
    {
        return "parsed_results:{$parseKey}";
    }

    private function sessionCacheNamespace(): string
    {
        $namespace = session('parsed_results_namespace');

        if (is_string($namespace) && $namespace !== '') {
            return $namespace;
        }

        $namespace = (string) Str::ulid();
        session(['parsed_results_namespace' => $namespace]);

        return $namespace;
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

    private function normalizeForCache(mixed $value): mixed
    {
        if ($value instanceof ParsedEventDTO) {
            return $this->normalizeForCache($value->toArray());
        }

        if ($value instanceof \JsonSerializable) {
            return $this->normalizeForCache($value->jsonSerialize());
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeForCache($item);
            }

            return $normalized;
        }

        if (is_object($value)) {
            throw new \LogicException('Unsupported object passed to cache boundary: '.$value::class);
        }

        return $value;
    }

    private function authorizeScheduleParser(Request $request): void
    {
        if (! config('features.schedule_parser.enabled', true)) {
            abort(404);
        }

        if (! $request->user()?->canUseScheduleParser()) {
            abort(403);
        }
    }

    private function authorizeScheduleParserDutyExport(Request $request): void
    {
        if (! config('features.schedule_parser.enabled', true)) {
            abort(404);
        }

        if (! $request->user()?->canExportScheduleParserDuty()) {
            abort(403);
        }
    }
}
