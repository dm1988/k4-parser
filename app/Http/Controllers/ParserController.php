<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
        $text = $request->validate([
            'text' => ['required', 'string'],
        ])['text'];

        return back()->with('result', [
            'type' => 'roster',
            'raw' => $text,
            'parsed' => $this->extractRoster($text),
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

        $detailStart = array_search('Details', $lines, true);
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

    private function normaliseLines(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return array_values(array_filter(array_map(
            fn (string $line) => trim(preg_replace('/\s+/', ' ', $line)),
            explode("\n", $text),
        ), fn (string $line) => $line !== ''));
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
