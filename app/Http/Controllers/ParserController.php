<?php

namespace App\Http\Controllers;

use App\Services\IcsCalendarService;
use App\Services\RosterParser;
use App\Services\RosterSourceResolver;
use Illuminate\Http\Request;

class ParserController extends Controller
{
    public function __construct(
        private readonly IcsCalendarService $icsCalendarService,
        private readonly RosterParser $rosterParser,
        private readonly RosterSourceResolver $rosterSourceResolver,
    ) {
    }

    public function index()
    {
        return view('parse');
    }

    public function parseFlight(Request $request)
    {
        $text = $request->validate([
            'text' => ['required', 'string'],
        ])['text'];

        return back()->with('result', [
            'type' => 'flight',
            'raw' => $text,
            'parsed' => $this->rosterParser->extractFlights($text),
        ]);
    }

    public function parseHotel(Request $request)
    {
        $text = $request->validate([
            'text' => ['required', 'string'],
        ])['text'];

        return back()->with('result', [
            'type' => 'hotel',
            'raw' => $text,
            'parsed' => $this->rosterParser->extractHotels($text),
        ]);
    }

    public function parseRoster(Request $request)
    {
        $data = $request->validate([
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,bmp,tif,tiff,webp', 'max:20480', 'required_without:text'],
            'text' => ['nullable', 'string', 'required_without:file'],
            'event_types' => ['nullable', 'array'],
            'event_types.*' => ['in:flight,layover'],
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

            return back()->with('result', ['error' => $message . $e->getMessage()]);
        }

        $text = $source['raw_text'];
        $parsed = $this->rosterParser->parse($text);

        if (! empty($eventTypes)) {
            $parsed['calendar_events'] = array_values(array_filter(
                $parsed['calendar_events'] ?? [],
                fn (array $event) => in_array($event['type'] ?? '', $eventTypes, true)
            ));
        }

        $result = [
            'type' => 'roster',
            'source' => $source['source'],
            'file' => $source['file'],
            'mime' => $source['mime'],
            'raw' => $source['raw'],
            'raw_text' => $source['raw_text'],
            'parsed' => $parsed,
            'filters' => $eventTypes,
            'meta' => $source['meta'],
        ];

        session(['parsed_result' => $result]);

        return back()->with('result', $result);
    }

    public function exportCalendar(Request $request)
    {
        $sessionResult = session('parsed_result', session('result'));

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
        $filename = 'crew-compass' . ($tripNumber ? "-{$tripNumber}" : '') . '.ics';

        return response($this->icsCalendarService->serialize($events, $sessionResult['parsed']['trip'] ?? []), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportCalendarEvent(Request $request, int $eventIndex)
    {
        $sessionResult = session('parsed_result', session('result'));

        if (! is_array($sessionResult) || ! isset($sessionResult['parsed']['calendar_events'][$eventIndex])) {
            abort(404);
        }

        $event = $sessionResult['parsed']['calendar_events'][$eventIndex];
        $trip = $sessionResult['parsed']['trip'] ?? [];
        $tripNumber = $trip['trip_number'] ?? null;
        $slug = 'event-' . $eventIndex;
        $filename = 'crew-compass' . ($tripNumber ? "-{$tripNumber}" : '') . '-' . $slug . '.ics';

        return response($this->icsCalendarService->serialize([$event], $trip), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

}
