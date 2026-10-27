<?php

namespace App\Services\CrewBids;

use Carbon\CarbonImmutable;

class BidLineMatcher
{
    /** @param array<string, mixed> $line
     * @param  array<string, mixed>  $preferences
     * @param  array<string, array<string, mixed>>  $trips
     * @return array<string, mixed>
     */
    public function present(array $line, string $period, array $preferences, array $trips = []): array
    {
        $month = array_filter($line['calendar'], fn (string $date): bool => str_starts_with($date, $period.'-'), ARRAY_FILTER_USE_KEY);
        $unknown = count(array_filter($month, fn (array $day): bool => $day['status'] === 'unknown')) > 0;
        $off = count(array_filter($month, fn (array $day): bool => $day['status'] === 'off'));
        $blocks = 0;
        $previous = 'off';
        foreach ($month as $day) {
            if ($day['status'] === 'occupied' && $previous !== 'occupied') {
                $blocks++;
            }
            $previous = $day['status'];
        }
        $conflicts = [];
        foreach ($preferences['days_off'] ?? [] as $date) {
            if (($line['calendar'][$date]['status'] ?? '') === 'occupied') {
                $conflicts[] = $date;
            }
        }
        $cityMismatch = ($preferences['start_city'] ?? '') !== '' && $line['start_city'] !== $preferences['start_city'];
        $warnings = [];
        if ($conflicts !== []) {
            $warnings[] = 'Conflicts with requested UTC days off: '.implode(', ', $conflicts);
        }
        if ($cityMismatch) {
            $warnings[] = 'Does not match the required start city.';
        }
        if ($unknown) {
            $warnings[] = 'Dates incomplete; conflicts unknown';
        }

        $linkedTrips = [];
        $carryDates = [];
        $missingTrips = false;
        $nextMonth = CarbonImmutable::parse($period.'-01', 'UTC')->addMonth();
        foreach ($line['pairings'] as $pairing) {
            $id = $pairing['id'];
            if (isset($linkedTrips[$id])) {
                continue;
            }
            if (! isset($trips[$id])) {
                $missingTrips = true;
                $linkedTrips[$id] = ['id' => $id, 'available' => false];

                continue;
            }
            $trip = $trips[$id];
            $linkedTrips[$id] = $this->presentTrip($trip);
            if ($trip['start_date'] < $nextMonth->format('Y-m-d')) {
                for ($date = $nextMonth; $date->format('Y-m-d') <= $trip['end_date']; $date = $date->addDay()) {
                    $carryDates[$date->format('Y-m-d')] = true;
                }
            }
        }
        $carryDays = $line['pairings'] !== [] && ! $missingTrips ? count($carryDates) : ($line['carry_in'] ?? null);
        $creditLabel = $line['credit'] === null ? 'Not available' : sprintf('%d:%02d', intdiv($line['credit'], 60), $line['credit'] % 60);
        $matchDetails = [];
        foreach ($preferences['goals'] ?? [] as $goal) {
            $detail = match ($goal) {
                'credit' => 'Maximize credit: '.$creditLabel,
                'days_off' => 'Maximize days off: '.($unknown ? 'Unknown' : $off),
                'blocks' => 'Fewer work blocks: '.($unknown ? 'Unknown' : $blocks),
                default => null,
            };
            if ($detail !== null) {
                $matchDetails[] = $detail;
            }
        }
        $filterDetails = [];
        if (($preferences['days_off'] ?? []) !== []) {
            $filterDetails[] = 'Requested UTC days off: '.($conflicts !== [] ? 'Conflict on '.implode(', ', $conflicts) : ($unknown ? 'Dates incomplete; conflicts unknown' : 'All clear'));
        }
        if (($preferences['start_city'] ?? '') !== '') {
            $filterDetails[] = 'Required start city '.$preferences['start_city'].': '.($cityMismatch ? 'Does not match' : 'Matches');
        }

        return $line + ['days_off' => $unknown ? null : $off, 'blocks' => $unknown ? null : $blocks, 'warnings' => $warnings, 'matches' => $conflicts === [] && ! $cityMismatch, 'credit_label' => $creditLabel, 'month' => $month, 'carry_days' => $carryDays, 'trip_details' => array_values($linkedTrips), 'match_details' => $matchDetails, 'filter_details' => $filterDetails];
    }

