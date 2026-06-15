<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class RosterParser
{
    public function parse(string $text): array
    {
        $lines = $this->normaliseLines($text);
        $defaultYear = $this->detectRosterYear($lines);
        $monthYears = $this->detectMonthYears($lines, $defaultYear);

        $detailStart = $this->firstLineMatchingPattern($lines, '/\b(?:Details|Day\s*Flight\s*Departure)\b/i');
        $detailEnd = $this->firstLineMatchingPattern($lines, '/Duty Summary/i');

        if ($detailStart !== false) {
            $sliceLength = ($detailEnd !== false) ? ($detailEnd - $detailStart - 1) : null;
            $detailLines = array_slice($lines, $detailStart + 1, $sliceLength);
        } else {
            $detailLines = $lines;
        }

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

    public function extractFlights(string $text): array
    {
        return $this->parse($text)['calendar_events'];
    }

    public function extractHotels(string $text): array
    {
        return array_values(array_filter(
            $this->parse($text)['calendar_events'],
            fn (array $event) => $event['type'] === 'layover',
        ));
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
                $monthYears[strtolower(substr($matches[1], 0, 3))] = (int) $matches[2];
            }
        }

        return $monthYears ?: ['Jan' => $defaultYear];
    }

    private function firstLineMatchingPattern(array $lines, string $pattern): int|false
    {
        foreach ($lines as $index => $line) {
            if (preg_match($pattern, $line)) {
                return $index;
            }
        }

        return false;
    }

    private function detailBlocks(array $lines): array
    {
        $blocks = [];
        $currentBlock = [];
        $mode = null;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if (str_contains($trimmedLine, 'Duty start')) {
                if (! empty($currentBlock)) {
                    $blocks[] = $currentBlock;
                }

                $currentBlock = [$trimmedLine];
                $mode = 'duty';
                continue;
            }

            if ($this->isDateRange($trimmedLine)) {
                if (! empty($currentBlock)) {
                    $blocks[] = $currentBlock;
                }

                $currentBlock = [$trimmedLine];
                $mode = 'date-range';
                continue;
            }

            if ($mode !== null) {
                $currentBlock[] = $trimmedLine;

                if ($mode === 'duty' && str_contains($trimmedLine, 'Duty end')) {
                    $blocks[] = $currentBlock;
                    $currentBlock = [];
                    $mode = null;
                }
            }
        }

        if (! empty($currentBlock)) {
            $blocks[] = $currentBlock;
        }

        return $blocks;
    }

    private function parseDetailBlock(array $block, array $monthYears, int $defaultYear): ?array
    {
        if ($this->isDateRange($block[0] ?? '')) {
            return $this->parseDateRangeDetailBlock($block, $monthYears, $defaultYear);
        }

        if (count($block) < 3) {
            return null;
        }

        $lineData = $block[1];
        $lineEnd = $block[2];

        if (! preg_match('/(\d{1,2})([A-Za-z]{3})\s*Duty\s+end/i', $lineEnd, $dateMatches)) {
            return null;
        }

        $day = $dateMatches[1];
        $monthStr = $dateMatches[2];
        $year = $monthYears[strtolower($monthStr)] ?? $defaultYear;

        preg_match_all('/\d{2}:\d{2}/', $lineData, $timeMatchesAll);
        $timeMatches = $timeMatchesAll[0] ?? [];

        if (count($timeMatches) < 2) {
            return null;
        }

        $depTime = $timeMatches[0];
        $arrTime = $timeMatches[1];

        if (! preg_match('/(?:[A-Z]{2}\s+)?([A-Z0-9]+)\s*([A-Z]{3})-([A-Z]{3})/', $lineData, $flightMatches)) {
            return null;
        }

        $flightNumber = $flightMatches[1];
        $origin = $flightMatches[2];
        $destination = $flightMatches[3];

        try {
            $start = now()->createFromFormat('Y-M-d H:i', "{$year}-{$monthStr}-{$day} {$depTime}");
            $end = now()->createFromFormat('Y-M-d H:i', "{$year}-{$monthStr}-{$day} {$arrTime}");

            if ($end->lessThan($start)) {
                $end->addDay();
            }
        } catch (\Exception) {
            return null;
        }

        $isDeadhead = str_contains(strtoupper($lineData), ' DH ');
        $type = $isDeadhead ? 'layover' : 'flight';
        $title = "{$origin} - {$destination} ({$flightNumber})";

        return $this->calendarEvent($type, $title, $start, $end, [
            'flight_number' => $flightNumber,
            'origin' => $origin,
            'destination' => $destination,
            'deadhead' => $isDeadhead,
        ]);
    }

    private function parseDateRangeDetailBlock(array $block, array $monthYears, int $defaultYear): ?array
    {
        if (! preg_match('/^([A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2})\s+-\s+([A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2})$/', $block[0], $matches)) {
            return null;
        }

        $start = $this->parseRosterDate($matches[1], $monthYears, $defaultYear);
        $end = $this->parseRosterDate($matches[2], $monthYears, $defaultYear);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addYear();
        }

        $body = array_values(array_filter(array_slice($block, 1), fn (string $line): bool => $line !== ''));
        $joinedBody = implode(' ', $body);

        if ($route = $this->extractFlightRoute($body)) {
            $flightNumber = $this->extractFlightNumber($body);
            $position = $this->detectCrewPosition($body);
            $aircraft = $this->detectAircraft($body);
            $tailNumber = $this->detectTailNumber($body);
            $flightAwareUrl = $tailNumber
                ? 'https://www.flightaware.com/live/flight/' . rawurlencode($tailNumber)
                : null;
            $blockTime = $this->firstMatchingLine($body, '/\b\d{1,2}:\d{2}h\b/');
            $isDeadhead = (bool) preg_match('/\bDH\b/i', $joinedBody);

            return $this->calendarEvent(
                'flight',
                trim(($flightNumber ? "{$flightNumber} " : '') . "{$route['origin']}-{$route['destination']}"),
                $start,
                $end,
                array_filter([
                    'flight_number' => $flightNumber,
                    'origin' => $route['origin'],
                    'destination' => $route['destination'],
                    'position' => $position,
                    'aircraft' => $aircraft,
                    'tail_number' => $tailNumber,
                    'flightaware_url' => $flightAwareUrl,
                    'block_time' => $blockTime,
                    'deadhead' => $isDeadhead,
                    'raw_lines' => $body,
                ], fn ($value) => $value !== null && $value !== '')
            );
        }

        if ($layover = $this->extractLayover($body)) {
            return $this->calendarEvent(
                'layover',
                "Layover {$layover['station']}",
                $start,
                $end,
                [
                    'station' => $layover['station'],
                    'hotel' => $layover['hotel'],
                    'raw_lines' => $body,
                ],
            );
        }

        if (preg_match('/\b([A-Z]{3})\b/', $joinedBody, $matches)) {
            return $this->calendarEvent(
                'duty',
                "Duty {$matches[1]}",
                $start,
                $end,
                [
                    'station' => $matches[1],
                    'raw_lines' => $body,
                ],
            );
        }

        return null;
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

        $fullText = implode("\n", $lines);

        if (preg_match('/Trip\s*Id:\s*(\d+)/i', $fullText, $matches)) {
            $summary['trip_number'] = $matches[1];
        } elseif (preg_match('/\bTrip\b\D+(\d{4,})\b/s', $fullText, $matches)) {
            $summary['trip_number'] = $matches[1];
        }

        if (preg_match('/Crew:\s*\d*([A-Z]{2})/i', $fullText, $matches)) {
            $summary['position'] = strtoupper($matches[1]);
        } elseif (preg_match('/\b\d{4,}\s*\|?\s*([A-Z]{2})\.?\s+[A-Z]{3}\b/', $fullText, $matches)) {
            $summary['position'] = strtoupper($matches[1]);
        } else {
            $summary['position'] = $this->detectCrewPosition($lines);
        }

        if (preg_match('/Homebase:\s*([A-Z]{3})/i', $fullText, $matches)) {
            $summary['base'] = $matches[1];
        } elseif (preg_match('/\b\d{4,}\s*\|?\s*[A-Z]{2}\.?\s+([A-Z]{3})\b/', $fullText, $matches)) {
            $summary['base'] = $matches[1];
        }

        if (preg_match('/Block\s+Time:\s*(\d{2}:\d{2})/i', $fullText, $matches)) {
            $summary['block_time'] = $matches[1];
        } elseif (preg_match('/\bBlock\s+(\d{1,2}:\d{2}h?)\b/i', $fullText, $matches)) {
            $summary['block_time'] = $matches[1];
        }

        if (preg_match_all('/([A-Z]{3})-([A-Z]{3})/', $fullText, $matches)) {
            $stations = [];
            foreach ($matches[2] as $arrivalStation) {
                if ($summary['base'] && $arrivalStation !== $summary['base']) {
                    $stations[] = $arrivalStation;
                }
            }
            $summary['layovers'] = array_values(array_unique($stations));
        }

        if (preg_match('/Date:\s*(\d{2}[A-Za-z]{3}\d{4})/', $fullText, $matches)) {
            $summary['roster_range'] = $matches[1];
        }

        return $summary;
    }

    private function extractFlightRoute(array $lines): ?array
    {
        foreach ($lines as $line) {
            if (preg_match('/\b([A-Z]{3})\s*-\s*([A-Z]{3})\b/', $line, $matches)) {
                return [
                    'origin' => $matches[1],
                    'destination' => $matches[2],
                ];
            }
        }

        return null;
    }

    private function extractLayover(array $lines): ?array
    {
        foreach ($lines as $line) {
            if (! preg_match('/\b([A-Z]{3})\s*-\s*(.+)$/', $line, $matches)) {
                continue;
            }

            $hotel = preg_replace('/\s+[vV]{1,2}\s*$/', '', trim($matches[2]));

            if (preg_match('/^[A-Z]{3}\b/', $hotel)) {
                continue;
            }

            return [
                'station' => $matches[1],
                'hotel' => trim($hotel, " \t\n\r\0\x0B|"),
            ];
        }

        return null;
    }

    private function extractFlightNumber(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/\b([A-Z][A-Z0-9]?\s*\d{1,4}[A-Z]?)\b/', $line, $matches)) {
                $flightNumber = preg_replace('/\s+/', ' ', trim($matches[1]));

                return preg_replace('/^K4\s+/', 'CKS ', $flightNumber);
            }
        }

        return null;
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

    private function detectTailNumber(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/\b(?:N\d{1,5}[A-Z]{0,2}|[A-Z]{1,2}-?[A-Z0-9]{3,6})\b/', $line, $matches)) {
                return $matches[0];
            }
        }

        return null;
    }
}
