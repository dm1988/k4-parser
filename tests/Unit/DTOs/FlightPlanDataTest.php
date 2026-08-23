<?php

namespace Tests\Unit\DTOs;

use App\DTOs\Etops\EtopsData;
use App\DTOs\FlightIdentityData;
use App\DTOs\FlightPlanData;
use App\DTOs\FuelPlanData;
use App\DTOs\RouteData;
use App\DTOs\ScheduleData;
use App\DTOs\WaypointData;
use App\Enums\EtopsApplicability;
use App\ValueObjects\AirportCode;
use App\ValueObjects\FuelQuantity;
use PHPUnit\Framework\TestCase;

class FlightPlanDataTest extends TestCase
{
    public function test_it_composes_one_immutable_normalized_release(): void
    {
        $flightPlan = new FlightPlanData(
            identity: new FlightIdentityData(flightNumber: 'K4198'),
            schedule: new ScheduleData(etdUtc: '2026-08-21T12:00:00+00:00'),
            route: new RouteData(
                departure: new AirportCode('KJFK'),
                destination: new AirportCode('KLAX'),
            ),
            fuelPlan: new FuelPlanData(ramp: FuelQuantity::pounds(216800)),
            etops: new EtopsData(
                sectionPresent: true,
                applicability: EtopsApplicability::ConfirmedEtops,
            ),
            waypoints: [new WaypointData('FIX01', 'N01 02.3 E004 05.6', 5, 11, FuelQuantity::pounds(0))],
        );

        $this->assertSame('K4198', $flightPlan->identity->flightNumber);
        $this->assertSame('KJFK', $flightPlan->route->departure->value);
        $this->assertSame(216800.0, $flightPlan->fuelPlan?->ramp?->amount);
        $this->assertSame('2026-08-21T12:00:00+00:00', $flightPlan->toArray()['schedule']['etdUtc']);
        $this->assertSame('confirmed_etops', $flightPlan->toArray()['etops']['applicability']);
        $this->assertSame(0.0, $flightPlan->toArray()['waypoints'][0]['remainingFuel']['amount']);
        $this->assertTrue((new \ReflectionClass($flightPlan))->isReadOnly());
    }

    public function test_it_allows_optional_section_data_to_be_absent(): void
    {
        $flightPlan = new FlightPlanData(
            identity: new FlightIdentityData,
            schedule: new ScheduleData,
            route: new RouteData(
                departure: new AirportCode('KJFK'),
                destination: new AirportCode('KLAX'),
            ),
        );

        $this->assertNull($flightPlan->fuelPlan);
        $this->assertNull($flightPlan->flightInit);
        $this->assertNull($flightPlan->etops);
        $this->assertNull($flightPlan->toArray()['fuelPlan']);
        $this->assertNull($flightPlan->toArray()['flightInit']);
        $this->assertNull($flightPlan->toArray()['etops']);
    }
}
