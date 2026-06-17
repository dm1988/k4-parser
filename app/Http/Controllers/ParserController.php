<?php

namespace App\Http\Controllers;

use App\Enums\ParserEventType;
use App\Services\IcsCalendarService;
use App\Services\RosterDocumentParser;
use App\Services\RosterParser;
use App\Services\RosterSourceResolver;
use App\View\Models\Parser\ParserPageViewModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

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
            'viewModel' => ParserPageViewModel::fromSession(
                session('result'),
                session()->getOldInput(),
            ),
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
                'calendar_events' => $this->rosterParser->extractFlights($text),
            ],
        );

        session(['parsed_result' => $result]);
        session()->put("parsed_results.{$result['parse_key']}", $result);

        return back()->with('result', [
            'type' => 'flight',
            'raw' => $text,
            'parsed' => $result['parsed'],
            'parse_key' => $result['parse_key'],
        ]);
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

        session(['parsed_result' => $result]);
        session()->put("parsed_results.{$result['parse_key']}", $result);

        return back()->with('result', [
            'type' => 'hotel',
            'raw' => $text,
            'parsed' => $result['parsed']['calendar_events'],
            'parse_key' => $result['parse_key'],
        ]);
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

            return back()->with('result', ['error' => $message.$e->getMessage()]);
        }

        $text = $source['raw_text'];
        $parsed = $this->rosterDocumentParser->parse(
            $text,
            $source['document_type'] ?? null,
        );

        if (! empty($eventTypes)) {
            $parsed['calendar_events'] = array_values(array_filter(
                $parsed['calendar_events'] ?? [],
                fn (array $event) => in_array($event['type'] ?? '', $eventTypes, true)
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

        session(['parsed_result' => $result]);
        session()->put("parsed_results.{$result['parse_key']}", $result);

        return back()->with('result', $result);
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
                fn (array $event) => in_array($event['type'], $eventTypes, true),
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
            if (! is_array($event)) {
                continue;
            }

            $event['download_id'] = (string) Str::ulid();
            $events[] = $event;
        }

        $parsed['calendar_events'] = $events;

        return $parsed;
    }

    private function resolveExportResult(Request $request): mixed
    {
        $parseKey = $request->query('parse_key');

        if (is_string($parseKey) && $parseKey !== '') {
            return session("parsed_results.{$parseKey}");
        }

        return session('parsed_result', session('result'));
    }

    private function findEventByDownloadId(array $events, string $eventId): ?array
    {
        foreach ($events as $event) {
            if (is_array($event) && ($event['download_id'] ?? null) === $eventId) {
                return $event;
            }
        }

        return null;
    }
}
