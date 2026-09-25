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
