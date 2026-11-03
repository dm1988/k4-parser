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
        if ($mode === 'source') {
            if (in_array($input['category'] ?? '', ['reserve', 'composite'], true)) {
                $missing[] = 'Reserve/composite scenarios require private daily credit inputs so daily minimums and other assignments are accounted for.';
            }
            $covered = [];
            $seen = [];
            foreach ($line['pairings'] ?? [] as $pairing) {
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
                    || $end < $start || ! $period->contains($start) || ! $period->contains($end)) {
                    $missing[] = 'Trip '.$id.': complete credit and duty allocation needed; use dated private credit inputs.';

                    continue;
                }
                if ($rates->resolve($seat, $start, $longevity) !== $rates->resolve($seat, $end, $longevity)) {
                    $missing[] = 'Trip '.$id.' spans a rate change; assign its credits to earning/departure UTC days.';

                    continue;
                }
                $rows[] = ['type' => 'credit', 'date' => $start, 'seconds' => $trip['credit_minutes'] * 60, 'basis' => 'Trips '.$id.': '.$trip['credit_minutes'].' published pairing credit minutes; constant rate throughout trip'];
                for ($date = CarbonImmutable::parse($start, 'UTC'); $date->format('Y-m-d') <= $end; $date = $date->addDay()) {
                    $covered[$date->format('Y-m-d')] = true;
                }
            }
            foreach ($period->dates() as $date) {
                $status = $line['calendar'][$date]['status'] ?? 'unknown';
                if ($status === 'unknown' || ($status === 'occupied' && ! isset($covered[$date]))) {
                    $missing[] = 'Scheduled credit coverage is incomplete; enter a reviewed, dated private credit scenario.';
                    break;
                }
            }
            if ($seen === []) {
                $missing[] = 'No complete linked pairing credits; private dated inputs are required, including an explicit zero if applicable.';
            }
            $assumptions[] = 'Published pairing credit is the scheduled ordinary-credit basis. No additional carry multiplier is applied.';
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
}
