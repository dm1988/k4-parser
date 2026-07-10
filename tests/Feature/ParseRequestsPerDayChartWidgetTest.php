<?php

namespace Tests\Feature;

use App\Filament\Widgets\ParseRequestsPerDayChart;
use App\Models\ParseRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParseRequestsPerDayChartWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_chart_data_contains_daily_parse_request_totals_for_the_last_seven_days(): void
    {
        CarbonImmutable::setTestNow('2026-07-10 12:00:00');

        $this->createParseRequest('2026-07-04 08:00:00');
        $this->createParseRequest('2026-07-04 09:00:00');
        $this->createParseRequest('2026-07-06 10:00:00');
        $this->createParseRequest('2026-07-10 11:00:00');
        $this->createParseRequest('2026-07-03 23:59:59');

        $widget = new class extends ParseRequestsPerDayChart
        {
            /**
             * @return array<string, mixed>
             */
            public function data(): array
            {
                return $this->getData();
            }
        };

        $data = $widget->data();

        $this->assertSame(
            ['Jul 4', 'Jul 5', 'Jul 6', 'Jul 7', 'Jul 8', 'Jul 9', 'Jul 10'],
            $data['labels'],
        );
        $this->assertSame([2, 0, 1, 0, 0, 0, 1], $data['datasets'][0]['data']);

        CarbonImmutable::setTestNow();
    }

    public function test_admins_can_see_the_parse_requests_per_day_widget_on_the_dashboard(): void
    {
        $this->actingAs($this->makeAdminUser())
            ->get(route('filament.admin.pages.dashboard'))
            ->assertOk()
            ->assertSee('wire:name="App\\Filament\\Widgets\\ParseRequestsPerDayChart"', escape: false);
    }

    private function makeAdminUser(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        return $user->refresh();
    }

    private function createParseRequest(string $createdAt): ParseRequest
    {
        $parseRequest = ParseRequest::query()->create([
            'user_id' => User::factory()->create()->getKey(),
            'request_uuid' => fake()->uuid(),
            'source_type' => 'pasted_text',
            'parser_type' => 'roster',
            'status' => 'success',
            'parse_duration_ms' => 150,
            'detected_event_count' => 0,
            'detected_flight_count' => 0,
            'detected_hotel_count' => 0,
        ]);

        $parseRequest->forceFill([
            'created_at' => $createdAt,
        ])->save();

        return $parseRequest->refresh();
    }
}
