<?php

namespace App\Services\FlightPlan\Extractor;

use App\Exceptions\FlightPlanDataConflictException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class FlightScheduleExtractor
{
    /**
     * @return array{
     *     data: array{etd_utc: ?string, eta_utc: ?string, block_duration: ?string, report_time_utc: ?string, duty_end_utc: ?string, slot_times_utc: list<string>},
     *     source_fragments: array<string, string|list<array{direction: string, airport: string, time: string}>>
     * }
     */
    public function extract(string $text, ?string $flightDate): array
    {
        $etdMatches = [];
        preg_match('/(?:SH)?ETD\s+(\d{2})[.:](\d{2})Z\/(\d{1,2})\b/i', $text, $etdMatches);
        $etaMatches = [];
        preg_match('/\bETA\s+(\d{2})[.:](\d{2})Z(?:\/(\d{1,2}))?/i', $text, $etaMatches);

        $etd = $this->utcInstant($flightDate, $etdMatches, dayIndex: 3);
        $eta = $this->utcInstant($flightDate, $etaMatches, dayIndex: 3, after: $etd);
        $this->corroborateFplDepartureTime($text, $etd);
        [$slotTimes, $slotEvidence] = $this->slotTimes($text, $flightDate, $etd);

        return [
            'data' => [
                'etd_utc' => $etd?->toIso8601String(),
                'eta_utc' => $eta?->toIso8601String(),
                'block_duration' => null,
                'report_time_utc' => null,
                'duty_end_utc' => null,
                'slot_times_utc' => array_map(
                    static fn (CarbonImmutable $time): string => $time->toIso8601String(),
                    $slotTimes,
                ),
            ],
            'source_fragments' => array_filter([
                'schedule' => isset($etdMatches[0], $etaMatches[0])
                    ? Str::squish($etdMatches[0].' '.$etaMatches[0])
                    : null,
                'slot_times' => $slotEvidence,
            ], static fn (string|array|null $value): bool => $value !== null && $value !== []),
        ];
    }

    /**
     * @param  array<int, string>  $matches
     */
    private function utcInstant(
        ?string $flightDate,
        array $matches,
        int $dayIndex,
        ?CarbonImmutable $after = null,
    ): ?CarbonImmutable {
        if ($flightDate === null || ! isset($matches[1], $matches[2])) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $flightDate, 'UTC');

        if (isset($matches[$dayIndex]) && $matches[$dayIndex] !== '') {
            $day = (int) $matches[$dayIndex];

            if ($day < 1 || $day > 31) {
                return null;
            }

            if ($day !== $date->day) {
                $candidate = $date->day($day);

                if ($candidate->day !== $day) {
                    return null;
                }

                $date = $candidate;
            }
        }

        $instant = $date->setTime($hour, $minute);

        if ($after !== null && $instant->lessThan($after)) {
            $instant = $instant->addDay();
        }

        return $instant;
    }

    private function corroborateFplDepartureTime(string $text, ?CarbonImmutable $etd): void
    {
        if ($etd === null) {
            return;
        }

        $matches = [];

        if (preg_match('/\(FPL-.*?-([A-Z]{4})(\d{4})\s*-/s', $text, $matches) !== 1) {
            return;
        }

        if ($matches[2] !== $etd->format('Hi')) {
            throw FlightPlanDataConflictException::forField('scheduled departure time');
        }
    }

    /**
     * @return array{list<CarbonImmutable>, list<array{direction: string, airport: string, time: string}>}
     */
    private function slotTimes(string $text, ?string $flightDate, ?CarbonImmutable $etd): array
    {
        if ($flightDate === null) {
            return [[], []];
        }

        $sectionMatches = [];

        if (preg_match('/APPROVED\s+SLOT\s+TIMES?:\s*(.+?)(?=\*{3,}|PLANNED\s+TO|\R|$)/is', $text, $sectionMatches) !== 1) {
            return [[], []];
        }

        $matches = [];
        preg_match_all('/\b(ARR|DEP)\s+([A-Z]{4})\s+@\s*(\d{2})(\d{2})Z\b/i', $sectionMatches[1], $matches, PREG_SET_ORDER);
        $times = [];
        $evidence = [];

        foreach ($matches as $match) {
            $time = $this->utcInstant($flightDate, [1 => $match[3], 2 => $match[4]], dayIndex: 5, after: $etd);

            if ($time === null) {
                continue;
            }

            $times[] = $time;
            $evidence[] = [
                'direction' => Str::upper($match[1]),
                'airport' => Str::upper($match[2]),
                'time' => $match[3].$match[4].'Z',
            ];
        }

        return [$times, $evidence];
    }
}
