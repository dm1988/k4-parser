<?php

namespace App\Services\CrewBids;

use App\DTOs\CrewBidPayEstimate;
use Brick\Math\BigRational;

class CalculateCrewBidPay
{
    /** @var array<string, CrewPayPeriod> */
    private array $periods = [];

    /** @var array<string, array{sum: int, intervals: array<string, array{start: string, end: string, rate: array{step: int, cents: int}}>, missing: list<string>}> */
    private array $periodRates = [];

    public function __construct(private CrewPayRates $rates, private NormalizeCrewBidPay $normalize) {}

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, array<string, mixed>>  $trips
     * @param  array<string, mixed>  $context
     */
    public function calculate(array $line, array $trips, string $periodLabel, string $fleet, string $seat, array $context): CrewBidPayEstimate
    {
        $settings = $context['settings'] ?? [];
        $input = $context['lines'][$line['id']] ?? [];
        $period = $this->periods[$periodLabel] ??= new CrewPayPeriod($periodLabel);
        $periodText = $period->start->format('Y-m-d').' through '.$period->end->format('Y-m-d').' UTC';
        if (($settings['longevity_date'] ?? '') === '') {
            return new CrewBidPayEstimate('unconfigured', missing: ['Enter your adjusted pay-longevity date in your user profile and review the period scenario.'], period: $periodText);
        }
        if (! in_array($fleet, $this->rates->metadata()['fleets'], true) || ($context['unsupported'] ?? false)) {
            return new CrewBidPayEstimate('unsupported', missing: ['This rate table or personal pay scenario is outside the supported scope.'], period: $periodText);
        }
        $missing = [];
        if (array_key_exists('check_airman', $context) && $context['check_airman'] === null) {
            $missing[] = 'Saved Check Airman scenarios disagree. Choose and save the Check Airman setting in your user profile before comparing pay.';
        }
        if (! ($context['qualified'] ?? true) || ! ($context['full_period'] ?? true)) {
            $missing[] = 'Confirm qualified hourly Flight Pay in this seat and full-period availability. New-hire weekly pay, partial availability and qualification/crew-class changes are unsupported.';
        }
        $category = self::category($line['id']) ?? ($input['category'] ?? '');
        if (! in_array($category, ['hard', 'reserve', 'composite'], true)) {
            $missing[] = 'This line type is not recognized. F lines are hard lines; Q lines are reserve.';
        }
        $input['category'] = $category;
        $longevity = $settings['longevity_date'];
        $checkAirman = (bool) ($context['check_airman'] ?? false);
        $ratePeriod = $this->ratePeriod($period, $seat, $longevity, $checkAirman);
        $sumRates = $ratePeriod['sum'];
        $intervals = $ratePeriod['intervals'];
        $missing = array_merge($missing, $ratePeriod['missing']);
        $assumptions = [
            'USD scheduled gross compensation for the stated compensation period, before taxes/deductions; per diem and expense reimbursements excluded. This is not a deposit forecast.',
            'User-confirmed scenario policies: adjusted longevity; anniversary at 0000Z; Feb 29 anniversary on Mar 1 in non-leap years; step cap 12; actual UTC-day guarantee weighting; one final half-up rounding.',
            'Source line credit is the published guarantee, not scheduled PCH. Other-seat browsing is a declared scenario, not evidence of qualification.',
            'Normal line estimate assumes qualified hourly Flight Pay, full-period availability and no unentered additional assignments. Use optional adjustments for exceptions.',
        ];
        if ($checkAirman) {
            $assumptions[] = 'Check Airman scenario: the contractual minimum $15/hour override applies to all flown/credited hours and guarantees.';
        }
        $assumptions[] = ($context['line_guarantee'] ?? true)
            ? 'Published line Credit is included automatically as the minimum credit for this line’s pay.'
            : 'Published line minimum excluded by an explicit advanced adjustment.';
        $normalized = $this->normalize->normalize($line, $trips, $input, $period, $this->rates, $seat, $longevity);
        $missing = array_merge($missing, $normalized['missing']);
        $assumptions = array_merge($assumptions, $normalized['assumptions']);
        $ordinary = BigRational::of(0);
        $extra = BigRational::of(0);
        $carryPayments = [];
        $earningRates = [];
        $components = [];
        $unsupported = false;
        $trainingDays = [];
        foreach ($normalized['rows'] as $row) {
            $type = $row['type'];
            if ($type === 'unsupported') {
                $unsupported = true;
                $missing[] = 'Other applicable compensation needs verified rules: '.$row['basis'];

                continue;
            }
            $rate = $this->rates->resolve($seat, $row['date'], $longevity, $checkAirman);
            if ($rate === null) {
                $missing[] = 'No applicable earning/departure-day rate for '.$row['date'].'.';

                continue;
            }
            if (! $period->contains($row['date'])) {
                $earningRates[$row['date']] = $rate;
            }
            $seconds = $row['seconds'];
            $amount = match ($type) {
                'passenger_deadhead' => BigRational::of($seconds)->multipliedBy($rate['cents'])->dividedBy(7200),
                'reserve', 'taxi' => BigRational::of(max($type === 'reserve' ? 14400 : 3600, $seconds))->multipliedBy($rate['cents'])->dividedBy(3600),
                'vacation', 'training', 'cq_cbt' => BigRational::of(4 * $rate['cents']),
                'cbt', 'random_test' => BigRational::of($rate['cents']),
                'open_time', 'carry_excess' => BigRational::of($seconds)->multipliedBy(3 * $rate['cents'])->dividedBy(7200),
                'maintenance' => BigRational::of(intdiv($seconds + 3599, 3600) * 2500),
                'sim_instruction' => BigRational::of($seconds)->multipliedBy(1500)->dividedBy(3600),
                default => BigRational::of($seconds)->multipliedBy($rate['cents'])->dividedBy(3600),
            };
            if ($type === 'training') {
                $trainingDays[$row['date']] = true;
            }
            if ($type === 'sim_instruction' && $checkAirman) {
                $unsupported = true;
                $missing[] = 'The separate simulator-instructor override requires a non-Check-Airman scenario.';
            }
            if ($type === 'open_time') {
                $assumptions[] = 'Open Time flight on an actual SDFDO: 1.5 × hours flown × earning-day rate is the whole additional off-day payment; no separate ordinary credit for this assignment (user-confirmed 5.D.2/5.L interaction).';
            }
            if ($type === 'carry_excess') {
                $receivingPeriod = $row['period'];
                $carryPayments[$receivingPeriod] = ($carryPayments[$receivingPeriod] ?? BigRational::of(0))->plus($amount);
                $components[] = $this->component('Carry-in excess-day pay — '.$receivingPeriod, $amount, $row['basis'].'; additional to originating month base', $rate['cents']);
            } elseif (in_array($type, NormalizeCrewBidPay::extras(), true)) {
                $extra = $extra->plus($amount);
                $components[] = $this->component(NormalizeCrewBidPay::types()[$type], $amount, $row['basis'].'; additional to base', match ($type) {
                    'maintenance' => 2500,
                    'sim_instruction' => 1500,
                    default => $rate['cents'],
                });
            } else {
                $ordinary = $ordinary->plus($amount);
                $components[] = $this->component(NormalizeCrewBidPay::types()[$type].' — included in scheduled credits', $amount,
                    $row['basis'].'; not a separate addition to selected base', $rate['cents']);
            }
        }
        if (count($trainingDays) >= 16) {
            $unsupported = true;
            $missing[] = 'Training of 16 or more days requires the Monthly Guarantee branch; daily training credit cannot be used.';
        }
        $weightedRate = BigRational::of($sumRates)->dividedBy(count($period->dates()));
        $candidates = ['MBPG' => $weightedRate->multipliedBy($category === 'composite' ? 70 : 64), 'Scheduled ordinary credits' => $ordinary];
        if ($context['line_guarantee'] ?? true) {
            if (! isset($line['source_credit']) || ! is_int($line['source_credit']) || $line['source_credit'] < 0) {
                $missing[] = 'Published contractual line guarantee is missing; adjusted Credit cannot replace it.';
            } else {
                $candidates['Published line guarantee'] = $weightedRate->multipliedBy($line['source_credit'])->dividedBy(60);
            }
        }
        $winner = 'MBPG';
        foreach ($candidates as $label => $amount) {
            if ($amount->isGreaterThan($candidates[$winner])) {
                $winner = $label;
            }
        }
        foreach ($candidates as $label => $amount) {
            $components[] = $this->component($label.($label === $winner ? ' — selected base' : ' — comparison only'), $amount,
                $label === 'Scheduled ordinary credits' ? 'Sum of dated ordinary credits at earning/departure UTC-day rates; includes applicable Check Airman override.' : 'Eligible guarantee PCH × sum of daily hourly rates / actual compensation-period UTC days.');
        }
        $missing = array_values(array_unique($missing));
        $periodTotal = $candidates[$winner]->plus($extra);
        $total = $periodTotal;
        $allocations = [['period' => $periodLabel, 'label' => 'Scheduled gross pay', 'exact_cents' => (string) $periodTotal]];
        ksort($carryPayments);
        foreach ($carryPayments as $label => $payment) {
            $total = $total->plus($payment);
            $allocations[] = ['period' => $label, 'label' => 'Carry-in excess-day payment only', 'exact_cents' => (string) $payment];
        }
        $minimum = null;
        if ($missing !== [] && ! $unsupported && ($input['mode'] ?? 'source') === 'source'
            && ($context['qualified'] ?? true) && ($context['full_period'] ?? true)
            && (! array_key_exists('check_airman', $context) || $context['check_airman'] !== null) && count($intervals) > 0
            && ! array_filter($missing, static fn (string $reason): bool => str_starts_with($reason, 'No applicable hourly rate'))
            && in_array($category, ['hard', 'reserve'], true)) {
            $floor = $candidates['Published line guarantee'] ?? $candidates['MBPG'];
            if ($candidates['MBPG']->isGreaterThan($floor)) {
                $floor = $candidates['MBPG'];
            }
            $minimum = CrewBidPayEstimate::roundedCents($floor);
        }
        $rates = array_map(static fn (array $interval): array => ['start' => $interval['start'], 'end' => $interval['end'], 'step' => $interval['rate']['step'], 'cents' => $interval['rate']['cents'], 'seat' => $seat], array_values($intervals));
        ksort($earningRates);
        foreach ($earningRates as $date => $rate) {
            $rates[] = ['start' => $date, 'end' => $date, 'step' => $rate['step'], 'cents' => $rate['cents'], 'seat' => $seat];
        }
        if ($carryPayments !== []) {
            $periodText .= '; plus carry-in allocated to '.implode(', ', array_keys($carryPayments));
            $assumptions[] = 'Combined line value is originating-period scheduled gross plus the displayed receiving-period excess-day payment. It is not one month’s pay or a forecast of the next month’s full compensation.';
        }
        $assumptions[] = 'Rate table '.$this->rates->metadata()['version'].'; current applicability confirmed by user. Component displays are rounded individually for reading; only selected base plus extras enter the exact sum, rounded once.';

        return new CrewBidPayEstimate($unsupported ? 'unsupported' : ($missing === [] ? 'ready' : 'incomplete'), $missing === [] && ! $unsupported ? CrewBidPayEstimate::roundedCents($total) : null,
            $components, $missing, array_values(array_unique($assumptions)), $rates, $periodText,
            minimumCents: $minimum,
            allocations: $missing === [] && ! $unsupported ? $allocations : [], includesCarry: $carryPayments !== []);
    }

