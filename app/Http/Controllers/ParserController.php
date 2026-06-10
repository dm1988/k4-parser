<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Throwable;

class ParserController extends Controller
{
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
            'parsed' => $this->extractFlight($text),
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
            'parsed' => $this->extractHotel($text),
        ]);
    }

    public function parseRoster(Request $request)
    {
        $data = $request->validate([
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,bmp,tif,tiff,webp', 'max:10240', 'required_without:text'],
            'text' => ['nullable', 'string', 'required_without:image'],
            'event_types' => ['nullable', 'array'],
            'event_types.*' => ['in:flight,layover'],
        ]);

        $source = 'text';
        $text = $data['text'] ?? '';

        if ($request->hasFile('image')) {
            $source = 'image';
            $text = $this->extractTextFromImage($request->file('image')->getRealPath());
        }

        $parsed = $this->extractRoster($text);
        $eventTypes = $data['event_types'] ?? [];

        if ($eventTypes !== []) {
            $parsed['calendar_events'] = array_values(array_filter(
                $parsed['calendar_events'],
                fn (array $event) => in_array($event['type'], $eventTypes, true),
            ));
        }

        $result = [
            'type' => 'roster',
            'source' => $source,
            'filters' => $eventTypes,
            'raw' => $text,
            'parsed' => $parsed,
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

        return response($this->buildIcs($events, $sessionResult['parsed']['trip'] ?? []), 200, [
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
        $slug = 'event-'.$eventIndex;
        $filename = 'crew-compass' . ($tripNumber ? "-{$tripNumber}" : '') . '-' . $slug . '.ics';

        return response($this->buildIcs([$event], $trip), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function extractFlight(string $text): array
    {
        return $this->extractRoster($text)['calendar_events'];
    }

    private function extractHotel(string $text): array
    {
        return array_values(array_filter(
            $this->extractRoster($text)['calendar_events'],
            fn (array $event) => $event['type'] === 'layover',
        ));
    }

    private function extractRoster(string $text): array
    {
        $lines = $this->normaliseLines($text);
        $defaultYear = $this->detectRosterYear($lines);
        $monthYears = $this->detectMonthYears($lines, $defaultYear);

        $detailStart = $this->firstLineIndexContaining($lines, 'Details');
        $detailLines = $detailStart === false ? $lines : array_slice($lines, $detailStart + 1);

        $events = [];

        foreach ($this->detailBlocks($detailLines) as $block) {
            $event = $this->parseDetailBlock($block, $monthYears, $defaultYear);

            if ($event !== null) {
                $events[] = $event;
            }
        }

        return [
            'trip' => $this->extractTripSummary($lines),
            'calendar_events' => $events,
        ];
    }

    private function extractTextFromImage(string $path): string
    {
        $tesseract = config('services.ocr.tesseract_path', '/usr/bin/tesseract');

        if (! is_executable($tesseract)) {
            throw ValidationException::withMessages([
                'image' => "OCR is not installed in the web server container. Expected Tesseract at {$tesseract}.",
            ]);
        }

        $process = new Process([
            $tesseract,
            $path,
            'stdout',
            '--psm',
            '6',
        ]);
        $process->setTimeout(30);

        try {
            $process->mustRun();
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'image' => 'OCR failed. Try a sharper roster screenshot or paste the extracted text instead.',
            ]);
        }

        $text = trim($process->getOutput());

        if ($text === '') {
            throw ValidationException::withMessages([
                'image' => 'OCR did not find any text in that image. Try a clearer screenshot or paste the text manually.',
            ]);
        }

        return $text;
    }

    private function normaliseLines(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $lines = [];

        foreach (explode("\n", $text) as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line));

            if ($line === '') {
                continue;
            }

            if (preg_match('/([A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2}\s+-\s+[A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2})/', $line, $matches)) {
                $before = trim(substr($line, 0, strpos($line, $matches[1])));
                $after = trim(substr($line, strpos($line, $matches[1]) + strlen($matches[1])));

                if ($before !== '') {
                    $lines[] = $before;
                }

                $lines[] = $matches[1];

                if ($after !== '') {
                    $lines[] = $after;
                }

                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    private function detectRosterYear(array $lines): int
    {
        foreach ($lines as $line) {
            if (preg_match('/\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2}\s+\d{2}:\d{2}\b/', $line)) {
                continue;
            }

            if (preg_match('/\b(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})\b/', $line, $matches)) {
                return (int) $matches[1];
            }
        }

        return (int) now()->year;
    }

    private function detectMonthYears(array $lines, int $defaultYear): array
    {
        $monthYears = [];

        foreach ($lines as $line) {
            if (preg_match('/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})\b/', $line, $matches)) {
                $monthYears[substr($matches[1], 0, 3)] = (int) $matches[2];
            }
        }

        return $monthYears ?: ['Jan' => $defaultYear];
    }

    private function detailBlocks(array $lines): array
    {
        $blocks = [];
        $current = [];

        foreach ($lines as $line) {
            if ($this->isDateRange($line)) {
                if ($current !== []) {
                    $blocks[] = $current;
                }

                $current = [$line];
                continue;
            }

            if ($current !== []) {
                $current[] = $line;
            }
        }

        if ($current !== []) {
            $blocks[] = $current;
        }

        return $blocks;
    }

    private function parseDetailBlock(array $block, array $monthYears, int $defaultYear): ?array
    {
        $range = array_shift($block);

        if (! preg_match('/^([A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2})\s+-\s+([A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2})$/', $range, $matches)) {
            return null;
        }

        $start = $this->parseRosterDate($matches[1], $monthYears, $defaultYear);
        $end = $this->parseRosterDate($matches[2], $monthYears, $defaultYear);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addYear();
        }

        $flightNumber = $this->firstMatchingLine($block, '/^[A-Z0-9]{1,3}\s+\d{2,5}$/');
        $airportRoute = $this->firstMatchingLine($block, '/^([A-Z]{3})\s+-\s+([A-Z]{3})$/');
        $hotelRoute = $this->firstMatchingLine($block, '/^([A-Z]{3})\s+-\s+(.+)$/');
        $station = $this->firstMatchingLine($block, '/^[A-Z]{3}$/');
        $blockText = implode(' ', $block);

        if ($flightNumber === null && preg_match('/\b([A-Z0-9]{1,3}\s+\d{2,5})\b/', $blockText, $matches)) {
            $flightNumber = $matches[1];
        }

        if ($airportRoute === null && preg_match('/\b([A-Z]{3})\s*-\s*([A-Z]{3})\b/', $blockText, $matches)) {
            $airportRoute = "{$matches[1]} - {$matches[2]}";
        }

        if ($hotelRoute === null && preg_match('/\b([A-Z]{3})\s*-\s*([^|]+?)(?:\s+[vV]+)?(?:\s+\d+:\d{2}h?)?$/', $blockText, $matches)) {
            $hotelRoute = "{$matches[1]} - ".trim($matches[2]);
        }

        if ($station === null && preg_match('/\b([A-Z]{3})\b/', $blockText, $matches)) {
            $station = $matches[1];
        }

        if ($flightNumber !== null && $airportRoute !== null && preg_match('/^([A-Z]{3})\s+-\s+([A-Z]{3})$/', $airportRoute, $routeMatches)) {
            $origin = $routeMatches[1];
            $destination = $routeMatches[2];

            return $this->calendarEvent('flight', "{$flightNumber} {$origin}-{$destination}", $start, $end, [
                'flight_number' => $flightNumber,
                'origin' => $origin,
                'destination' => $destination,
                'position' => $this->detectCrewPosition($block),
                'aircraft' => $this->detectAircraft($block),
                'block_time' => $this->firstMatchingLine($block, '/^\d+:\d{2}h$/'),
                'raw_lines' => $block,
            ]);
        }

        if ($hotelRoute !== null && preg_match('/^([A-Z]{3})\s+-\s+(.+)$/', $hotelRoute, $hotelMatches) && ! preg_match('/^[A-Z]{3}$/', $hotelMatches[2])) {
            $station = $hotelMatches[1];
            $hotel = $hotelMatches[2];

            return $this->calendarEvent('layover', "Layover {$station}", $start, $end, [
                'station' => $station,
                'hotel' => $hotel,
                'duration' => $this->firstMatchingLine($block, '/^\d+:\d{2}h?$/'),
                'raw_lines' => $block,
            ]);
        }

        if ($station !== null) {
            return $this->calendarEvent('duty', "Duty {$station}", $start, $end, [
                'station' => $station,
                'duration' => $this->firstMatchingLine($block, '/^\d+:\d{2}h?$/'),
                'raw_lines' => $block,
            ]);
        }

        return $this->calendarEvent('duty', 'Roster duty', $start, $end, [
            'raw_lines' => $block,
        ]);
    }

    private function parseRosterDate(string $value, array $monthYears, int $defaultYear): Carbon
    {
        preg_match('/^([A-Z][a-z]{2})\s+(\d{1,2})\s+(\d{2}:\d{2})$/', $value, $matches);

        $year = $monthYears[$matches[1]] ?? $defaultYear;

        return Carbon::createFromFormat('Y M j H:i', "{$year} {$matches[1]} {$matches[2]} {$matches[3]}");
    }

    private function calendarEvent(string $type, string $title, Carbon $start, Carbon $end, array $metadata = []): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'start' => $start->toIso8601String(),
            'end' => $end->toIso8601String(),
            'timezone' => config('app.timezone'),
            'metadata' => $metadata,
        ];
    }

    private function buildIcs(array $events, array $trip = []): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'PRODID:-//Crew Compass//Roster Parser//EN',
        ];

        if (! empty($trip['trip_number'])) {
            $lines[] = 'X-WR-CALNAME:Crew Compass Trip '.$this->escapeIcsValue($trip['trip_number']);
        }

        $lines[] = 'X-WR-CALDESC:Calendar export from Crew Compass';

        foreach ($events as $event) {
            $start = Carbon::parse($event['start'])->setTimezone('UTC');
            $end = Carbon::parse($event['end'])->setTimezone('UTC');
            $description = $this->formatEventDescription($event);
            $uid = sha1($event['title'].$event['start'].$event['end']);

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$uid.'@crew-compass';
            $lines[] = 'DTSTAMP:'.now()->setTimezone('UTC')->format('Ymd\THis\Z');
            $lines[] = 'DTSTART:'.$start->format('Ymd\THis\Z');
            $lines[] = 'DTEND:'.$end->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:'.$this->escapeIcsValue($event['title']);
            $lines[] = 'DESCRIPTION:'.$this->escapeIcsValue($description);
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    private function formatEventDescription(array $event): string
    {
        $description = ['Type: '.ucfirst($event['type'])];

        foreach ($event['metadata'] as $key => $value) {
            if ($key === 'raw_lines') {
                continue;
            }

            if (is_array($value)) {
                $value = implode(', ', array_filter($value, fn ($item) => $item !== null && $item !== ''));
            }

            if ($value === null || $value === '') {
                continue;
            }

            $description[] = ucfirst(str_replace('_', ' ', $key)).': '.$value;
        }

        return implode("\n", $description);
    }

    private function escapeIcsValue(string $value): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", ',', ';'],
            ['\\\\', '\\n', '\\n', '\\,', '\\;'],
            $value,
        );
    }

    private function extractTripSummary(array $lines): array
    {
        $summary = [
            'trip_number' => null,
            'position' => null,
            'base' => null,
            'layovers' => [],
            'block_time' => null,
            'roster_range' => null,
        ];

        foreach ($lines as $index => $line) {
            if ($line === 'Roster' && isset($lines[$index + 1]) && preg_match('/^[A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2}\s+-\s+[A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2}/', $lines[$index + 1])) {
                $summary['roster_range'] = $lines[$index + 1];
            }

            if ($line === 'Trip' && isset($lines[$index + 1]) && preg_match('/^\d+$/', $lines[$index + 1])) {
                $summary['trip_number'] = $lines[$index + 1];
            }

            if ($line === 'FO' || $line === 'CA') {
                $summary['position'] ??= $line;
            }

            if (preg_match('/^Block\s+(\d+:\d{2}h)$/', $line, $matches)) {
                $summary['block_time'] = $matches[1];
            }
        }

        $headerIndex = array_search('Pos Stn Layovers', $lines, true);

        if ($headerIndex !== false && isset($lines[$headerIndex + 1], $lines[$headerIndex + 2])) {
            $summary['position'] = $lines[$headerIndex + 1];
            $stations = preg_split('/\s+/', $lines[$headerIndex + 2]);
            $summary['base'] = $stations[0] ?? null;
            $summary['layovers'] = array_values(array_filter(array_slice($stations, 1)));
        }

        return $summary;
    }

    private function isDateRange(string $line): bool
    {
        return (bool) preg_match('/^[A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2}\s+-\s+[A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2}$/', $line);
    }

    private function firstMatchingLine(array $lines, string $pattern): ?string
    {
        foreach ($lines as $line) {
            if (preg_match($pattern, $line)) {
                return $line;
            }
        }

        return null;
    }

    private function firstLineIndexContaining(array $lines, string $needle): int|false
    {
        foreach ($lines as $index => $line) {
            if (str_contains($line, $needle)) {
                return $index;
            }
        }

        return false;
    }

    private function detectCrewPosition(array $lines): ?string
    {
        $positions = ['CA', 'CAPT', 'FO', 'DH', 'FE', 'AC'];

        foreach ($lines as $line) {
            if (in_array($line, $positions, true)) {
                return $line;
            }
        }

        return null;
    }

    private function detectAircraft(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/^(?:\d{2}[A-Z]|[A-Z]\d{2})$/', $line)) {
                return $line;
            }
        }

        return null;
    }
}
