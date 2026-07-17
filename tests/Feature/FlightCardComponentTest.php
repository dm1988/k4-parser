<?php

namespace Tests\Feature;

use App\DTOs\Flight;
use App\Models\User;
use App\View\Models\Parser\FlightCardViewModel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FlightCardComponentTest extends TestCase
{
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
        Config::set('features.schedule_parser.duty_export_for_all_users', true);

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
        $html = Blade::render('<x-parser.flight-card :model="$model" />', [
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
    }

    public function test_it_renders_airport_info_popover_triggers_when_airport_lookup_data_is_available(): void
    {
        $html = Blade::render('<x-parser.flight-card :model="$model" />', [
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
    }

    private function renderFlightCard(): string
    {
        return Blade::render('<x-parser.flight-card :model="$model" />', [
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
            ])),
        ]);
    }
}
