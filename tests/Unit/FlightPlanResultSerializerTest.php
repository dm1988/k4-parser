<?php

namespace Tests\Unit;

use App\DTOs\AirportData;
use App\DTOs\FlightIdentityData;
use App\DTOs\FlightPlanData;
use App\DTOs\ParsedFlightPlanData;
use App\DTOs\RouteData;
use App\DTOs\ScheduleData;
use App\Services\FlightPlan\Extractor\FlightRouteExtractor;
use App\Services\FlightPlan\FlightPlanResultSerializer;
use App\ValueObjects\AirportCode;
use Tests\TestCase;

class FlightPlanResultSerializerTest extends TestCase
{
    public function test_it_preserves_the_flat_view_contract_and_adds_normalized_data(): void
    {
        $routeExtractor = $this->createMock(FlightRouteExtractor::class);
        $routeExtractor->expects($this->once())
            ->method('formatForIcaoDisplay')
            ->with('DCT TEST')
            ->willReturn("DCT\n TEST");
        $flightPlan = new FlightPlanData(
            identity: new FlightIdentityData(flightNumber: 'CKS256'),
            schedule: new ScheduleData,
            route: new RouteData(
                departure: new AirportCode('KLAX'),
                destination: new AirportCode('RKSI'),
                route: 'DCT TEST',
            ),
        );
        $parsed = new ParsedFlightPlanData(
            identity: [
                'flight_number' => 'CKS256',
                'trip_number' => null,
                'recall_number' => null,
                'aircraft_type' => null,
                'tail_number' => null,
                'flight_date' => null,
                'release_revision' => null,
            ],
            schedule: [
                'etd_utc' => null,
                'eta_utc' => null,
                'block_duration' => null,
                'report_time_utc' => null,
                'duty_end_utc' => null,
                'slot_times_utc' => [],
            ],
            route: [
                'departure' => 'KLAX',
                'destination' => 'RKSI',
                'alternate' => null,
                'route' => 'DCT TEST',
                'departure_runway' => null,
                'arrival_runway' => null,
                'departure_sid' => null,
                'arrival_star' => null,
                'distance_nautical_miles' => null,
            ],
            fuel: array_fill_keys([
                'ramp', 'taxi', 'takeoff', 'trip', 'contingency', 'alternate', 'final_reserve', 'estimated_landing',
            ], null),
            sourceFragments: ['fuel_summary' => 'must not leak'],
            legacy: [
                'departure_airport' => new AirportData('KLAX', 'LAX', 'Los Angeles International', 'Los Angeles', 'California', 'United States'),
                'destination_airport' => null,
                'alternate_airport' => null,
                'etps' => [],
                'eent_coordinates' => null,
                'eexp_coordinates' => null,
                'initial_altitude' => 'FL 340',
                'duration' => '12h10m',
            ],
        );

        $result = (new FlightPlanResultSerializer($routeExtractor))->serialize($flightPlan, $parsed);

        $this->assertSame('KLAX', $result['departure']);
        $this->assertSame('Los Angeles International', $result['departure_airport']['name']);
        $this->assertSame("DCT\n TEST", $result['route']);
        $this->assertSame('CKS256', $result['flight_plan_data']['identity']['flightNumber']);
        $this->assertArrayNotHasKey('source_fragments', $result);
        $this->assertStringNotContainsString('must not leak', json_encode($result, JSON_THROW_ON_ERROR));
    }
}
