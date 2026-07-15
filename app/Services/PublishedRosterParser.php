<?php

namespace App\Services;

use App\Enums\MetadataKey;
use Illuminate\Support\Carbon;

class PublishedRosterParser
{
    public function __construct(
        private readonly AirlineCodeLookup $airlineCodeLookup,
    ) {}

    public function parse(string $text): array
    {
        $lines = $this->normaliseLines($text);
        $planningPeriod = $this->detectPlanningPeriod($lines);
        $entries = $this->extractEntries($lines, $planningPeriod);
        $tripIds = [];
        $events = [];
        $pendingFlight = null;

        foreach ($entries as $entry) {
            $body = $entry['body'];
            $compactBody = $this->compact($body);

            if ($compactBody === '' || str_contains($compactBody, 'OFF') || str_starts_with($compactBody, 'LAYOVER:')) {
                continue;
            }

            $flightFragment = $this->parseFlightFragment($entry);

            if ($flightFragment !== null) {
                [$parsedEvents, $pendingFlight, $fragmentTripIds] = $this->consumeFlightFragment($flightFragment, $pendingFlight);

                $events = [...$events, ...$parsedEvents];
                $tripIds = [...$tripIds, ...$fragmentTripIds];

                continue;
            }

            $pendingFlight = null;

            $dutyEvent = $this->parseDutyEvent($entry);

            if ($dutyEvent === null) {
                continue;
            }

            $events[] = $dutyEvent;

            if (! empty($dutyEvent['metadata'][MetadataKey::LayoverDuration->value])) {
                $events[] = $this->buildLayoverEvent(
                    $dutyEvent['metadata'][MetadataKey::Station->value] ?? null,
                    Carbon::parse($dutyEvent['end']),
                    $dutyEvent['metadata'][MetadataKey::LayoverDuration->value],
                );
            }
        }

        usort(
            $events,
            fn (array $left, array $right): int => strcmp($left['start'], $right['start']),
        );

        return [
            'trip' => $this->extractTripSummary($lines, $events, $tripIds),
            'calendar_events' => $events,
        ];
    }