    public static function category(string $lineId): ?string
    {
        return match (substr($lineId, 0, 1)) {
            'F' => 'hard',
            'Q' => 'reserve',
            default => null,
        };
    }

    /** @return array{sum: int, intervals: array<string, array{start: string, end: string, rate: array{step: int, cents: int}}>, missing: list<string>} */
    private function ratePeriod(CrewPayPeriod $period, string $seat, string $longevity, bool $checkAirman): array
    {
        $cacheKey = $period->label.'|'.$seat.'|'.$longevity.'|'.(int) $checkAirman;
        if (isset($this->periodRates[$cacheKey])) {
            return $this->periodRates[$cacheKey];
        }
        $sum = 0;
        $intervals = [];
        $missing = [];
        foreach ($period->dates() as $date) {
            $rate = $this->rates->resolve($seat, $date, $longevity, $checkAirman);
            if ($rate === null) {
                $missing[] = 'No applicable hourly rate for '.$date.'.';

                continue;
            }
            $sum += $rate['cents'];
            $key = $rate['step'].':'.$rate['cents'];
            $intervals[$key] ??= ['start' => $date, 'end' => $date, 'rate' => $rate];
            $intervals[$key]['end'] = $date;
        }

        return $this->periodRates[$cacheKey] = ['sum' => $sum, 'intervals' => $intervals, 'missing' => $missing];
    }

    /** @return array{label: string, exact_cents: string, basis: string, rate_cents: ?int} */
    private function component(string $label, BigRational $amount, string $basis, ?int $rateCents = null): array
    {
        return ['label' => $label, 'exact_cents' => (string) $amount, 'basis' => $basis, 'rate_cents' => $rateCents];
    }
}
