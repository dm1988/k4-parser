<?php

namespace App\Services\CrewBids;

use Carbon\CarbonImmutable;

class NormalizeCrewBidPay
{
    /** @return array<string, string> */
    public static function types(): array
    {
        return [
            'credit' => 'Awarded ordinary PCH', 'carry' => 'Transitioning duty: daily awarded / actual PCH',
            'flight' => 'Flight: block time, departure UTC day',
            'passenger_deadhead' => 'Company passenger deadhead: scheduled block',
            'reserve' => 'Reserve day: daily planned PCH', 'vacation' => 'Vacation day (4 PCH)',
            'training' => 'Training day (under 16 days)', 'taxi' => 'Taxi only: actual/planned block',
            'cbt' => 'Special tested CBT (1 PCH)', 'cq_cbt' => 'Completed CQ CBT examinations (+4 PCH)',
            'open_time' => 'Open Time flight on SDFDO: hours flown (+1.5×)',
            'random_test' => 'Random drug/alcohol test (+1 hour pay)',
            'maintenance' => 'Maintenance: hours or fraction ($25 per started hour)',
            'sim_instruction' => 'Non-Check-Airman simulator instruction: including brief/debrief (+$15/h)',
            'unsupported' => 'Other applicable pay: rule not supported yet',
        ];
    }

    /** @return list<string> */
    public static function extras(): array
    {
        return ['cq_cbt', 'open_time', 'random_test', 'maintenance', 'sim_instruction'];
    }

