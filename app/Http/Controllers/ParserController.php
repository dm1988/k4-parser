<?php

namespace App\Http\Controllers;

use App\DTOs\Flight;
use App\DTOs\ParsedEventDTO;
use App\Enums\ParserEventType;
use App\Mappers\FlightMapper;
use App\Services\IcsCalendarService;
use App\Services\RosterDocumentParser;
use App\Services\RosterParser;
use App\Services\RosterSourceResolver;
use App\View\Models\Parser\ParserPageViewModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class ParserController extends Controller
{
    public function __construct(
        private readonly IcsCalendarService $icsCalendarService,
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
        $text = $request->validate([
            'text' => ['required', 'string'],
        ])['text'];

        $result = $this->buildResult(
            type: 'flight',
            source: 'text',
            documentType: null,
            raw: $text,
            rawText: $text,
            parsed: [
                'trip' => [],
                'calendar_events' => $this->rosterParser->extractFlightsDto($text), // Keep as DTO array!
            ],
        );

        $this->cacheResult($result);

        return back();
    }

    public function parseHotel(Request $request)
    {
        $text = $request->validate([
            'text' => ['required', 'string'],
        ])['text'];

        $result = $this->buildResult(
            type: 'hotel',
            source: 'text',
            documentType: null,
            raw: $text,
            rawText: $text,
            parsed: [
                'trip' => [],
                'calendar_events' => $this->rosterParser->extractHotels($text),
            ],
        );

        $this->cacheResult($result);

        return back();
    }

    public function parseRoster(Request $request)
    {
        $data = $request->validate([
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,bmp,tif,tiff,webp', 'max:20480', 'required_without:text'],
            'text' => ['nullable', 'string', 'required_without:file'],
            'event_types' => ['nullable', 'array'],
            'event_types.*' => [Rule::in(ParserEventType::filterValues())],
        ]);

        $eventTypes = $data['event_types'] ?? [];

        try {
            $source = $this->rosterSourceResolver->resolve(
                $request->file('file'),
                $data['text'] ?? null,
            );
        } catch (\Exception $e) {
            $message = $request->hasFile('file')
                ? 'Source resolution failed: '
                : 'Roster text resolution failed: ';

            return back()->withInput()->withErrors(['file' => $message . $e->getMessage()]);
        }

        $text = $source['raw_text'];
        $parsed = $this->rosterDocumentParser->parse(
            $text,
            $source['document_type'] ?? null,
        );

        if (! empty($eventTypes)) {
            $parsed['calendar_events'] = array_values(array_filter(
                $parsed['calendar_events'] ?? [],
                fn(mixed $event) => in_array($this->eventType($event), $eventTypes, true)
            ));
        }

        $result = $this->buildResult(
            type: 'roster',
            source: $source['source'],
            documentType: $source['document_type'] ?? null,
            raw: $source['raw'],
            rawText: $source['raw_text'],
            parsed: $parsed,
            filters: $eventTypes,
            file: $source['file'],
            mime: $source['mime'],
            meta: is_array($source['meta'] ?? null) ? $source['meta'] : [],
        );

        $this->cacheResult($result);

        return back();
    }

    public function exportCalendar(Request $request)
    {
        $sessionResult = $this->resolveExportResult($request);

        if (! is_array($sessionResult) || ! isset($sessionResult['parsed']['calendar_events'])) {
            abort(404);
        }

        $eventTypes = $request->query('event_types', $sessionResult['filters'] ?? []);
        $events = $sessionResult['parsed']['calendar_events'];

        if ($eventTypes !== []) {
            $events = array_values(array_filter(
                $events,
                fn(mixed $event) => in_array($this->eventType($event), $eventTypes, true),
            ));
        }

        if (count($events) === 0) {
            abort(404);
        }

        $tripNumber = $sessionResult['parsed']['trip']['trip_number'] ?? null;
        $filename = 'crew-compass' . ($tripNumber ? "-{$tripNumber}" : '') . '.ics';

        return response($this->icsCalendarService->serialize($events, $sessionResult['parsed']['trip'] ?? []), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportCalendarEvent(Request $request, string $eventId)
    {
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
        $slug = 'event-' . $eventId;
        $filename = 'crew-compass' . ($tripNumber ? "-{$tripNumber}" : '') . '-' . $slug . '.ics';

        return response($this->icsCalendarService->serialize([$event], $trip), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function buildResult(
        string $type,
        string $source,
        ?string $documentType,
        string $raw,
        string $rawText,
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
            'raw' => $raw,
            'raw_text' => $rawText,
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
                $event['download_id'] = $downloadId;
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

            if (is_array($event) && ($event['download_id'] ?? null) === $eventId) {
                return $event;
            }
        }

        return null;
    }

    private function resolveCachedResult(string $parseKey): mixed
    {
        return Cache::get($this->cacheKey($parseKey));
    }

    private function cacheResult(array $result): void
    {
        $ttlMinutes = config('cache.parsed_results_ttl', 60);
        $normalizedResult = $this->normalizeForCache($result);

        Cache::put($this->cacheKey($result['parse_key']), $normalizedResult, now()->addMinutes($ttlMinutes));
        session(['latest_parse_key' => $result['parse_key']]);
    }

    private function cacheKey(string $parseKey): string
    {
        return 'sessions:' . $this->sessionCacheNamespace() . ":parsed_results:{$parseKey}";
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
            throw new \LogicException('Unsupported object passed to cache boundary: ' . $value::class);
        }

        return $value;
    }
}
