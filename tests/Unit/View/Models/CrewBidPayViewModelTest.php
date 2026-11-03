<?php

namespace Tests\Unit\View\Models;

use App\DTOs\CrewBidPayEstimate;
use App\View\Models\CrewBidPayEditorViewModel;
use App\View\Models\CrewBidPayViewModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CrewBidPayViewModelTest extends TestCase
{
    public function test_prepares_display_amounts_without_replacing_final_exact_total(): void
    {
        $estimate = new CrewBidPayEstimate('ready', 748801, [
            ['label' => 'Instruction', 'exact_cents' => '5/12', 'basis' => 'Additional pay', 'rate_cents' => 1500],
        ], rates: [['start' => '2026-11-01', 'end' => '2026-11-30', 'step' => 1, 'cents' => 11700, 'seat' => 'FO']]);

        $view = CrewBidPayViewModel::fromEstimate($estimate);

        $this->assertTrue($view->ready);
        $this->assertSame('$7,488.01', $view->label);
        $this->assertSame('$0.00', $view->components[0]['amount']);
        $this->assertSame('Additional pay; $15.00/h', $view->components[0]['basis']);
        $this->assertSame(['2026-11-01–2026-11-30 UTC: FO step 1 · $117.00/h'], $view->rates);
    }

    #[DataProvider('unavailableStatuses')]
    public function test_missing_totals_get_distinct_prepared_labels(string $status, string $label): void
    {
        $view = CrewBidPayViewModel::fromEstimate(new CrewBidPayEstimate($status));

        $this->assertFalse($view->ready);
        $this->assertSame($label, $view->label);
    }

    /** @return array<string, array{string, string}> */
    public static function unavailableStatuses(): array
    {
        return ['not configured' => ['unconfigured', 'Set up pay'], 'incomplete' => ['incomplete', 'Inputs needed'], 'unsupported' => ['unsupported', 'Unavailable']];
    }

    public function test_editor_prepares_compensation_context_and_rejects_malformed_draft_rows_for_rendering(): void
    {
        $editor = CrewBidPayEditorViewModel::fromInput(true, 'F1', ['rows' => 'invalid'], '2026-02', 'FO');

        $this->assertSame('Pay adjustments · F1', $editor->title);
        $this->assertSame('FO · 2026-01-31 through 2026-03-01 UTC compensation period', $editor->period);
        $this->assertSame([], $editor->rows);
        $this->assertArrayHasKey('open_time', $editor->payTypes);
        $this->assertArrayHasKey('qualified', $editor->contextChoices);
    }
}
