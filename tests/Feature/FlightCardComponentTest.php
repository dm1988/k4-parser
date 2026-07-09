<?php

namespace Tests\Feature;

use App\DTOs\Flight;
use App\Models\User;
use App\View\Models\Parser\FlightCardViewModel;
use Illuminate\Support\Facades\Blade;
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
