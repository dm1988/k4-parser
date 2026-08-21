<?php

namespace Tests\Feature;

use App\Filament\Widgets\FlightPlanDemoMetrics;
use App\Models\ExtractRequest;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlightPlanDemoMetricsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_summarize_only_flight_plan_demo_requests(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $this->createExtractRequest($firstUser, 'success', 100);
        $this->createExtractRequest($firstUser, 'failed', 200);
        $this->createExtractRequest($secondUser, 'success', 300);
        $this->createExtractRequest($secondUser, 'success', 900, 'roster');

        $stats = $this->stats();

        $this->assertSame('Flight Plan Requests', $stats[0]->getLabel());
        $this->assertSame(3, $stats[0]->getValue());
        $this->assertSame('Failed Extracts', $stats[1]->getLabel());
        $this->assertSame(1, $stats[1]->getValue());
        $this->assertSame('33.3% failure rate', $stats[1]->getDescription());
        $this->assertSame('Average Processing Time', $stats[2]->getLabel());
        $this->assertSame('200 ms', $stats[2]->getValue());
        $this->assertSame('Users Adopted', $stats[3]->getLabel());
        $this->assertSame(2, $stats[3]->getValue());
    }

    public function test_metrics_use_the_flight_plan_brief_branding(): void
    {
        $widget = new class extends FlightPlanDemoMetrics
        {
            public function heading(): ?string
            {
                return $this->heading;
            }

            public function description(): ?string
            {
                return $this->description;
            }
        };

        $this->assertSame('Flight Plan Brief Demo', $widget->heading());
        $this->assertSame('Lifetime usage recorded by Flight Plan Brief.', $widget->description());
    }

    public function test_metrics_show_zero_values_before_demo_adoption(): void
    {
        $stats = $this->stats();

        $this->assertSame(0, $stats[0]->getValue());
        $this->assertSame(0, $stats[1]->getValue());
        $this->assertSame('0.0% failure rate', $stats[1]->getDescription());
        $this->assertSame('0 ms', $stats[2]->getValue());
        $this->assertSame(0, $stats[3]->getValue());
    }

    public function test_admins_can_see_the_flight_plan_demo_metrics_on_the_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('filament.admin.pages.dashboard'))
            ->assertOk()
            ->assertSee('wire:name="App\\Filament\\Widgets\\FlightPlanDemoMetrics"', escape: false);
    }

    /** @return array<Stat> */
    private function stats(): array
    {
        $widget = new class extends FlightPlanDemoMetrics
        {
            /** @return array<Stat> */
            public function stats(): array
            {
                return $this->getStats();
            }
        };

        return $widget->stats();
    }

    private function createExtractRequest(
        User $user,
        string $status,
        int $durationMs,
        string $parserType = 'flight_plan',
    ): ExtractRequest {
        return ExtractRequest::query()->create([
            'user_id' => $user->getKey(),
            'request_uuid' => fake()->uuid(),
            'source_type' => 'pdf',
            'parser_type' => $parserType,
            'status' => $status,
            'extraction_duration_ms' => $durationMs,
            'detected_event_count' => 1,
            'detected_flight_count' => 1,
            'detected_hotel_count' => 0,
        ]);
    }
}
