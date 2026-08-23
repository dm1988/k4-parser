<?php

namespace Tests\Unit\Enums;

use App\Enums\FlightPlanTask;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FlightPlanTaskTest extends TestCase
{
    #[Test]
    public function it_maps_every_task_to_its_conventional_component_name(): void
    {
        foreach (FlightPlanTask::cases() as $task) {
            $this->assertSame(
                'flight-release.'.str_replace('_', '-', $task->value),
                $task->componentName(),
            );
        }
    }

    #[Test]
    public function it_identifies_tasks_that_require_airport_data(): void
    {
        $this->assertTrue(FlightPlanTask::JeppPdPro->requiresAirports());
        $this->assertTrue(FlightPlanTask::Fms->requiresAirports());
        $this->assertFalse(FlightPlanTask::Overview->requiresAirports());
        $this->assertFalse(FlightPlanTask::FuelScore->requiresAirports());
    }

    #[Test]
    public function it_identifies_tasks_with_dedicated_views(): void
    {
        $customViews = array_values(array_filter(
            FlightPlanTask::cases(),
            static fn (FlightPlanTask $task): bool => $task->hasCustomView(),
        ));

        $this->assertSame([
            FlightPlanTask::Overview,
            FlightPlanTask::JeppPdPro,
            FlightPlanTask::MaintenanceLog,
            FlightPlanTask::Envelope,
            FlightPlanTask::FlightInit,
            FlightPlanTask::Fms,
            FlightPlanTask::FuelScore,
            FlightPlanTask::Etops,
        ], $customViews);
    }
}
