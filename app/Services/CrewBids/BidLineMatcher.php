<?php

namespace App\Services\CrewBids;

class BidLineMatcher
{
    /** @param array<string, mixed> $line
     * @param  array<string, mixed>  $preferences
     * @return array<string, mixed>
     */
    public function present(array $line, string $period, array $preferences): array
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

        return $line + ['days_off' => $unknown ? null : $off, 'blocks' => $unknown ? null : $blocks, 'warnings' => $warnings, 'matches' => $conflicts === [] && ! $cityMismatch, 'credit_label' => $line['credit'] === null ? 'Not available' : sprintf('%d:%02d', intdiv($line['credit'], 60), $line['credit'] % 60), 'month' => $month];
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
