<?php

namespace Tests\Feature;

use App\Enums\FlightPlanTaskAvailability;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class FlightReleaseStatusComponentTest extends TestCase
{
    public function test_not_present_renders_as_a_red_badge(): void
    {
        $html = Blade::render(
            '<x-flight-release.status :availability="$availability" />',
            ['availability' => FlightPlanTaskAvailability::NotPresent],
        );

        $this->assertStringContainsString('Not present', $html);
        $this->assertStringContainsString(
            'bg-red-100 text-red-900 dark:bg-red-400/15 dark:text-red-200',
            $html,
        );
    }

    public function test_not_present_dot_uses_red_and_keeps_its_accessible_label(): void
    {
        $html = Blade::render(
            '<x-flight-release.status :availability="$availability" dot />',
            ['availability' => FlightPlanTaskAvailability::NotPresent],
        );

        $this->assertStringContainsString('aria-label="Not present"', $html);
        $this->assertStringContainsString('bg-red-500 dark:bg-red-400', $html);
    }

    public function test_available_remains_hidden_unless_explicitly_requested(): void
    {
        $hiddenHtml = Blade::render(
            '<x-flight-release.status :availability="$availability" />',
            ['availability' => FlightPlanTaskAvailability::Available],
        );
        $visibleHtml = Blade::render(
            '<x-flight-release.status :availability="$availability" :show-available="true" />',
            ['availability' => FlightPlanTaskAvailability::Available],
        );

        $this->assertStringNotContainsString('<span', $hiddenHtml);
        $this->assertStringContainsString('Available', $visibleHtml);
        $this->assertStringContainsString(FlightPlanTaskAvailability::Available->badgeColor(), $visibleHtml);
    }
}
