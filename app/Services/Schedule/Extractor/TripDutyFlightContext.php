<?php

namespace App\Services\Schedule\Extractor;

use App\Enums\ScheduleEventType;
use Illuminate\Support\Carbon;

class TripDutyFlightContext
{
    /**
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    public function attachDutyFlightContext(array $events): array
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
            if (! ScheduleEventType::fromEvent($event)->isFlightLike()) {
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
     * @param  list<string>  $lines
     * @return array{leg_local_start?: string, leg_local_end?: string, duty_local_start?: string, duty_local_end?: string}
     */
    public function extractFlightLocalTimes(array $lines): array
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
    /** @param list<string> $lines */
    public function extractDutyStationFromLines(array $lines): ?string
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
}
