<?php

namespace Tests\Feature;

use App\DTOs\Flight;
use App\Models\User;
use App\View\Models\Extract\FlightCardViewModel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FlightCardComponentTest extends TestCase
{
    public function test_tracker_link_uses_current_preference_for_existing_flight_cards(): void
    {
        $user = User::factory()->make(['flight_tracker_preference' => 'flightradar24']);
        $this->actingAs($user);
        $html = $this->renderFlightCard();

        $this->assertStringContainsString('href="https://www.flightradar24.com/data/aircraft/n772ck"', $html);
        $this->assertStringContainsString('Flightradar24', $html);
        $this->assertStringContainsString('title="Download .ics"', $html);
        $this->assertStringContainsString('focus-visible:outline', $html);

        $user->flight_tracker_preference = 'flightaware';
        $html = $this->renderFlightCard();
        $this->assertStringContainsString('href="https://www.flightaware.com/live/flight/N772CK"', $html);
        $this->assertStringContainsString('FlightAware', $html);
        $this->assertStringNotContainsString('flightradar24', $html);
    }

    public function test_tracker_link_uses_flight_number_when_a_tail_is_unavailable(): void
    {
        $this->actingAs(User::factory()->make(['flight_tracker_preference' => 'flightradar24']));
        $html = Blade::render('<x-extract.flight-card :model="$model" />', [
            'model' => FlightCardViewModel::fromFlight(Flight::fromArray([
                'type' => 'deadhead', 'flightNumber' => 'UA 123', 'origin' => 'LAX', 'destination' => 'AUS',
            ])),
        ]);

        $this->assertStringContainsString('href="https://www.flightradar24.com/data/flights/ua123"', $html);
        $this->assertStringContainsString('Flightradar24', $html);
    }

    public function test_tracker_link_is_omitted_without_a_tail_or_flight_number(): void
    {
        $html = Blade::render('<x-extract.flight-card :model="$model" />', [
            'model' => FlightCardViewModel::fromFlight(Flight::fromArray(['type' => 'flight', 'typeIcon' => 'heroicon-o-paper-airplane'])),
        ]);

        $this->assertStringNotContainsString('Flight Tracking', $html);
    }

    public function test_admins_can_see_the_duty_calendar_download_button(): void
    {
        $admin = User::factory()->make([
            'role' => 'admin',
        ]);

        $html = $this
            ->actingAs($admin)
            ->renderFlightCard();

        $this->assertStringContainsString('title="Download duty .ics"', $html);
    }

    public function test_non_admins_can_not_see_the_duty_calendar_download_button(): void
    {
        Config::set('features.schedule_extractor.duty_export_for_all_users', false);

        $user = User::factory()->make([
            'role' => 'user',
        ]);

        $html = $this
            ->actingAs($user)
            ->renderFlightCard();

        $this->assertStringNotContainsString('title="Download duty .ics"', $html);
        $this->assertStringContainsString('title="Download .ics"', $html);
    }

    public function test_non_admins_can_see_the_duty_calendar_download_button_when_the_feature_is_enabled_for_all_users(): void
    {
        Config::set('features.schedule_extractor.duty_export_for_all_users', true);

        $user = User::factory()->make([
            'role' => 'user',
        ]);

        $html = $this
            ->actingAs($user)
            ->renderFlightCard();

        $this->assertStringContainsString('title="Download duty .ics"', $html);
    }

    public function test_it_shows_the_airline_name_when_a_tail_number_is_not_available(): void
    {
        $html = Blade::render('<x-extract.flight-card :model="$model" />', [
            'model' => FlightCardViewModel::fromFlight(Flight::fromArray([
                'title' => 'G4 368 AUS-CVG',
                'type' => 'deadhead',
                'typeLabel' => 'Deadhead',
                'typeDescription' => 'Time spent traveling as a passenger for work purposes.',
                'typeIcon' => 'heroicon-o-paper-airplane',
                'scheduleLabel' => 'Jun 12 • 5:44 PM - 9:17 PM',
                'durationLabel' => '3h 33m',
                'isDeadhead' => true,
                'badgeColor' => 'bg-yellow-100 text-yellow-900',
                'downloadUrl' => route('parse.export.event', [
                    'eventId' => '01JTESTEVENTKEYABC123',
                    'parse_key' => '01JTESTPARSEKEYABC123',
                ]),
                'downloadId' => '01JTESTEVENTKEYABC123',
                'flightNumber' => 'G4 368',
                'start' => '2026-06-12T17:44:00+00:00',
                'end' => '2026-06-12T21:17:00+00:00',
                'origin' => 'AUS',
                'destination' => 'CVG',
                'metadata' => [
                    'airline_name' => 'Allegiant Air',
                ],
            ])),
        ]);

        $this->assertStringContainsString('Airline', $html);
        $this->assertStringContainsString('Allegiant Air', $html);
        $this->assertStringNotContainsString('Tail</span>', $html);
        $this->assertStringNotContainsString('Airport details unavailable', $html);
    }

    public function test_it_renders_airport_info_popover_triggers_when_airport_lookup_data_is_available(): void
    {
        $html = Blade::render('<x-extract.flight-card :model="$model" />', [
            'model' => FlightCardViewModel::fromFlight(Flight::fromArray([
                'title' => 'CKS 240 ICN-HKG',
                'type' => 'flight',
                'typeLabel' => 'Flight',
                'typeDescription' => 'Scheduled flying segment.',
                'typeIcon' => 'heroicon-o-paper-airplane',
                'scheduleLabel' => 'Jun 15, 11:45 PM -> Jun 16, 3:45 AM',
                'durationLabel' => '4:00h',
                'isDeadhead' => false,
                'badgeColor' => 'bg-blue-100 text-blue-900',
                'downloadUrl' => route('parse.export.event', [
                    'eventId' => '01JTESTEVENTKEYABC123',
                    'parse_key' => '01JTESTPARSEKEYABC123',
                ]),
                'downloadId' => '01JTESTEVENTKEYABC123',
                'flightNumber' => 'CKS 240',
                'start' => '2026-06-15T23:45:00+00:00',
                'end' => '2026-06-16T03:45:00+00:00',
                'origin' => 'ICN',
                'destination' => 'HKG',
                'metadata' => [
                    'origin_icao' => 'RKSI',
                    'origin_name' => 'Incheon International Airport',
                    'origin_city' => 'Seoul',
                    'origin_country' => 'South Korea',
                    'destination_icao' => 'VHHH',
                    'destination_name' => 'Hong Kong International Airport',
                    'destination_city' => 'Hong Kong',
                    'destination_country' => 'Hong Kong',
                ],
            ])),
        ]);

        $this->assertStringContainsString('aria-label="Airport info for ICN"', $html);
        $this->assertStringContainsString('aria-label="Airport info for HKG"', $html);
        $this->assertStringContainsString('Airport Info', $html);
        $this->assertStringNotContainsString('Airport details', $html);
        $this->assertStringContainsString('overflow-visible rounded-lg', $html);
        $this->assertStringNotContainsString('<article class="overflow-hidden', $html);
        $this->assertStringContainsString("x-bind:class=\"{ 'z-50': open }\"", $html);
        $this->assertStringContainsString('max-w-[calc(100vw-3rem)]', $html);
        $this->assertStringContainsString('break-words text-[13px]', $html);
        $this->assertStringContainsString('break-words text-[12px]', $html);
        $this->assertStringContainsString('x-bind:aria-controls="$id(\'airport-popover\')"', $html);
        $this->assertStringContainsString('x-bind:id="$id(\'airport-popover\')"', $html);
    }

    public function test_it_does_not_render_a_deadhead_badge_for_crew_members(): void
    {
        $html = $this->renderFlightCard();

        $this->assertStringContainsString('Deadheading', $html);
        $this->assertDoesNotMatchRegularExpression('/<span[^>]*>\s*Deadhead\s*<\/span>/', $html);
    }

    public function test_it_groups_local_flight_and_duty_times_in_a_two_column_grid(): void
    {
        $html = $this->renderFlightCard();

        $this->assertStringContainsString('aria-labelledby="local-times-heading"', $html);
        $this->assertStringContainsString('data-local-times-grid class="grid min-w-0 gap-2 sm:grid-cols-2"', $html);
        $this->assertMatchesRegularExpression('/data-local-times-group\s+class="[^"]*min-w-0/', $html);
        $this->assertSame(2, preg_match_all('/data-detail-card\s+class="[^"]*\bmin-w-0\b/', $html));
        $this->assertSame(2, substr_count($html, 'break-words'));
        $this->assertStringContainsString('border-[#C5A059]/40', $html);
        $this->assertStringContainsString('bg-[#F8F9FA]', $html);
        $this->assertStringNotContainsString('bg-[#C5A059]/5', $html);
        $this->assertMatchesRegularExpression(
            '/Local Times.*Flight Times.*Duty Times/s',
            $html,
        );
    }

    private function renderFlightCard(): string
    {
        return Blade::render('<x-extract.flight-card :model="$model" />', [
            'model' => FlightCardViewModel::fromFlight(Flight::fromArray([
                'title' => 'CKS 240 ICN-HKG',
                'type' => 'flight',
                'typeLabel' => 'Flight',
                'typeDescription' => 'Scheduled flying segment.',
                'typeIcon' => 'heroicon-o-paper-airplane',
                'scheduleLabel' => 'Jun 15, 11:45 PM -> Jun 16, 3:45 AM',
                'durationLabel' => '4:00h',
                'tailNumber' => 'N772CK',
                'isDeadhead' => false,
                'badgeColor' => 'bg-blue-100 text-blue-900',
                'downloadUrl' => route('parse.export.event', [
                    'eventId' => '01JTESTEVENTKEYABC123',
                    'parse_key' => '01JTESTPARSEKEYABC123',
                ]),
                'downloadId' => '01JTESTEVENTKEYABC123',
                'flightNumber' => 'CKS 240',
                'legLocalStart' => 'Jun 16 08:45',
                'legLocalEnd' => 'Jun 16 11:45',
                'dutyLocalStart' => 'Jun 16 06:45',
                'dutyLocalEnd' => 'Jun 15 12:00',
                'start' => '2026-06-15T23:45:00+00:00',
                'end' => '2026-06-16T03:45:00+00:00',
                'origin' => 'ICN',
                'destination' => 'HKG',
                'metadata' => [
                    'crew_count' => 1,
                    'operating_crew_count' => 0,
                    'deadheading_crew_count' => 1,
                    'crew' => [
                        [
                            'name' => 'Cameron Stovold',
                            'role' => 'DH',
                            'deadheading' => true,
                        ],
                    ],
                ],
            ])),
        ]);
    }
}