    private function normaliseLines(string $text): array
    {
        $text = str_replace(["\x00", "\r\n", "\r"], ['', "\n", "\n"], $text);

        return array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            explode("\n", $text),
        ), static fn (string $line): bool => $line !== ''));
    }

    /**
     * @return array{month: int, year: int}
     */
    private function detectPlanningPeriod(array $lines): array
    {
        $monthMap = [
            'january' => 1,
            'february' => 2,
            'march' => 3,
            'april' => 4,
            'may' => 5,
            'june' => 6,
            'july' => 7,
            'august' => 8,
            'september' => 9,
            'october' => 10,
            'november' => 11,
            'december' => 12,
        ];

        foreach ($lines as $line) {
            if (! preg_match('/Planning period:\s*([A-Za-z]+)\s+(\d{4})/i', $line, $matches)) {
                continue;
            }

            $month = $monthMap[strtolower($matches[1])] ?? null;

            if ($month !== null) {
                return [
                    'month' => $month,
                    'year' => (int) $matches[2],
                ];
            }
        }

        return [
            'month' => (int) now()->month,
            'year' => (int) now()->year,
        ];
    }

    /**
     * @param  array{month: int, year: int}  $planningPeriod
     * @return list<array{date: Carbon, report_time: ?string, body: string}>
     */
    private function extractEntries(array $lines, array $planningPeriod): array
    {
        $entries = [];
        $currentDate = null;
        $currentMonth = $planningPeriod['month'];
        $currentYear = $planningPeriod['year'];
        $previousDay = null;
        $startsInPreviousMonth = isset($lines[5]) && preg_match('/^(\d{2})\s+[A-Z][a-z]{2}\b/', $lines[5], $matches) === 1
            && (int) $matches[1] > 20;

        if ($startsInPreviousMonth) {
            $seed = Carbon::create($planningPeriod['year'], $planningPeriod['month'], 1)->subMonth();
            $currentMonth = (int) $seed->month;
            $currentYear = (int) $seed->year;
        }

        foreach ($lines as $line) {
            if (preg_match('/^(\d{2})\s+([A-Z][a-z]{2})(\d{2}:\d{2})?\t(.+)$/', $line, $matches)) {
                $day = (int) $matches[1];

                if ($previousDay !== null && $day < $previousDay) {
                    $nextMonth = Carbon::create($currentYear, $currentMonth, 1)->addMonth();
                    $currentMonth = (int) $nextMonth->month;
                    $currentYear = (int) $nextMonth->year;
                }

                $currentDate = Carbon::create($currentYear, $currentMonth, $day)->startOfDay();
                $previousDay = $day;

                $entries[] = [
                    'date' => $currentDate->copy(),
                    'report_time' => $matches[3] !== '' ? $matches[3] : null,
                    'body' => trim($matches[4]),
                ];

                continue;
            }

            if ($currentDate === null || $this->isSummaryLine($line)) {
                continue;
            }

            $entries[] = [
                'date' => $currentDate->copy(),
                'report_time' => null,
                'body' => $line,
            ];
        }

        return $entries;
    }

    private function isSummaryLine(string $line): bool
    {
        return preg_match('/^(?:Published Roster|Planning period:|Rank:|Passports:|DateReport \(UTC\)|OFF Days|Block time|DH time|Created \d{2}[A-Za-z]{3}\d{4}|0[1-9][A-Za-z]{3}-\d{2}[A-Za-z]{3}\d{4})/i', $line) === 1
            || preg_match('/^\d{5,},\s+/', $line) === 1;
    }

    /**
     * @param  array{date: Carbon, report_time: ?string, body: string}  $entry
     * @return array{
     *     mode: 'complete'|'start'|'end',
     *     start_date: Carbon,
     *     end_date: Carbon,
     *     flight_number: string,
     *     origin: string,
     *     destination?: string,
     *     start_time?: string,
     *     end_time?: string,
     *     aircraft?: ?string,
     *     layover_duration?: ?string,
     *     deadhead: bool,
     *     trip_id?: ?string
     * }|null
     */
    private function parseFlightFragment(array $entry): ?array
    {
        $body = $this->compact($entry['body']);
        $body = preg_replace('/^\d{2}:\d{2}/', '', $body, 1) ?? $body;

        if (! preg_match('/^(AFO|FO|DH)([A-Z]{0,3}\d{2,4})([A-Z]{3})([A-Z]{3})?(.*)$/', $body, $matches)) {
            return null;
        }

        $flightNumber = $matches[2];
        $origin = $matches[3];
        $destination = $matches[4] !== '' ? $matches[4] : null;
        $tail = $matches[5];

        if ($destination === null && strlen($flightNumber) <= 2 && ! preg_match('/^[A-Z]{1,2}\d{3,4}$/', $flightNumber)) {
            return null;
        }

        preg_match_all('/\d{1,3}:\d{2}/', $tail, $timeMatches);
        $times = $timeMatches[0] ?? [];

        if ($times === []) {
            return null;
        }

        preg_match('/77[A-Z]/', $tail, $aircraftMatches);
        preg_match('/(?:^|\s)(\d{4,})\s*$/', $entry['body'], $tripIdMatches);

        $commercialDeadhead = $this->resolveCommercialDeadhead(
            $this->normalizeFlightNumber($flightNumber),
            $matches[1] === 'DH',
        );

        $fragment = [
            'start_date' => $entry['date'],
            'end_date' => $entry['date'],
            'flight_number' => $commercialDeadhead['flight_number'],
            'origin' => $origin,
            'deadhead' => $matches[1] === 'DH',
            'aircraft' => $aircraftMatches[0] ?? null,
            'trip_id' => $tripIdMatches[1] ?? null,
            'airline_name' => $commercialDeadhead['airline_name'],
        ];

        if ($destination !== null && count($times) >= 2) {
            $fragment['mode'] = 'complete';
            $fragment['destination'] = $destination;
            $fragment['start_time'] = $times[0];
            $fragment['end_time'] = $times[1];
            $fragment['layover_duration'] = $times[2] ?? null;

            return $fragment;
        }

        if ($destination === null && count($times) >= 2) {
            $fragment['mode'] = 'end';
            $fragment['destination'] = $origin;
            $fragment['end_time'] = $times[0];
            $fragment['layover_duration'] = $times[1] ?? null;

            return $fragment;
        }

        $fragment['mode'] = 'start';
        $fragment['start_time'] = $times[0];

        return $fragment;
    }

    /**
     * @param array{
     *     mode: 'complete'|'start'|'end',
     *     start_date: Carbon,
     *     end_date: Carbon,
     *     flight_number: string,
     *     origin: string,
     *     destination?: string,
     *     start_time?: string,
     *     end_time?: string,
     *     aircraft?: ?string,
     *     layover_duration?: ?string,
     *     deadhead: bool,
     *     trip_id?: ?string
     * } $fragment
     * @param array{
     *     start_date: Carbon,
     *     flight_number: string,
     *     origin: string,
     *     start_time: string,
     *     aircraft?: ?string,
     *     deadhead: bool,
     *     trip_id?: ?string
     * }|null $pendingFlight
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>|null, 2: list<string>}
     */
    private function consumeFlightFragment(array $fragment, ?array $pendingFlight): array
    {
        $events = [];
        $tripIds = [];

        if (! empty($fragment['trip_id'])) {
            $tripIds[] = $fragment['trip_id'];
        }

        if ($fragment['mode'] === 'start') {
            return [$events, [
                'start_date' => $fragment['start_date'],
                'flight_number' => $fragment['flight_number'],
                'origin' => $fragment['origin'],
                'start_time' => $fragment['start_time'],
                'aircraft' => $fragment['aircraft'] ?? null,
                'deadhead' => $fragment['deadhead'],
                'trip_id' => $fragment['trip_id'] ?? null,
                'airline_name' => $fragment['airline_name'] ?? null,
            ], $tripIds];
        }

        if ($fragment['mode'] === 'end' && $pendingFlight !== null && $pendingFlight['flight_number'] === $fragment['flight_number']) {
            $flightEvent = $this->buildFlightEvent(
                startDate: $pendingFlight['start_date'],
                startTime: $pendingFlight['start_time'],
                endDate: $fragment['end_date'],
                endTime: $fragment['end_time'],
                flightNumber: $fragment['flight_number'],
                origin: $pendingFlight['origin'],
                destination: $fragment['destination'],
                deadhead: $pendingFlight['deadhead'],
                aircraft: $fragment['aircraft'] ?? $pendingFlight['aircraft'] ?? null,
                tripId: $pendingFlight['trip_id'] ?? $fragment['trip_id'] ?? null,
                airlineName: $pendingFlight['airline_name'] ?? $fragment['airline_name'] ?? null,
            );

            $events[] = $flightEvent;

            if (! empty($fragment['layover_duration'])) {
                $events[] = $this->buildLayoverEvent(
                    $fragment['destination'],
                    Carbon::parse($flightEvent['end']),
                    $fragment['layover_duration'],
                );
            }

            return [$events, null, $tripIds];
        }

        if ($fragment['mode'] === 'complete') {
            $flightEvent = $this->buildFlightEvent(
                startDate: $fragment['start_date'],
                startTime: $fragment['start_time'],
                endDate: $fragment['end_date'],
                endTime: $fragment['end_time'],
                flightNumber: $fragment['flight_number'],
                origin: $fragment['origin'],
                destination: $fragment['destination'],
                deadhead: $fragment['deadhead'],
                aircraft: $fragment['aircraft'] ?? null,
                tripId: $fragment['trip_id'] ?? null,
                airlineName: $fragment['airline_name'] ?? null,
            );

            $events[] = $flightEvent;

            if (! empty($fragment['layover_duration'])) {
                $events[] = $this->buildLayoverEvent(
                    $fragment['destination'],
                    Carbon::parse($flightEvent['end']),
                    $fragment['layover_duration'],
                );
            }

            return [$events, null, $tripIds];
        }

        return [$events, null, $tripIds];
    }

    /**
     * @param  array{date: Carbon, report_time: ?string, body: string}  $entry
     */
    private function parseDutyEvent(array $entry): ?array
    {
        $body = $this->compact($entry['body']);

        if (! preg_match('/^(?:AFO|FO)?(R\d+)([A-Z]{3})(.*)$/', $body, $matches)) {
            return null;
        }

        preg_match_all('/\d{2}:\d{2}/', $matches[3], $timeMatches);
        $times = $timeMatches[0] ?? [];

        if (count($times) < 2) {
            return null;
        }

        $start = $this->applyTime($entry['date'], $times[0]);
        $end = $this->applyTime($entry['date'], $times[1]);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $this->calendarEvent(
            type: 'duty',
            title: "Duty {$matches[2]}",
            start: $start,
            end: $end,
            metadata: array_filter([
                'station' => $matches[2],
                'activity_code' => $matches[1],
                'layover_duration' => $times[2] ?? null,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        );
    }

    private function buildFlightEvent(
        Carbon $startDate,
        string $startTime,
        Carbon $endDate,
        string $endTime,
        string $flightNumber,
        string $origin,
        string $destination,
        bool $deadhead,
        ?string $aircraft,
        ?string $tripId,
        ?string $airlineName,
    ): array {
        $start = $this->applyTime($startDate, $startTime);
        $end = $this->applyTime($endDate, $endTime);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $this->calendarEvent(
            type: 'flight',
            title: trim("{$flightNumber} {$origin}-{$destination}"),
            start: $start,
            end: $end,
            metadata: array_filter([
                'flight_number' => $flightNumber,
                'origin' => $origin,
                'destination' => $destination,
                'aircraft' => $aircraft,
                'deadhead' => $deadhead,
                'trip_id' => $tripId,
                'airline_name' => $airlineName,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        );
    }

    /**
     * @return array{flight_number: string, airline_name: ?string}
     */
    private function resolveCommercialDeadhead(string $flightNumber, bool $isDeadhead): array
    {
        if (! $isDeadhead || preg_match('/^([A-Z0-9]{2})\s?(\d+)$/', $flightNumber, $matches) !== 1) {
            return [
                'flight_number' => $flightNumber,
                'airline_name' => null,
            ];
        }

        $airlineName = $this->airlineCodeLookup->airlineNameForIataCode($matches[1]);

        if ($airlineName === null) {
            return [
                'flight_number' => $flightNumber,
                'airline_name' => null,
            ];
        }

        return [
            'flight_number' => "{$matches[1]} {$matches[2]}",
            'airline_name' => $airlineName,
        ];
    }

    private function buildLayoverEvent(?string $station, Carbon $start, string $duration): array
    {
        $end = $start->copy();
        [$hours, $minutes] = array_map('intval', explode(':', $duration));
        $end->addHours($hours)->addMinutes($minutes);

        return $this->calendarEvent(
            type: 'layover',
            title: 'Layover '.($station ?? 'Unknown'),
            start: $start,
            end: $end,
            metadata: array_filter([
                'station' => $station,
                'layover_duration' => $duration,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        );
    }

    private function extractTripSummary(array $lines, array $events, array $tripIds): array
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

        if (preg_match('/Rank:\s*([A-Z]{2})\b/i', $fullText, $matches)) {
            $summary['position'] = strtoupper($matches[1]);
        }

        if (preg_match('/Base:\s*([A-Z]{3})\b/i', $fullText, $matches)) {
            $summary['base'] = strtoupper($matches[1]);
        }

        if (preg_match('/Block time\s+(\d{1,3}:\d{2})\b/i', $fullText, $matches)) {
            $summary['block_time'] = $matches[1];
        }

        if (preg_match('/\b(\d{2}[A-Za-z]{3}-\d{2}[A-Za-z]{3}\d{4})\b/', $fullText, $matches)) {
            $summary['roster_range'] = $matches[1];
        }

        $stations = [];

        foreach ($events as $event) {
            if (($event['type'] ?? null) !== 'layover') {
                continue;
            }

            $station = $event['metadata']['station'] ?? null;

            if ($station !== null && $station !== '' && $station !== $summary['base']) {
                $stations[] = $station;
            }
        }

        $summary['layovers'] = array_values(array_unique($stations));

        if (count(array_unique($tripIds)) === 1) {
            $summary['trip_number'] = $tripIds[0];
        }

        return $summary;
    }

    private function applyTime(Carbon $date, string $time): Carbon
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $date->copy()->setTime($hours, $minutes);
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

    private function normalizeFlightNumber(string $flightNumber): string
    {
        return preg_match('/^\d+$/', $flightNumber) === 1
            ? 'CKS '.$flightNumber
            : $flightNumber;
    }

    private function compact(string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', $value) ?? '');
    }
}
