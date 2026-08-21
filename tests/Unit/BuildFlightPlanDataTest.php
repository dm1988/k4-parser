<?php

namespace Tests\Unit;

use App\Actions\BuildFlightPlanData;
use App\DTOs\ParsedFlightPlanData;
use PHPUnit\Framework\TestCase;

class BuildFlightPlanDataTest extends TestCase
{
    public function test_it_builds_the_normalized_flight_plan_aggregate(): void
    {
        $parsed = new ParsedFlightPlanData(
            identity: [
                'flight_number' => 'CKS256',
                'trip_number' => '109546',
                'recall_number' => '62930',
                'aircraft_type' => 'B777-200F',
                'tail_number' => 'N774CK',
                'flight_date' => '2026-05-25',
                'release_revision' => null,
            ],
            schedule: [
                'etd_utc' => '2026-05-25T02:20:00+00:00',
                'eta_utc' => '2026-05-25T14:50:00+00:00',
                'block_duration' => null,
                'report_time_utc' => null,
                'duty_end_utc' => null,
                'slot_times_utc' => ['2026-05-25T15:20:00+00:00'],
            ],
            route: [
                'departure' => 'klax',
                'destination' => 'rksi',
                'alternate' => 'rktu',
                'route' => 'DCT TEST',
                'departure_runway' => '25R',
                'arrival_runway' => '33R',
                'departure_sid' => 'SUMMR2',
                'arrival_star' => 'GUKDO2E',
                'distance_nautical_miles' => 5549,
            ],
            fuel: [
                'ramp' => ['amount' => 216800.0, 'unit' => 'lb'],
                'taxi' => ['amount' => 2000.0, 'unit' => 'lb'],
                'takeoff' => ['amount' => 214829.0, 'unit' => 'lb'],
                'trip' => ['amount' => 195116.0, 'unit' => 'lb'],
                'contingency' => null,
                'alternate' => ['amount' => 5600.0, 'unit' => 'lb'],
                'final_reserve' => ['amount' => 6900.0, 'unit' => 'lb'],
                'estimated_landing' => ['amount' => 19713.0, 'unit' => 'lb'],
            ],
        );

        $flightPlan = (new BuildFlightPlanData)->handle($parsed);

        $this->assertSame('CKS256', $flightPlan->identity->flightNumber);
        $this->assertSame('62930', $flightPlan->identity->recallNumber);
        $this->assertSame('2026-05-25', $flightPlan->identity->flightDate?->toDateString());
        $this->assertSame('2026-05-25T02:20:00+00:00', $flightPlan->schedule->etdUtc);
        $this->assertSame('KLAX', $flightPlan->route->departure->value);
        $this->assertSame(5549, $flightPlan->route->distanceNauticalMiles);
        $this->assertSame(216800.0, $flightPlan->fuelPlan?->ramp?->amount);
    }

    public function test_it_omits_the_fuel_plan_when_no_fuel_was_normalized(): void
    {
        $parsed = new ParsedFlightPlanData(
            identity: [
                'flight_number' => null,
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
                'route' => null,
                'departure_runway' => null,
                'arrival_runway' => null,
                'departure_sid' => null,
                'arrival_star' => null,
                'distance_nautical_miles' => null,
            ],
            fuel: array_fill_keys([
                'ramp', 'taxi', 'takeoff', 'trip', 'contingency', 'alternate', 'final_reserve', 'estimated_landing',
            ], null),
        );

        $this->assertNull((new BuildFlightPlanData)->handle($parsed)->fuelPlan);
    }

    public function test_it_falls_back_to_null_for_malformed_or_impossible_flight_dates(): void
    {
        foreach (['not-a-date', '2026-02-30'] as $flightDate) {
            $flightPlan = (new BuildFlightPlanData)->handle($this->partialParsedData(
                identity: ['flight_date' => $flightDate],
            ));

            $this->assertNull($flightPlan->identity->flightDate);
        }
    }

    public function test_it_preserves_null_slot_times_without_emitting_empty_strings(): void
    {
        $flightPlan = (new BuildFlightPlanData)->handle($this->partialParsedData(
            schedule: [
                'slot_times_utc' => [null, '2026-05-25T15:20:00+00:00'],
            ],
        ));

        $this->assertSame(
            [null, '2026-05-25T15:20:00+00:00'],
            $flightPlan->schedule->slotTimesUtc,
        );
    }

    public function test_it_defaults_missing_optional_parser_keys_to_null(): void
    {
        $flightPlan = (new BuildFlightPlanData)->handle($this->partialParsedData());

        $this->assertNull($flightPlan->identity->flightNumber);
        $this->assertNull($flightPlan->schedule->etdUtc);
        $this->assertNull($flightPlan->route->alternate);
        $this->assertNull($flightPlan->route->distanceNauticalMiles);
        $this->assertNull($flightPlan->fuelPlan);
    }

    public function test_it_preserves_an_explicit_zero_fuel_quantity(): void
    {
        $flightPlan = (new BuildFlightPlanData)->handle($this->partialParsedData(
            fuel: [
                'contingency' => ['amount' => 0.0, 'unit' => 'lb'],
            ],
        ));

        $this->assertNotNull($flightPlan->fuelPlan);
        $this->assertSame(0.0, $flightPlan->fuelPlan->contingency?->amount);
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $schedule
     * @param  array<string, mixed>  $route
     * @param  array<string, mixed>  $fuel
     */
    private function partialParsedData(
        array $identity = [],
        array $schedule = [],
        array $route = [],
        array $fuel = [],
    ): ParsedFlightPlanData {
        return new ParsedFlightPlanData(
            identity: $identity,
            schedule: $schedule,
            route: [
                'departure' => 'KLAX',
                'destination' => 'RKSI',
                ...$route,
            ],
            fuel: $fuel,
        );
    }
}