    public static function seconds(string $duration): ?int
    {
        if (! preg_match('/\A(\d{1,3}):([0-5]\d)(?::([0-5]\d))?\z/', $duration, $matches)) {
            return null;
        }

        return (int) $matches[1] * 3600 + (int) $matches[2] * 60 + (int) ($matches[3] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, array<string, mixed>>  $trips
     * @param  array<string, mixed>  $input
     * @return array{rows: list<array<string, mixed>>, missing: list<string>, assumptions: list<string>}
     */
    public function normalize(array $line, array $trips, array $input, CrewPayPeriod $period, CrewPayRates $rates, string $seat, string $longevity): array
    {
        $rows = [];
        $missing = [];
        $assumptions = [];
        $mode = $input['mode'] ?? 'source';
        if ($mode === 'source' && ($input['category'] ?? '') === 'reserve') {
            foreach ($period->dates() as $date) {
                $status = $line['calendar'][$date]['status'] ?? 'unknown';
                if ($status === 'unknown') {
                    $missing[] = 'Reserve dates are incomplete in the package.';
                } elseif ($status === 'occupied') {
                    $rows[] = ['type' => 'reserve', 'date' => $date, 'seconds' => 0,
                        'basis' => 'Q reserve line: published occupied date '.$date.' UTC; scheduled reserve minimum 4 PCH'];
                }
            }
            $assumptions[] = 'Q lines are reserve. Published occupied dates use the scheduled 4 PCH reserve-day minimum; additional assigned flying is an optional adjustment when known.';
        }
        if ($mode === 'source') {
            $covered = [];
            $seen = [];
            foreach (($input['category'] ?? '') === 'reserve' ? [] : ($line['pairings'] ?? []) as $pairing) {
                $id = $pairing['id'];
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $trip = $trips[$id] ?? [];
                $start = $trip['start_date'] ?? '';
                $end = $trip['end_date'] ?? '';
                if (! isset($trip['credit_minutes']) || ! is_int($trip['credit_minutes']) || $trip['credit_minutes'] < 0
                    || ! CarbonImmutable::hasFormat($start, 'Y-m-d') || ! CarbonImmutable::hasFormat($end, 'Y-m-d')
                    || $end < $start || ! $period->contains($start)) {
                    $missing[] = 'Trip '.$id.': complete credit and duty allocation needed; use dated private credit inputs.';

                    continue;
                }
                $crossesPeriod = ! $period->contains($end);
                $crossesRate = $rates->resolve($seat, $start, $longevity) !== $rates->resolve($seat, $end, $longevity);
                if ($crossesPeriod || $crossesRate) {
                    $allocated = $this->allocatedTrip($trip, $period, (string) $id);
                    if ($allocated === null) {
                        $missing[] = $crossesPeriod
                            ? 'Trip '.$id.': complete credit and duty allocation needed; use dated private credit inputs.'
                            : 'Trip '.$id.' spans a rate change; assign its credits to earning/departure UTC days.';

                        continue;
                    }
                    array_push($rows, ...$allocated);
                    if ($crossesPeriod) {
                        $assumptions[] = 'Carry-in scenario: the next-month bid avoids carry-in days, making the outgoing duties excess work. Their whole payment is 1.5 × credited hours at earning/departure-day rates; passenger deadhead first credits 50% of scheduled travel. This is additional to the originating month’s base and does not include the next month’s unrelated line pay.';
                    }
                } else {
                    $rows[] = ['type' => 'credit', 'date' => $start, 'seconds' => $trip['credit_minutes'] * 60, 'basis' => 'Trips '.$id.': '.$trip['credit_minutes'].' published pairing credit minutes; constant rate throughout trip'];
                }
                for ($date = CarbonImmutable::parse($start, 'UTC'); $date->format('Y-m-d') <= $end; $date = $date->addDay()) {
                    $covered[$date->format('Y-m-d')] = true;
                }
            }
            foreach (($input['category'] ?? '') === 'reserve' ? [] : $period->dates() as $date) {
                $status = $line['calendar'][$date]['status'] ?? 'unknown';
                if ($status === 'occupied' && ! isset($covered[$date])) {
                    if (isset($line['source_credit']) && is_int($line['source_credit']) && $line['source_credit'] >= 0
                        && in_array($date, $line['pay_included_occupied_dates'] ?? [], true)) {
                        $assumptions[] = $date.' UTC: user confirmed positioning/travel or another obligation is already included in published line Credit; no additional credit is added.';
                    } else {
                        $missing[] = 'Occupied dates outside linked trips need credit details.';
                    }
                }
            }
            if ($seen === [] && ($input['category'] ?? '') !== 'reserve') {
                $missing[] = 'Pairing credit is missing from this package.';
            }
            $assumptions[] = 'Published pairing credit is the scheduled ordinary-credit basis. Supported outgoing excess-day payments are allocated and shown separately; no blanket multiplier is applied to ordinary credit.';
        } elseif (($input['rows'] ?? []) === []) {
            $missing[] = 'Enter dated ordinary credits or an explicit 0:00 credit for a reviewed zero-credit schedule.';
        }
        $ordinary = $mode === 'source';
        foreach ($input['rows'] ?? [] as $row) {
            $type = $row['type'];
            if (! in_array($type, self::extras(), true) && $type !== 'unsupported') {
                $ordinary = true;
            }
            $seconds = self::seconds($row['duration']) ?? 0;
            if ($type === 'carry') {
                $actual = ($row['actual'] ?? '') === '' ? null : self::seconds($row['actual']);
                $seconds = max($seconds, $actual ?? $seconds);
                $assumptions[] = $actual === null ? 'Transitioning duty uses daily awarded PCH; future actual work is unknown.' : 'Transitioning duty uses the greater of daily awarded and entered actual PCH.';
            }
            $rows[] = ['type' => $type, 'date' => $row['date'], 'seconds' => $seconds,
                'basis' => 'Private scenario '.$row['assignment'].'; input '.$row['duration'].' h:mm[:ss]'.(($row['actual'] ?? '') === '' ? '' : '; actual '.$row['actual'].' PCH h:mm[:ss]').'; earning/departure '.$row['date'].' UTC; allocated to '.$period->label];
        }
        if (! $ordinary) {
            $missing[] = 'Ordinary scheduled credits must be supplied separately from additional compensation.';
        }
        if ($mode === 'manual') {
            $assumptions[] = 'Private ordinary-credit inputs replace the source credit basis for this scenario; shared package data is unchanged.';
        }

        return ['rows' => $rows, 'missing' => array_values(array_unique($missing)), 'assumptions' => array_values(array_unique($assumptions))];
    }

    /**
     * @param  array<string, mixed>  $trip
     * @return list<array<string, mixed>>|null
     */
    private function allocatedTrip(array $trip, CrewPayPeriod $period, string $id): ?array
    {
        $duties = $trip['duties'] ?? [];
        $legs = $trip['legs'] ?? [];
        if ($duties === [] || $legs === []) {
            return null;
        }
        if (($duties[0]['start_date'] ?? '') !== $trip['start_date']
            || ($duties[array_key_last($duties)]['end_date'] ?? '') !== $trip['end_date']) {
            return null;
        }
        $rows = [];
        $seen = [];
        $totalSeconds = 0;
        $nextPeriod = CarbonImmutable::parse($period->label.'-01', 'UTC')->addMonth()->format('Y-m');
        foreach ($duties as $duty) {
            $start = $duty['start_date'] ?? '';
            $end = $duty['end_date'] ?? '';
            if (! CarbonImmutable::hasFormat($start, 'Y-m-d') || ! CarbonImmutable::hasFormat($end, 'Y-m-d')
                || $start < $trip['start_date'] || $end > $trip['end_date'] || $end < $start) {
                return null;
            }
            $receivingPeriod = CrewPayPeriod::labelForDate($end);
            if (! in_array($receivingPeriod, [$period->label, $nextPeriod], true)) {
                return null;
            }
            foreach ($duty['leg_indexes'] ?? [] as $index) {
                if (isset($seen[$index]) || ! isset($legs[$index])) {
                    return null;
                }
                $seen[$index] = true;
                $leg = $legs[$index];
                $date = $leg['date'] ?? '';
                if (! CarbonImmutable::hasFormat($date, 'Y-m-d') || $date < $start || $date > $end) {
                    return null;
                }
                $deadhead = (bool) ($leg['deadhead'] ?? false);
                if ($deadhead) {
                    if (! CarbonImmutable::hasFormat($leg['departure'] ?? '', 'H:i') || ! CarbonImmutable::hasFormat($leg['arrival'] ?? '', 'H:i')) {
                        return null;
                    }
                    $departure = CarbonImmutable::parse($date.' '.$leg['departure'], 'UTC');
                    $arrival = CarbonImmutable::parse($date.' '.$leg['arrival'], 'UTC');
                    if ($arrival < $departure) {
                        $arrival = $arrival->addDay();
                    }
                    $seconds = intdiv($arrival->getTimestamp() - $departure->getTimestamp(), 2);
                } else {
                    $seconds = self::seconds($leg['block'] ?? '');
                }
                if ($seconds === null || $seconds <= 0) {
                    return null;
                }
                $totalSeconds += $seconds;
                $carry = $receivingPeriod !== $period->label;
                $rows[] = ['type' => $carry ? 'carry_excess' : 'credit', 'date' => $date, 'seconds' => $seconds,
                    'period' => $receivingPeriod,
                    'basis' => 'Trip '.$id.' '.$leg['flight'].'; '.($deadhead ? '50% of scheduled passenger deadhead' : 'published scheduled flight block')
                        .'; '.$seconds.' credited seconds; duty '.$start.' through '.$end.' UTC; earning/departure '.$date.' UTC; allocated to '.$receivingPeriod
                        .($carry ? '; whole excess-day payment at 1.5× credited hours, without a second ordinary-credit payment' : '')];
            }
        }
        if (count($seen) !== count($legs) || $totalSeconds !== $trip['credit_minutes'] * 60) {
            return null;
        }

        return $rows;
    }
}
