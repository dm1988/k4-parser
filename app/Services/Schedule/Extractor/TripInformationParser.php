<?php

namespace App\Services\Schedule\Extractor;

use App\DTOs\Flight;
use App\Enums\CrewPosition;
use App\Enums\ParserEventType;
use App\Mappers\FlightMapper;
use App\Services\Clients\AirlineCodeLookupClient;
use Illuminate\Support\Carbon;

class TripInformationParser
{
    public function __construct(
        private readonly FlightMapper $flightMapper,
        private readonly CrewListParser $crewListParser,
        private readonly AirlineCodeLookupClient $airlineCodeLookupClient,
    ) {}

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

        $events = $this->attachDutyFlightContext($events);

        return [
            'trip' => $this->extractTripSummary($lines),
            'calendar_events' => $events,
        ];
    }

    public function extractFlights(string $text): array
    {
        return $this->parse($text)['calendar_events'];
    }

    /**
     * Return parsed flights as `App\DTOs\Flight` instances.
     *
     * @return list<Flight>
     */
    public function extractFlightsDto(string $text): array
    {
        $events = $this->extractFlights($text);

        $flights = [];

        foreach ($events as $event) {
            $dto = $this->flightMapper->fromCalendarEvent($event);

            if ($dto !== null) {
                $flights[] = $dto;
            }
        }

        return $flights;
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
            $line = str_replace(['—', '–'], '-', $line);
            $line = preg_replace('/\b([A-Z][a-z]{2}\s+\d{1,2})(\d{2}:\d{2})\b/', '$1 $2', $line) ?? $line;
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
                $previousLine = $currentBlock === [] ? null : end($currentBlock);

                if (is_string($previousLine) && preg_match('/\b(?:Leg|Duty) LT\b/i', $previousLine) === 1) {
                    $currentBlock[] = $trimmedLine;

                    continue;
                }

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
        $timeMatches = $timeMatchesAll[0];

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
        $commercialDeadhead = $this->resolveCommercialDeadhead($flightNumber, $isDeadhead);
        $flightNumber = $commercialDeadhead['flight_number'];
        $type = $isDeadhead ? ParserEventType::Deadhead->value : ParserEventType::Flight->value;
        $title = "{$origin} - {$destination} ({$flightNumber})";

        return $this->calendarEvent($type, $title, $start, $end, [
            'flight_number' => $flightNumber,
            'origin' => $origin,
            'destination' => $destination,
            'deadhead' => $isDeadhead,
            'airline_name' => $commercialDeadhead['airline_name'],
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
            $position = $this->extractFlightPosition($body);
            $aircraft = $this->detectAircraft($body);
            $tailNumber = $this->detectTailNumber($body);
            $flightAwareUrl = $tailNumber
                ? 'https://www.flightaware.com/live/flight/'.rawurlencode($tailNumber)
                : null;
            $blockTime = $this->extractBlockTime($body);
            $dutyStation = $this->extractDutyStationFromLines($body);
            $dutyRawLines = $this->extractDutyRawLines($body);
            $isDeadhead = $position === CrewPosition::Deadhead->value;
            $commercialDeadhead = $this->resolveCommercialDeadhead($flightNumber, $isDeadhead);
            $flightNumber = $commercialDeadhead['flight_number'];
            $crewSummary = $this->crewListParser->parseWithSummary($body);
            $localTimes = $this->extractFlightLocalTimes($body);

            return $this->calendarEvent(
                $isDeadhead ? ParserEventType::Deadhead->value : ParserEventType::Flight->value,
                trim(($flightNumber ? "{$flightNumber} " : '')."{$route['origin']}-{$route['destination']}"),
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
                    'duty_station' => $dutyStation,
                    'crew_count' => $crewSummary['crew_count'],
                    'operating_crew_count' => $crewSummary['operating_crew_count'],
                    'deadheading_crew_count' => $crewSummary['deadheading_crew_count'],
                    'crew' => $crewSummary['crew'] !== [] ? $crewSummary['crew'] : null,
                    'deadhead' => $isDeadhead,
                    'airline_name' => $commercialDeadhead['airline_name'],
                    'raw_lines' => $body,
                    'duty_raw_lines' => $dutyRawLines !== [] ? $dutyRawLines : null,
                    ...$localTimes,
                ], fn (mixed $value): bool => $value !== null)
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

        if (preg_match('/\bCrew list\b/i', $joinedBody)) {
            $crewSummary = $this->crewListParser->parseWithSummary($body);

            return $this->calendarEvent(
                'duty',
                'Duty Crew',
                $start,
                $end,
                array_filter([
                    'crew_count' => $crewSummary['crew_count'],
                    'operating_crew_count' => $crewSummary['operating_crew_count'],
                    'deadheading_crew_count' => $crewSummary['deadheading_crew_count'],
                    'crew' => $crewSummary['crew'] !== [] ? $crewSummary['crew'] : null,
                    'raw_lines' => $body,
                ], fn (mixed $value): bool => $value !== null)
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

    private function attachDutyFlightContext(array $events): array
    {
        foreach ($events as $eventIndex => $event) {
            if (! $this->shouldAttachDutyToFlight($event)) {
                continue;
            }

            $flightIndex = $this->findMatchingFlightEventIndex($events, $event);

            if ($flightIndex === null) {
                continue;
            }

            $events[$flightIndex] = $this->mergeDutyIntoFlightEvent($events[$flightIndex], $event);
            unset($events[$eventIndex]);
        }

        return array_values($events);
    }

    private function shouldAttachDutyToFlight(array $event): bool
    {
        if (($event['type'] ?? null) !== 'duty') {
            return false;
        }

        $rawLines = data_get($event, 'metadata.raw_lines');

        if (! is_array($rawLines) || $rawLines === []) {
            return false;
        }

        $joinedLines = implode(' ', $rawLines);

        return preg_match('/\bDuty LT\b/i', $joinedLines) === 1
            || preg_match('/\bFlight Info\b/i', $joinedLines) === 1
            || preg_match('/\bCrew list\b/i', $joinedLines) === 1;
    }

    private function findMatchingFlightEventIndex(array $events, array $dutyEvent): ?int
    {
        $bestIndex = null;
        $bestScore = 0;
        $dutyStart = Carbon::parse($dutyEvent['start']);
        $dutyEnd = Carbon::parse($dutyEvent['end']);

        foreach ($events as $index => $event) {
            if (! ParserEventType::fromEvent($event)->isFlightLike()) {
                continue;
            }

            $score = 0;
            $flightRawLines = data_get($event, 'metadata.raw_lines', []);
            $flightJoinedLines = is_array($flightRawLines) ? implode(' ', $flightRawLines) : '';

            if (preg_match('/\bLeg LT\b/i', $flightJoinedLines) === 1) {
                $score += 3;
            }

            $flightStart = Carbon::parse($event['start']);
            $flightEnd = Carbon::parse($event['end']);

            if ($flightStart->lessThan($dutyEnd) && $flightEnd->greaterThan($dutyStart)) {
                $score += 4;
            } elseif ($flightStart->diffInHours($dutyStart) <= 18) {
                $score += 1;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $index;
            }
        }

        return $bestScore >= 4 ? $bestIndex : null;
    }

    private function mergeDutyIntoFlightEvent(array $flightEvent, array $dutyEvent): array
    {
        $flightMetadata = is_array($flightEvent['metadata'] ?? null) ? $flightEvent['metadata'] : [];
        $dutyMetadata = is_array($dutyEvent['metadata'] ?? null) ? $dutyEvent['metadata'] : [];
        $flightRawLines = is_array($flightMetadata['raw_lines'] ?? null) ? $flightMetadata['raw_lines'] : [];
        $dutyRawLines = is_array($dutyMetadata['raw_lines'] ?? null) ? $dutyMetadata['raw_lines'] : [];

        $flightMetadata['raw_lines'] = array_values(array_unique([
            ...$flightRawLines,
            ...$dutyRawLines,
        ]));
        $flightMetadata['duty_raw_lines'] = $dutyRawLines;
        $flightMetadata = [
            ...$flightMetadata,
            ...$this->extractFlightLocalTimes($flightMetadata['raw_lines']),
        ];

        if (! empty($dutyMetadata['station']) && empty($flightMetadata['duty_station'])) {
            $flightMetadata['duty_station'] = $dutyMetadata['station'];
        }

        if (empty($flightMetadata['duty_station'])) {
            $flightMetadata['duty_station'] = $this->extractDutyStationFromLines($flightMetadata['raw_lines']);
        }

        if (! empty($dutyMetadata['crew_count']) && empty($flightMetadata['crew_count'])) {
            $flightMetadata['crew_count'] = $dutyMetadata['crew_count'];
        }

        if (! empty($dutyMetadata['operating_crew_count']) && empty($flightMetadata['operating_crew_count'])) {
            $flightMetadata['operating_crew_count'] = $dutyMetadata['operating_crew_count'];
        }

        if (! empty($dutyMetadata['deadheading_crew_count']) && empty($flightMetadata['deadheading_crew_count'])) {
            $flightMetadata['deadheading_crew_count'] = $dutyMetadata['deadheading_crew_count'];
        }

        if (! empty($dutyMetadata['crew']) && empty($flightMetadata['crew'])) {
            $flightMetadata['crew'] = $dutyMetadata['crew'];
        }

        $flightEvent['metadata'] = $flightMetadata;

        return $flightEvent;
    }

    /**
     * @return array{flight_number: ?string, airline_name: ?string}
     */
    private function resolveCommercialDeadhead(?string $flightNumber, bool $isDeadhead): array
    {
        if (! $isDeadhead || $flightNumber === null) {
            return [
                'flight_number' => $flightNumber,
                'airline_name' => null,
            ];
        }

        $condensedFlightNumber = strtoupper(str_replace(' ', '', $flightNumber));

        if (preg_match('/^([A-Z0-9]{2})(\d+)$/', $condensedFlightNumber, $matches) !== 1) {
            return [
                'flight_number' => $flightNumber,
                'airline_name' => null,
            ];
        }

        $airlineName = $this->airlineCodeLookupClient->airlineNameForIataCode($matches[1]);

        if (! is_string($airlineName) || trim($airlineName) === '') {
            return [
                'flight_number' => $flightNumber,
                'airline_name' => null,
            ];
        }

        return [
            'flight_number' => "{$matches[1]} {$matches[2]}",
            'airline_name' => trim($airlineName),
        ];
    }

    /**
     * @param  list<string>  $lines
     * @return array{
     *     leg_local_start?: string,
     *     leg_local_end?: string,
     *     duty_local_start?: string,
     *     duty_local_end?: string
     * }
     */
    private function extractFlightLocalTimes(array $lines): array
    {
        $joinedLines = trim(preg_replace('/\s+/', ' ', implode(' ', $lines)) ?? '');

        if ($joinedLines === '') {
            return [];
        }

        return array_filter([
            'leg_local_start' => $this->extractLocalTimeBoundary($joinedLines, 'Leg LT', 1),
            'leg_local_end' => $this->extractLocalTimeBoundary($joinedLines, 'Leg LT', 2),
            'duty_local_start' => $this->extractLocalTimeBoundary($joinedLines, 'Duty LT', 1),
            'duty_local_end' => $this->extractLocalTimeBoundary($joinedLines, 'Duty LT', 2),
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }

    private function extractLocalTimeBoundary(string $input, string $label, int $captureGroup): ?string
    {
        $pattern = '/\b'.preg_quote($label, '/').'\b\s+([A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2})\s*-\s*([A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2})/';

        if (preg_match($pattern, $input, $matches) !== 1) {
            return null;
        }

        return $matches[$captureGroup] ?? null;
    }

    /**
     * @param  list<string>  $lines
     */
    private function extractDutyStationFromLines(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/\b([A-Z]{3})\s+\1\b.*(?:Flight Info|Customer|\.)/i', $line, $matches) === 1) {
                return strtoupper($matches[1]);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function extractDutyRawLines(array $lines): array
    {
        foreach ($lines as $index => $line) {
            if (preg_match('/\b(?:Duty LT|Flight Info|Crew list)\b/i', $line) === 1) {
                return array_slice($lines, $index);
            }
        }

        return [];
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
            $summary['position'] = $this->crewListParser->detectPosition($lines);
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

    /**
     * @param  list<string>  $lines
     */
    private function extractFlightPosition(array $lines): ?string
    {
        foreach ($lines as $index => $line) {
            if (preg_match(
                '/\b[A-Z]{3}\s*-\s*[A-Z]{3}\s*\|\s*('.CrewPosition::regexPattern().')\b/i',
                $line,
                $matches,
            ) === 1) {
                return strtoupper($matches[1]);
            }

            if (preg_match('/\b[A-Z]{3}\s*-\s*[A-Z]{3}\b/', $line) !== 1) {
                continue;
            }

            $position = CrewPosition::tryFrom(strtoupper(trim($lines[$index + 1] ?? '')));

            if ($position !== null) {
                return $position->value;
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

    private function detectAircraft(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/^(?:\d{2}[A-Z]|[A-Z]\d{2})$/', $line)) {
                return $line;
            }

            if (preg_match('/\b(?:\d{2}[A-Z]|[A-Z]\d{2})\b/', $line, $matches)) {
                return $matches[0];
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

    private function extractBlockTime(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/\b(\d{1,2}:\d{2}h)\b/', $line, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
