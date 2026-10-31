<?php

namespace Tests\Unit\View\Models;

use App\View\Models\CrewBidLineViewModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrewBidLineViewModelTest extends TestCase
{
    public function test_summary_preserves_seconds_zero_values_and_metric_order(): void
    {
        $model = CrewBidLineViewModel::fromLine($this->line(['credit_label' => '190:15:30', 'block' => 0, 'block_label' => '0:00', 'days_off' => 0]));

        $this->assertSame(['Credit', 'Block', 'Days off'], array_column($model->summaryMetrics, 'label'));
        $this->assertSame(['190:15:30', '0:00', '0'], array_column($model->summaryMetrics, 'value'));

        $withoutCredit = CrewBidLineViewModel::fromLine($this->line(['credit' => null]));
        $this->assertSame(['Block', 'Days off'], array_column($withoutCredit->summaryMetrics, 'label'));
    }

    public function test_missing_values_do_not_become_zero_or_available_metrics(): void
    {
        $model = CrewBidLineViewModel::fromLine($this->line(['credit' => null, 'block' => null, 'days_off' => null, 'carry_days' => null, 'crew' => ' ', 'start_city' => '', 'duty_days' => null]));

        $this->assertSame([], $model->summaryMetrics);
        $this->assertSame([], $model->detailMetrics);
        $this->assertSame([], $model->creditNotes);
        $this->assertNull($model->crew);
        $this->assertNull($model->carryDays);
    }

    /** @return array<string, array{string, int}> */
    public static function monthLengths(): array
    {
        return ['February' => ['2026-02', 28], 'leap February' => ['2028-02', 29], 'November' => ['2026-11', 30], 'December' => ['2026-12', 31]];
    }

    #[DataProvider('monthLengths')]
    public function test_calendar_keeps_every_date_and_labels_unknown_days_across_month_lengths(string $period, int $length): void
    {
        $month = [];
        for ($day = 1; $day <= $length; $day++) {
            $month[sprintf('%s-%02d', $period, $day)] = ['status' => $day === 2 ? 'unknown' : ($day < 15 ? 'occupied' : 'off'), 'activity' => $day === 2 ? 'Unknown' : ($day < 15 ? 'Trip / occupied' : 'Off')];
        }

        $model = CrewBidLineViewModel::fromLine($this->line(['month' => $month]));

        $this->assertCount($length, $model->calendarDays);
        $this->assertSame($period.'-01 UTC · Trip / occupied', $model->calendarDays[0]['description']);
        $this->assertSame('?', $model->calendarDays[1]['label']);
        $this->assertSame('Unknown', $model->calendarDays[1]['activity']);
        $this->assertSame('04', $model->calendarDays[3]['label']);
        $this->assertSame('05', $model->calendarDays[4]['label']);
        $this->assertSame((string) $length, $model->calendarDays[$length - 1]['label']);
        $this->assertSame((string) ($length - 1), $model->calendarDays[$length - 2]['label']);
    }

    public function test_details_preserve_zero_credit_and_only_show_work_blocks_when_requested(): void
    {
        $line = $this->line(['carry_days' => 0, 'carry_credit_label' => '0:00', 'carry_credit_seconds' => 0, 'blocks' => 2]);

        $model = CrewBidLineViewModel::fromLine($line, showBlocks: true);

        $details = array_column($model->detailMetrics, 'value', 'label');
        $this->assertSame('2', $details['Work blocks']);
        $this->assertSame('0', $details['Carry-in days after month-end']);
        $this->assertSame('0:00', $details['Carry credit subtotal · before premium · h:mm[:ss]']);
        $this->assertStringContainsString('not total contractual Pay Credit Hours', implode(' ', $model->creditNotes));
        $this->assertNotContains('Work blocks', array_column(CrewBidLineViewModel::fromLine($line)->detailMetrics, 'label'));
    }

    public function test_trip_disclosure_and_rank_keep_the_supplied_manual_order_and_missing_trip(): void
    {
        $trip = ['id' => '759', 'available' => false];

        $single = CrewBidLineViewModel::fromLine($this->line(['trip_details' => [$trip]]), rank: 4);
        $multiple = CrewBidLineViewModel::fromLine($this->line(['trip_details' => [$trip, ['id' => '760', 'available' => false]]]));

        $this->assertSame(4, $single->rank);
        $this->assertSame('Trip 759', $single->tripSummaryLabel);
        $this->assertTrue($single->singleTrip);
        $this->assertSame([$trip], $single->trips);
        $this->assertSame('Trips (2)', $multiple->tripSummaryLabel);
        $this->assertFalse($multiple->singleTrip);
        $this->assertNull($multiple->rank);
    }

    /** @return array<string, array{string, string, list<string>}> */
    public static function creditSources(): array
    {
        return [
            'recorded' => ['', '', ['Base credit: Recorded line credit.', 'Block: Recorded Lines block time.']],
            'trip totals' => ['trip_totals', 'trip_totals', ['Base credit: Complete pairing Credit Time totals from Trips, including credited deadheads.', 'Block: Complete pairing Block Time totals from Trips.']],
        ];
    }

    /** @param list<string> $expectedNotes */
    #[DataProvider('creditSources')]
    public function test_credit_explanations_retain_the_recorded_basis(string $creditBasis, string $blockBasis, array $expectedNotes): void
    {
        $model = CrewBidLineViewModel::fromLine($this->line(['credit_basis' => $creditBasis, 'block_basis' => $blockBasis]));

        $this->assertSame($expectedNotes, $model->creditNotes);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function line(array $overrides = []): array
    {
        return array_replace([
            'id' => 'F83', 'type' => 'flying', 'crew' => '2 CA 1 FO', 'credit' => 5415, 'credit_label' => '90:15', 'block' => 4800, 'block_label' => '80:00', 'days_off' => 15,
            'quick_flags' => [], 'month' => [], 'carry_days' => 0, 'trip_details' => [], 'warnings' => [], 'match_details' => [], 'filter_details' => [],
            'source_credit_label' => 'Not available', 'source_block_label' => 'Not available', 'base_credit_label' => 'Not available', 'deadhead_credit_label' => 'Not available', 'carry_credit_label' => 'Not available', 'carry_flying_label' => 'Not available', 'carry_deadhead_credit_label' => 'Not available', 'carry_premium_label' => 'Not available',
        ], $overrides);
    }
}
