<?php

namespace Tests\Unit\Enums;

use App\Enums\SlotDirection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SlotDirectionTest extends TestCase
{
    #[Test]
    public function it_provides_planned_time_comparison_labels(): void
    {
        $this->assertSame('Planned departure comparison', SlotDirection::Departure->comparisonHeading());
        $this->assertSame('ETD', SlotDirection::Departure->plannedTimeLabel());
        $this->assertSame('Planned arrival comparison', SlotDirection::Arrival->comparisonHeading());
        $this->assertSame('ETA', SlotDirection::Arrival->plannedTimeLabel());
        $this->assertNull(SlotDirection::Unspecified->comparisonHeading());
        $this->assertNull(SlotDirection::Unspecified->plannedTimeLabel());
    }
}
