<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\FlightEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FleetTimetableTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('fleet-timetable'))->assertRedirect(route('login'));
    }

    public function test_it_displays_aircraft_and_overlapping_flights_in_utc(): void
    {
        Carbon::setTestNow('2026-06-30 18:00:00 UTC');
        $aircraft = Aircraft::factory()->create(['tail_number' => 'N777CK']);
        $flight = FlightEvent::factory()->forAircraft($aircraft)->make([
            'flight_number' => 'K4777',
            'origin' => 'CVG',
            'destination' => 'ANC',
            'status' => 'En Route',
            'start' => Carbon::now()->subHours(16),
            'end' => Carbon::now()->subHours(14),
        ]);
        $flight->save();

        $this->actingAs(User::factory()->create())
            ->get(route('fleet-timetable'))
            ->assertOk()
            ->assertSee('Fleet timetable')
            ->assertSee('N777CK')
            ->assertSee('CVG → ANC')
            ->assertSee('K4777')
            ->assertSee('All times UTC')
            ->assertSee('https://www.flightaware.com/live/flight/N777CK');

        Carbon::setTestNow();
    }
}
