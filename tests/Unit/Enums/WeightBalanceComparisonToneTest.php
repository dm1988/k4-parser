<?php

namespace Tests\Unit\Enums;

use App\Enums\WeightBalanceComparisonTone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WeightBalanceComparisonToneTest extends TestCase
{
    #[Test]
    public function it_exposes_badge_colors_that_match_the_progress_colors(): void
    {
        $this->assertSame('cc-weight-badge cc-weight-badge-safe text-white', WeightBalanceComparisonTone::Safe->badgeClasses());
        $this->assertSame('cc-weight-badge cc-weight-badge-heavy text-white', WeightBalanceComparisonTone::Heavy->badgeClasses());
        $this->assertSame('cc-weight-badge cc-weight-badge-caution text-[#0B0E14]', WeightBalanceComparisonTone::Caution->badgeClasses());
        $this->assertSame('cc-weight-badge cc-weight-badge-exceeded text-white', WeightBalanceComparisonTone::Exceeded->badgeClasses());
    }

    #[Test]
    public function it_exposes_subtle_overview_card_surfaces_for_each_tone(): void
    {
        $this->assertSame(
            'border-[#1B365D]/30 border-l-4 border-l-[#1B365D] bg-[#1B365D]/5 backdrop-blur dark:border-sky-400/30 dark:border-l-sky-400 dark:bg-sky-400/10',
            WeightBalanceComparisonTone::Heavy->overviewCardClasses(),
        );
        $this->assertSame(
            'border-amber-500/30 border-l-4 border-l-amber-500 bg-amber-500/5 backdrop-blur dark:border-amber-400/30 dark:border-l-amber-400 dark:bg-amber-400/10',
            WeightBalanceComparisonTone::Caution->overviewCardClasses(),
        );
        $this->assertSame(
            'border-red-500/30 border-l-4 border-l-red-500 bg-red-500/5 backdrop-blur dark:border-red-400/30 dark:border-l-red-400 dark:bg-red-400/10',
            WeightBalanceComparisonTone::Exceeded->overviewCardClasses(),
        );
    }

    #[Test]
    public function it_orders_only_operational_alert_tones_by_severity(): void
    {
        $this->assertFalse(WeightBalanceComparisonTone::Safe->isOperationalAlert());
        $this->assertTrue(WeightBalanceComparisonTone::Heavy->isOperationalAlert());
        $this->assertTrue(WeightBalanceComparisonTone::Caution->isOperationalAlert());
        $this->assertTrue(WeightBalanceComparisonTone::Exceeded->isOperationalAlert());

        $this->assertLessThan(
            WeightBalanceComparisonTone::Caution->severity(),
            WeightBalanceComparisonTone::Heavy->severity(),
        );
        $this->assertLessThan(
            WeightBalanceComparisonTone::Exceeded->severity(),
            WeightBalanceComparisonTone::Caution->severity(),
        );
    }
}
