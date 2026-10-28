<?php

namespace App\Services\CrewBids;

use Carbon\CarbonImmutable;

class BidLineMatcher
{
    /** @param array<string, mixed> $line
     * @param  array<string, mixed>  $preferences
     * @param  array<string, array<string, mixed>>  $trips
     * @param  array{city?: string, extended?: bool, avoid_short?: bool}  $layoverFilters
     * @return array<string, mixed>
     */
    public function present(array $line, string $period, array $preferences, array $trips = [], array $layoverFilters = []): array
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
            $linkedTrips[$id] = $this->presentTrip($trip, $layoverFilters);
            if ($trip['start_date'] < $nextMonth->format('Y-m-d')) {
                for ($date = $nextMonth; $date->format('Y-m-d') <= $trip['end_date']; $date = $date->addDay()) {
                    $carryDates[$date->format('Y-m-d')] = true;
                }
            }
        }
        $line['quick_flags'] = $this->quickFlags(array_values($linkedTrips));
        $line += $this->layoverMatch(array_values($linkedTrips), $layoverFilters);
        $carryDays = $line['pairings'] !== [] && ! $missingTrips ? count($carryDates) : ($line['carry_in'] ?? null);
        $creditLabel = array_key_exists('credit_seconds', $line) ? $this->secondsLabel($line['credit_seconds']) : $this->timeLabel($line['credit']);
        $blockLabel = $this->timeLabel($line['block'] ?? null);
        $line['pch_label'] = $this->secondsLabel($line['pch_seconds'] ?? null);
        $line['carry_deadhead_credit_label'] = $this->secondsLabel($line['carry_deadhead_credit_seconds'] ?? null);
        $matchDetails = [];
        foreach ($preferences['goals'] ?? [] as $goal) {
            $detail = match ($goal) {
                'credit' => $line['credit'] === null ? null : 'Maximize credit: '.$creditLabel,
                'days_off' => $unknown ? null : 'Maximize days off: '.$off,
                'blocks' => $unknown ? null : 'Fewer work blocks: '.$blocks,
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

        return $line + ['days_off' => $unknown ? null : $off, 'blocks' => $unknown ? null : $blocks, 'warnings' => $warnings, 'matches' => $conflicts === [] && ! $cityMismatch, 'credit_label' => $creditLabel, 'block_label' => $blockLabel, 'base_credit_label' => $this->timeLabel($line['base_credit'] ?? $line['credit']), 'deadhead_credit_label' => $this->secondsLabel($line['deadhead_credit_seconds'] ?? null), 'carry_premium_label' => $this->secondsLabel($line['carry_premium_seconds'] ?? null), 'carry_flying_label' => $this->secondsLabel($line['carry_flying_seconds'] ?? null), 'source_credit_label' => $this->timeLabel(array_key_exists('source_credit', $line) ? $line['source_credit'] : $line['credit']), 'source_block_label' => $this->timeLabel(array_key_exists('source_block', $line) ? $line['source_block'] : ($line['block'] ?? null)), 'month' => $month, 'carry_days' => $carryDays, 'trip_details' => array_values($linkedTrips), 'match_details' => $matchDetails, 'filter_details' => $filterDetails];
    }

    /**
     * @param  list<array<string, mixed>>  $trips
     * @return list<array{type: string, label: string, description: string}>
     */
    private function quickFlags(array $trips): array
    {
        $flags = [];
        $endpoints = [];
        $completeEndpoints = $trips !== [];
        $hasDeadhead = false;
        foreach ($trips as $trip) {
            if (! $trip['available']) {
                $completeEndpoints = false;

                continue;
            }
            $origin = $trip['start_city'] ?? null;
            $destination = $trip['end_city'] ?? null;
            if (! is_string($origin) || ! is_string($destination) || ! preg_match('/\A[A-Z]{3}\z/', $origin) || ! preg_match('/\A[A-Z]{3}\z/', $destination)) {
                $completeEndpoints = false;
            } else {
                $endpoints[$origin.'–'.$destination] = [$origin, $destination];
            }
            foreach ($trip['legs'] as $leg) {
                if (($leg['deadhead'] ?? null) === true) {
                    $hasDeadhead = true;
                }
            }
        }
        if ($completeEndpoints && count($endpoints) === 1) {
            [$origin, $destination] = array_values($endpoints)[0];
            $flags[] = $origin === $destination
                ? ['type' => 'return', 'label' => $origin, 'description' => 'All linked trips start and end at '.$origin.'.']
                : ['type' => 'route', 'label' => $origin.'→'.$destination, 'description' => 'All linked trips start at '.$origin.' and end at '.$destination.'.'];
        } elseif ($completeEndpoints) {
            ksort($endpoints);
            $flags[] = ['type' => 'mixed', 'label' => 'Mixed ends', 'description' => 'Trip start/end airports vary: '.implode(', ', array_keys($endpoints)).'.'];
        }
        if ($hasDeadhead) {
            $flags[] = ['type' => 'deadhead', 'label' => 'DH', 'description' => 'Includes deadhead legs in the available trip details.'];
        }

        return $flags;
    }

    /**
     * @param  array<string, mixed>  $trip
     * @param  array{city?: string, extended?: bool, avoid_short?: bool}  $layoverFilters
     * @return array<string, mixed>
     */
    private function presentTrip(array $trip, array $layoverFilters = []): array
    {
        $route = [];
        $layovers = [];
        $layoverDetails = [];
        $layoverGroups = ['short' => [], 'longer' => []];
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
            $leg['deadhead_credit_label'] = $leg['deadhead'] ? $this->secondsLabel(intdiv($arrival->getTimestamp() - $departure->getTimestamp(), 2)) : null;
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
            $leg['block_duration_label'] = null;
            if (($leg['block'] ?? null) !== null) {
                [$blockHours, $blockMinutes] = array_map('intval', explode(':', $leg['block']));
                $leg['block_duration_label'] = $this->durationLabel($blockHours * 60 + $blockMinutes);
            }
            $leg['connection_status'] = null;
            $leg['layover_label'] = null;
            $leg['layover_highlight'] = null;
            $leg['connection_until'] = null;
            $leg['layover_matches_filter'] = false;
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
                    $leg['layover_highlight'] = match (true) {
                        $gapMinutes < 600 => 'short',
                        $gapMinutes >= 1440 => 'extended',
                        default => null,
                    };
                    $leg['layover_matches_filter'] = $layoverFilters !== []
                        && (($layoverFilters['city'] ?? '') === '' || $layoverFilters['city'] === $leg['destination'])
                        && (! ($layoverFilters['extended'] ?? false) || $leg['layover_highlight'] === 'extended')
                        && (! ($layoverFilters['avoid_short'] ?? false) || $leg['layover_highlight'] !== 'short');
                    $layovers[] = $leg['destination'];
                    $layoverDetails[] = ['airport' => $leg['destination'], 'duration' => $leg['layover_label']];
                    $layoverGroups[$gapMinutes < 180 ? 'short' : 'longer'][] = [
                        'airport' => $leg['destination'], 'duration' => $leg['layover_label'],
                        'start' => $arrival->format('Y-m-d H:i'), 'end' => $leg['connection_until'],
                        'source_rest' => $leg['rest_after'] ?? null,
                        'highlight' => $leg['layover_highlight'],
                    ];
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

        return $trip + ['available' => true, 'route_label' => implode('–', $route), 'layover_label' => $layovers === [] ? 'No connected layovers recorded' : implode(', ', array_unique($layovers)), 'layovers' => $layoverDetails, 'layover_groups' => $layoverGroups, 'minimum_rest' => $rests[0] ?? null, 'credit_time_label' => $this->timeLabel($trip['credit_minutes'] ?? null), 'block_time_label' => $this->timeLabel($trip['block_minutes'] ?? null)];
    }

    /**
     * @param  list<array<string, mixed>>  $trips
     * @param  array{city?: string, extended?: bool, avoid_short?: bool}  $filters
     * @return array{layover_matches: bool, layover_airports: list<string>}
     */
    private function layoverMatch(array $trips, array $filters): array
    {
        $airports = [];
        $hasMatch = false;
        $hasShort = false;
        $complete = $trips !== [];
        foreach ($trips as $trip) {
            if (! $trip['available'] || $trip['legs'] === []) {
                $complete = false;

                continue;
            }
            foreach ($trip['legs'] as $leg) {
                if (in_array($leg['connection_status'], ['overlap', 'airport_change'], true)) {
                    $complete = false;
                }
                if ($leg['connection_status'] === 'layover') {
                    $airports[] = $leg['destination'];
                    $hasShort = $hasShort || $leg['layover_highlight'] === 'short';
                    $hasMatch = $hasMatch || $leg['layover_matches_filter'];
                }
            }
        }

        $requiresMatch = ($filters['city'] ?? '') !== '' || ($filters['extended'] ?? false);

        return [
            'layover_matches' => (! $requiresMatch || $hasMatch)
                && (! ($filters['avoid_short'] ?? false) || ($complete && ! $hasShort)),
            'layover_airports' => array_values(array_unique($airports)),
        ];
    }

    private function timeLabel(?int $minutes): string
    {
        return $minutes === null ? 'Not available' : sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function secondsLabel(?int $seconds): string
    {
        if ($seconds === null) {
            return 'Not available';
        }

        $label = sprintf('%d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));

        return $seconds % 60 === 0 ? $label : $label.sprintf(':%02d', $seconds % 60);
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
            'credit' => ['credit'], 'block' => ['block'], 'days_off' => ['days_off'], 'id' => [], default => $goals,
        };
        usort($lines, function (array $left, array $right) use ($keys): int {
            foreach ($keys as $key) {
                $a = $left[$key] ?? null;
                $b = $right[$key] ?? null;
                if ($key === 'credit') {
                    $a = $left['credit_seconds'] ?? ($a === null ? null : $a * 60);
                    $b = $right['credit_seconds'] ?? ($b === null ? null : $b * 60);
                }
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
