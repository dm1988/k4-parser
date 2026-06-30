<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\FlightEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FlightEventFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_recycled_aircraft_form_non_overlapping_routes(): void
    {
        Carbon::setTestNow('2026-06-30 18:00:00 UTC');
        $aircraft = Aircraft::factory()->count(3)->create();

        $flights = FlightEvent::factory()
            ->count(60)
            ->recycle($aircraft)
            ->create();

        foreach ($flights->groupBy('aircraft_id') as $aircraftFlights) {
            $aircraftFlights = $aircraftFlights->sortBy('start')->values();

            for ($index = 1; $index < $aircraftFlights->count(); $index++) {
                $previous = $aircraftFlights[$index - 1];
                $flight = $aircraftFlights[$index];

                $this->assertSame($previous->destination, $flight->origin);
                $this->assertGreaterThanOrEqual(120, $previous->end->diffInMinutes($flight->start));
                $this->assertTrue($flight->start->greaterThan($previous->end));
                $this->assertSame($flight->origin, data_get($flight->metadata, 'origin.code'));
                $this->assertSame($flight->destination, data_get($flight->metadata, 'destination.code'));
            }
        }

        $this->assertTrue($flights->contains(fn (FlightEvent $flight): bool => $flight->start->isPast()));
        $this->assertTrue($flights->contains(fn (FlightEvent $flight): bool => $flight->start->isFuture()));

        Carbon::setTestNow();
    }
}