    /**
     * @param  array<string, mixed>  $trip
     * @return array<string, mixed>
     */
    private function presentTrip(array $trip): array
    {
        $route = [];
        $layovers = [];
        $layoverDetails = [];
        $rests = [];
        foreach ($trip['legs'] as $index => &$leg) {
            $departure = CarbonImmutable::parse($leg['date'].' '.$leg['departure'], 'UTC');
            $arrival = CarbonImmutable::parse($leg['date'].' '.$leg['arrival'], 'UTC');
            if ($leg['arrival'] < $leg['departure']) {
                $arrival = $arrival->addDay();
            }
            $leg['arrival_date'] = $arrival->format('Y-m-d');
            $elapsedMinutes = intdiv($arrival->getTimestamp() - $departure->getTimestamp(), 60);
            $leg['elapsed_label'] = $this->durationLabel($elapsedMinutes);
            $leg['timeline_type'] = $leg['deadhead'] ? 'deadhead' : (str_starts_with($leg['flight'] ?? '', 'GT ') ? 'ground' : 'flying');
            $leg['timeline_label'] = match ($leg['timeline_type']) {
                'deadhead' => 'Deadhead',
                'ground' => 'Ground transfer',
                default => 'Flying',
            };
            $leg['block_basis'] = ($leg['block'] ?? null) !== null ? 'source' : 'unavailable';
            if ($leg['block_basis'] === 'unavailable' && $leg['timeline_type'] === 'flying') {
                $leg['block'] = sprintf('%d:%02d', intdiv($elapsedMinutes, 60), $elapsedMinutes % 60);
                $leg['block_basis'] = 'calculated';
            }
            $leg['connection_status'] = null;
            $leg['layover_label'] = null;
            $leg['connection_until'] = null;
            $nextLeg = $trip['legs'][$index + 1] ?? null;
            if ($nextLeg !== null) {
                $nextDeparture = CarbonImmutable::parse($nextLeg['date'].' '.$nextLeg['departure'], 'UTC');
                $leg['connection_until'] = $nextDeparture->format('Y-m-d H:i');
                $gapMinutes = intdiv($nextDeparture->getTimestamp() - $arrival->getTimestamp(), 60);
                if ($gapMinutes < 0) {
                    $leg['connection_status'] = 'overlap';
                } elseif ($leg['destination'] !== $nextLeg['origin']) {
                    $leg['connection_status'] = 'airport_change';
                } else {
                    $leg['connection_status'] = 'layover';
                    $leg['layover_label'] = $this->durationLabel($gapMinutes);
                    $layovers[] = $leg['destination'];
                    $layoverDetails[] = ['airport' => $leg['destination'], 'duration' => $leg['layover_label']];
                }
            }
            if ($route === [] || $route[array_key_last($route)] !== $leg['origin']) {
                $route[] = $leg['origin'];
            }
            $route[] = $leg['destination'];
            if (($leg['rest_after'] ?? null) !== null) {
                $layovers[] = $leg['destination'];
                $rests[] = $leg['rest_after'];
            }
        }
        unset($leg);
        usort($rests, static function (string $left, string $right): int {
            [$leftHours, $leftMinutes] = array_map('intval', explode(':', $left));
            [$rightHours, $rightMinutes] = array_map('intval', explode(':', $right));

            return ($leftHours * 60 + $leftMinutes) <=> ($rightHours * 60 + $rightMinutes);
        });

        return $trip + ['available' => true, 'route_label' => implode('–', $route), 'layover_label' => $layovers === [] ? 'No connected layovers recorded' : implode(', ', array_unique($layovers)), 'layovers' => $layoverDetails, 'minimum_rest' => $rests[0] ?? null];
    }

    private function durationLabel(int $minutes): string
    {
        return sprintf('%dh %dm', intdiv($minutes, 60), $minutes % 60);
    }

    /** @param list<array<string, mixed>> $lines
     * @param  list<string>  $goals
     * @return list<array<string, mixed>>
     */
    public function sort(array $lines, array $goals, string $sort): array
    {
        $keys = match ($sort) {
            'credit' => ['credit'], 'days_off' => ['days_off'], 'id' => [], default => $goals,
        };
        usort($lines, function (array $left, array $right) use ($keys): int {
            foreach ($keys as $key) {
                $a = $left[$key] ?? null;
                $b = $right[$key] ?? null;
                if ($a === null || $b === null) {
                    $comparison = ($a === null ? 1 : 0) <=> ($b === null ? 1 : 0);
                } else {
                    $comparison = $key === 'blocks' ? $a <=> $b : $b <=> $a;
                }
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return strnatcmp($left['id'], $right['id']);
        });

        return $lines;
    }
}
