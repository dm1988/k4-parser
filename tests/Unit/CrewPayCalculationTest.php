<?php

namespace Tests\Unit;

use App\Services\CrewBids\CalculateCrewBidPay;
use App\Services\CrewBids\CrewPayPeriod;
use App\Services\CrewBids\CrewPayRates;
use App\Services\CrewBids\NormalizeCrewBidPay;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CrewPayCalculationTest extends TestCase
{
    #[DataProvider('periods')]
    public function test_compensation_periods_use_contract_boundaries(string $label, string $start, string $end, int $days): void
    {
        $period = new CrewPayPeriod($label);

        $this->assertSame($start, $period->start->format('Y-m-d'));
        $this->assertSame($end, $period->end->format('Y-m-d'));
        $this->assertCount($days, $period->dates());
    }

    /** @return array<string, array{string, string, string, int}> */
    public static function periods(): array
    {
        return [
            'January' => ['2026-01', '2026-01-01', '2026-01-30', 30],
            'February' => ['2026-02', '2026-01-31', '2026-03-01', 30],
            'leap February' => ['2024-02', '2024-01-31', '2024-03-01', 31],
            'March' => ['2026-03', '2026-03-02', '2026-03-31', 30],
            'year end' => ['2026-12', '2026-12-01', '2026-12-31', 31],
        ];
    }

    #[DataProvider('longevityCases')]
    public function test_rate_steps_and_leap_day_boundaries(string $longevity, string $date, ?int $step, ?int $cents): void
    {
        $rate = (new CrewPayRates)->resolve('FO', $date, $longevity);

        $this->assertSame($step, $rate['step'] ?? null);
        $this->assertSame($cents, $rate['cents'] ?? null);
    }

    /** @return array<string, array{string, string, ?int, ?int}> */
    public static function longevityCases(): array
    {
        return [
            'first day' => ['2024-02-29', '2024-02-29', 1, 11700],
            'before anniversary' => ['2024-02-29', '2025-02-28', 1, 11700],
            'March anniversary' => ['2024-02-29', '2025-03-01', 2, 14384],
            'leap anniversary' => ['2024-02-29', '2028-02-29', 5, 17890],
            'capped' => ['2000-01-01', '2026-11-01', 12, 21352],
            'future start' => ['2027-01-01', '2026-11-01', null, null],
            'missing rate coverage' => ['2020-01-01', '2023-12-31', null, null],
        ];
    }

    public function test_guarantee_day_weighting_and_source_guarantee_are_independent_of_adjusted_credit(): void
    {
        $context = $this->context([$this->row('credit', '0:00')]);
        $context['settings']['longevity_date'] = '2025-11-16';
        $context['line_guarantee'] = true;

        $estimate = $this->calculator()->calculate(['id' => 'F1', 'source_credit' => 4200, 'credit' => 60000], [], '2026-11', '777', 'FO', $context);

        $this->assertSame('ready', $estimate->status);
        $this->assertSame(912940, $estimate->cents);
        $this->assertCount(2, $estimate->rates);
        $this->assertSame('2026-11-16', $estimate->rates[1]['start']);
    }

    public function test_mbpg_and_scheduled_credit_can_each_win_the_base_comparison(): void
    {
        $minimum = $this->calculator()->calculate(['id' => 'F1'], [], '2026-11', '777', 'FO', $this->context([$this->row('credit', '0:00')]));
        $earned = $this->calculator()->calculate(['id' => 'F1'], [], '2026-11', '777', 'FO', $this->context([$this->row('credit', '80:00')]));
        $composite = $this->context([$this->row('credit', '0:00')]);
        $composite['lines']['F1']['category'] = 'composite';
        $compositePay = $this->calculator()->calculate(['id' => 'F1'], [], '2026-11', '777', 'FO', $composite);

        $this->assertSame(748800, $minimum->cents);
        $this->assertSame(936000, $earned->cents);
        $this->assertSame(819000, $compositePay->cents);
    }

    public function test_carried_credit_keeps_earning_day_rate_and_greater_daily_pch(): void
    {
        $row = $this->row('carry', '80:00', '2026-10-31');
        $row['actual'] = '90:00';
        $context = $this->context([$row]);
        $context['settings']['longevity_date'] = '2025-11-01';

        $estimate = $this->calculator()->calculate(['id' => 'F1'], [], '2026-11', '777', 'FO', $context);

        $this->assertSame(1053000, $estimate->cents);
        $this->assertSame(11700, $estimate->components[0]['rate_cents']);
    }

    public function test_open_time_is_the_entire_additional_off_day_payment(): void
    {
        $estimate = $this->calculator()->calculate(['id' => 'F1'], [], '2026-11', '777', 'FO',
            $this->context([$this->row('credit', '0:00'), $this->row('open_time', '10:00')]));

        $this->assertSame('ready', $estimate->status);
        $this->assertSame(924300, $estimate->cents);
    }

    public function test_only_final_total_is_rounded_and_maintenance_uses_started_hours(): void
    {
        $second = $this->row('sim_instruction', '0:00:01');
        $second['assignment'] = 'Other instruction';
        $estimate = $this->calculator()->calculate(['id' => 'F1'], [], '2026-11', '777', 'FO',
            $this->context([$this->row('credit', '0:00'), $this->row('sim_instruction', '0:00:01'), $second]));
        $maintenance = $this->calculator()->calculate(['id' => 'F1'], [], '2026-11', '777', 'FO',
            $this->context([$this->row('credit', '0:00'), $this->row('maintenance', '1:00:01')]));

        $this->assertSame(748801, $estimate->cents);
        $this->assertSame(753800, $maintenance->cents);
    }

    #[DataProvider('entitlements')]
    public function test_supported_entitlement_units(string $type, string $duration, int $expectedCents): void
    {
        $estimate = $this->calculator()->calculate(['id' => 'F1'], [], '2026-11', '777', 'FO',
            $this->context([$this->row('credit', '80:00'), $this->row($type, $duration)]));

        $this->assertSame('ready', $estimate->status);
        $this->assertSame($expectedCents, $estimate->cents);
    }

    /** @return array<string, array{string, string, int}> */
    public static function entitlements(): array
    {
        return [
            'flight block' => ['flight', '1:00', 947700],
            'half passenger scheduled block' => ['passenger_deadhead', '2:00', 947700],
            'half cent rounds up once' => ['passenger_deadhead', '0:00:04', 936007],
            'reserve minimum' => ['reserve', '1:00', 982800],
            'reserve greater daily pch' => ['reserve', '6:00', 1006200],
            'vacation fixed credit' => ['vacation', '0:00', 982800],
            'training fixed credit' => ['training', '0:00', 982800],
            'taxi minimum' => ['taxi', '0:30', 947700],
            'taxi actual block' => ['taxi', '2:00', 959400],
            'special tested CBT' => ['cbt', '0:00', 947700],
            'CQ completed exam' => ['cq_cbt', '0:00', 982800],
            'random test' => ['random_test', '0:00', 947700],
            'maintenance started hour' => ['maintenance', '0:00:01', 938500],
            'maintenance exact hour' => ['maintenance', '1:00', 938500],
            'simulator instruction' => ['sim_instruction', '2:00', 939000],
        ];
    }

    public function test_source_credit_crossing_a_rate_or_period_boundary_requires_dated_inputs(): void
    {
        $context = $this->context([]);
        $context['settings']['longevity_date'] = '2025-11-16';
        $context['lines']['F1']['mode'] = 'source';
        $line = ['id' => 'F1', 'calendar' => [], 'pairings' => [['id' => 'T1']]];
        $trips = ['T1' => ['start_date' => '2026-11-15', 'end_date' => '2026-11-17', 'credit_minutes' => 6000]];

        $anniversary = $this->calculator()->calculate($line, $trips, '2026-11', '777', 'FO', $context);
        $trips['T1']['end_date'] = '2026-12-01';
        $transition = $this->calculator()->calculate($line, $trips, '2026-11', '777', 'FO', $context);

        $this->assertNull($anniversary->cents);
        $this->assertStringContainsString('rate change', $anniversary->missing[0]);
        $this->assertNull($transition->cents);
        $this->assertStringContainsString('duty allocation', $transition->missing[0]);
    }

    public function test_missing_source_guarantee_and_unreviewed_inputs_never_become_ready(): void
    {
        $context = $this->context([$this->row('credit', '80:00')]);
        $context['line_guarantee'] = true;
        $context['lines']['F1']['reviewed'] = false;

        $estimate = $this->calculator()->calculate(['id' => 'F1', 'credit' => 6000], [], '2026-11', '777', 'FO', $context);

        $this->assertSame('incomplete', $estimate->status);
        $this->assertNull($estimate->cents);
        $this->assertCount(2, $estimate->missing);
    }

    public function test_supported_daily_and_additional_entitlements_and_check_airman_override(): void
    {
        $context = $this->context([$this->row('credit', '0:00'), $this->row('random_test', '0:00'), $this->row('cq_cbt', '0:00')]);
        $context['check_airman'] = true;

        $estimate = $this->calculator()->calculate(['id' => 'F1'], [], '2026-11', '747', 'FO', $context);

        $this->assertSame(910800, $estimate->cents);
        $this->assertSame(13200, $estimate->rates[0]['cents']);
    }

    public function test_unsupported_components_and_training_branch_do_not_produce_a_total(): void
    {
        $rows = [];
        for ($day = 1; $day <= 16; $day++) {
            $rows[] = $this->row('training', '0:00', sprintf('2026-11-%02d', $day));
        }
        $training = $this->calculator()->calculate(['id' => 'F1'], [], '2026-11', '777', 'FO', $this->context($rows));
        $other = $this->calculator()->calculate(['id' => 'F1'], [], '2026-11', '777', 'FO', $this->context([$this->row('unsupported', '0:00')]));

        $this->assertSame('unsupported', $training->status);
        $this->assertNull($training->cents);
        $this->assertSame('unsupported', $other->status);
        $this->assertNull($other->cents);
    }

    public function test_complete_pairing_totals_are_deduplicated_and_unknown_days_require_inputs(): void
    {
        $context = $this->context([]);
        $context['lines']['F1']['mode'] = 'source';
        $calendar = array_fill_keys((new CrewPayPeriod('2026-11'))->dates(), ['status' => 'off']);
        $calendar['2026-11-05'] = ['status' => 'occupied'];
        $line = ['id' => 'F1', 'calendar' => $calendar, 'pairings' => [['id' => 'T1'], ['id' => 'T1']]];
        $trips = ['T1' => ['start_date' => '2026-11-05', 'end_date' => '2026-11-05', 'credit_minutes' => 6000]];

        $ready = $this->calculator()->calculate($line, $trips, '2026-11', '777', 'FO', $context);
        $line['calendar']['2026-11-30']['status'] = 'unknown';
        $missing = $this->calculator()->calculate($line, $trips, '2026-11', '777', 'FO', $context);

        $this->assertSame(1170000, $ready->cents);
        $this->assertNull($missing->cents);
        $this->assertSame('incomplete', $missing->status);
    }

    private function calculator(): CalculateCrewBidPay
    {
        return new CalculateCrewBidPay(new CrewPayRates, new NormalizeCrewBidPay);
    }

    /** @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function context(array $rows): array
    {
        return ['settings' => ['longevity_date' => '2026-01-01'], 'qualified' => true, 'full_period' => true,
            'line_guarantee' => false, 'check_airman' => false, 'unsupported' => false,
            'lines' => ['F1' => ['mode' => 'manual', 'reviewed' => true, 'category' => 'hard', 'rows' => $rows]]];
    }

    /** @return array<string, mixed> */
    private function row(string $type, string $duration, string $date = '2026-11-15'): array
    {
        return ['assignment' => 'Test '.$type, 'type' => $type, 'date' => $date, 'duration' => $duration, 'actual' => ''];
    }
}
