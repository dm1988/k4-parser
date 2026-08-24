<?php

namespace App\Services\FlightPlan\Extractor;

use App\Exceptions\FlightPlanDataConflictException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class FlightScheduleExtractor
{
    /**
     * @return array{
     *     data: array{etd_utc: ?string, eta_utc: ?string, block_duration: ?string, report_time_utc: ?string, duty_end_utc: ?string, slots: list<array{direction: string, airport: string, instant_utc: string, source_time: string}>, slot_times_utc: list<string>},
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
        [$slots, $slotEvidence, $slotSourceText] = $this->slotTimes($text, $flightDate, $etd);

        return [
            'data' => [
                'etd_utc' => $etd?->toIso8601String(),
                'eta_utc' => $eta?->toIso8601String(),
                'block_duration' => null,
                'report_time_utc' => null,
                'duty_end_utc' => null,
                'slot_source_text' => $slotSourceText,
                'slots' => array_map(
                    static fn (array $slot): array => [
                        'direction' => $slot['direction'],
                        'airport' => $slot['airport'],
                        'instant_utc' => $slot['instant']->toIso8601String(),
                        'source_time' => $slot['source_time'],
                        'tolerance_minutes' => $slot['tolerance_minutes'],
                    ],
                    $slots,
                ),
                'slot_times_utc' => array_map(
                    static fn (array $slot): string => $slot['instant']->toIso8601String(),
                    $slots,
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
     * @return array{list<array{direction: 'arrival'|'departure', airport: string, instant: CarbonImmutable, source_time: string, tolerance_minutes: ?int, source_order: int}>, list<array{direction: string, airport: string, time: string}>, ?string}
     */
    private function slotTimes(string $text, ?string $flightDate, ?CarbonImmutable $etd): array
    {
        if ($flightDate === null) {
            return [[], [], null];
        }

        $sectionMatches = [];

        if (preg_match('/APPROVED\s+SLOT\s+TIMES?:\s*(.+?)(?=\bAPPROVED\s+SLOT\s+TIMES?\b|\bETOPS\b|\bMEL\s*\/\s*CDL\b|\bPLANNED\s+TO\b|\R\s*\*{3,}\s*(?:\R|$)|\z)/is', $text, $sectionMatches) !== 1) {
            return [[], [], null];
        }

        $matches = [];
        preg_match_all(
            '/(?:(?<direction_before>ARR|DEP)\s+(?<airport_before>[A-Z]{4})\s+@\s*(?<time_before>\d{4})Z(?:\s*\(\s*(?:\+\/-|\+-)\s*(?<tolerance_before>\d+)\s*MIN\s*\))?)|(?:-\s*(?<airport_after>[A-Z]{4}):\s*(?<time_after>\d{4})Z(?:\s*(?:\+\/-|\+-)\s*(?<tolerance_after>\d+)\s*MIN)?(?:\s+(?<direction_after>ARR|DEP))?)/i',
            $sectionMatches[1],
            $matches,
            PREG_SET_ORDER,
        );
        $slots = [];
        $evidence = [];

        foreach ($matches as $sourceOrder => $match) {
            $sourceDigits = ($match['time_before'] ?? '') !== '' ? $match['time_before'] : ($match['time_after'] ?? '');
            $direction = Str::upper(($match['direction_before'] ?? '') !== '' ? $match['direction_before'] : ($match['direction_after'] ?? ''));

            if ($direction === '' && preg_match('/\bARRIVAL\b/i', $sectionMatches[1]) === 1) {
                $direction = 'ARR';
            }

            if ($direction === '') {
                continue;
            }

            $time = $this->utcInstant($flightDate, [1 => substr($sourceDigits, 0, 2), 2 => substr($sourceDigits, 2, 2)], dayIndex: 5, after: $etd);

            if ($time === null) {
                continue;
            }

            $airport = Str::upper(($match['airport_before'] ?? '') !== '' ? $match['airport_before'] : ($match['airport_after'] ?? ''));
            $sourceTime = $sourceDigits.'Z';
            $tolerance = ($match['tolerance_before'] ?? '') !== '' ? $match['tolerance_before'] : ($match['tolerance_after'] ?? '');
            $slots[] = [
                'direction' => $direction === 'DEP' ? 'departure' : 'arrival',
                'airport' => $airport,
                'instant' => $time,
                'source_time' => $sourceTime,
                'tolerance_minutes' => $tolerance === '' ? null : (int) $tolerance,
                'source_order' => $sourceOrder,
            ];
            $evidence[] = [
                'direction' => $direction,
                'airport' => $airport,
                'time' => $sourceTime,
            ];
        }

        usort($slots, static fn (array $left, array $right): int => [
            $left['instant']->getTimestamp(),
            $left['source_order'],
        ] <=> [
            $right['instant']->getTimestamp(),
            $right['source_order'],
        ]);

        $slots = array_values(array_reduce($slots, static function (array $unique, array $slot): array {
            $key = implode('|', [
                $slot['direction'],
                $slot['airport'],
                $slot['instant']->toIso8601String(),
                $slot['source_time'],
                $slot['tolerance_minutes'] ?? '',
            ]);
            $unique[$key] ??= $slot;

            return $unique;
        }, []));

        $sourceText = preg_replace('/(?:\s*\*+\s*)+$/', '', $sectionMatches[1]) ?? $sectionMatches[1];

        return [$slots, $evidence, Str::squish('APPROVED SLOT TIMES: '.$sourceText)];
    }
}
