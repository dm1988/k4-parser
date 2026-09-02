<?php

namespace Tests\Unit\Enums;

use App\Enums\FlightPlanTaskAvailability;
use App\Enums\TaskTone;
use PHPUnit\Framework\TestCase;

class FlightPlanTaskAvailabilityTest extends TestCase
{
    public function test_it_exposes_labels_and_badge_colors_for_each_availability_state(): void
    {
        foreach (FlightPlanTaskAvailability::cases() as $availability) {
            $this->assertNotSame('', $availability->label());
            $this->assertStringContainsString('bg-', $availability->badgeColor());
            $this->assertStringContainsString('dark:', $availability->badgeColor());
            $this->assertStringContainsString('bg-', $availability->dotColor());
            $this->assertStringContainsString('dark:', $availability->dotColor());
        }

        $this->assertSame('Not present', FlightPlanTaskAvailability::NotPresent->label());
        $this->assertSame(
            'bg-red-100 text-red-900 dark:bg-red-400/15 dark:text-red-200',
            FlightPlanTaskAvailability::NotPresent->badgeColor(),
        );
        $this->assertSame(
            'bg-red-500 dark:bg-red-400',
            FlightPlanTaskAvailability::NotPresent->dotColor(),
        );

        $this->assertSame(TaskTone::Neutral, FlightPlanTaskAvailability::Available->tone());
        $this->assertSame(TaskTone::Danger, FlightPlanTaskAvailability::NotPresent->tone());
        $this->assertSame(TaskTone::Warning, FlightPlanTaskAvailability::NotSupported->tone());
        $this->assertSame(TaskTone::Warning, FlightPlanTaskAvailability::Available->tone(absenceIsGood: true));
        $this->assertSame(TaskTone::Success, FlightPlanTaskAvailability::NotPresent->tone(absenceIsGood: true));
        $this->assertSame(TaskTone::Warning, FlightPlanTaskAvailability::NotSupported->tone(absenceIsGood: true));
        $this->assertSame(TaskTone::Warning->badgeColor(), FlightPlanTaskAvailability::Available->badgeColor(absenceIsGood: true));
        $this->assertSame(TaskTone::Success->dotColor(), FlightPlanTaskAvailability::NotPresent->dotColor(absenceIsGood: true));
    }
}
