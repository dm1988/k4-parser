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
            crewMembers: [[
                'name' => 'Alex Morgan',
                'role' => 'CP',
                'base' => 'YIP',
                'employee_number' => '4827',
            ]],
            maintenance: [
                'section_present' => true,
                'etops_applicability' => 'confirmed_etops',
                'items' => [[
                    'type' => 'MEL',
                    'number' => '28-22-01',
                    'description' => 'Center tank override pump inoperative.',
                    'reference' => '1042',
                    'status' => 'OPEN',
                    'limitations' => null,
                    'procedures' => null,
                ]],
            ],
            envelope: [
                'section_present' => true,
                'source_type' => 'takeoff_landing_report',
                'report_reference' => 'TLR-30 SEQ-48273190 25MAY26 0115Z',
                'airport' => 'KLAX',
                'planned_runway' => '25R',
                'outside_air_temperature_celsius' => 18.0,
                'wind' => '250M08',
                'qnh_inches_mercury' => null,
                'qnh_hectopascals' => 1015,
                'maximum_runway_takeoff_weight' => ['amount' => 768000, 'unit' => 'lb'],
                'flap_setting' => '15',
                'anti_ice' => false,
                'v1_knots' => 151,
                'rotate_knots' => 158,
                'v2_knots' => 164,
                'planned_takeoff_weight' => ['amount' => 612400, 'unit' => 'lb'],
                'maximum_field_takeoff_weight' => ['amount' => 766000, 'unit' => 'lb'],
                'source_warnings' => ['Source warning'],
            ],
            flightInit: [
                'section_present' => true,
                'acars_init_date' => '11',
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
        $this->assertTrue($flightPlan->maintenanceLog?->sectionPresent);
        $this->assertSame('28-22-01', $flightPlan->maintenanceLog->items[0]->number);
        $this->assertSame('Alex Morgan', $flightPlan->crewMembers[0]->name);
        $this->assertSame('4827', $flightPlan->crewMembers[0]->employeeNumber);
        $this->assertSame('11', $flightPlan->flightInit?->acarsInitDate);
        $this->assertSame(612400, $flightPlan->envelope->plannedTakeoffWeight?->amount);
        $this->assertNull($flightPlan->envelope->qnhInchesMercury);
        $this->assertSame(1015, $flightPlan->envelope->qnhHectopascals);
        $this->assertFalse($flightPlan->envelope->antiIce);
        $this->assertSame(['Source warning'], $flightPlan->envelope?->sourceWarnings);
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
        $this->assertFalse($flightPlan->maintenanceLog?->sectionPresent);
        $this->assertNull($flightPlan->envelope);
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

    public function test_it_migrates_current_etops_values_without_changing_their_meaning(): void
    {
        $flightPlan = (new BuildFlightPlanData)->handle($this->partialParsedData(
            etops: [
                'etps' => [[
                    'label' => 'ETP1',
                    'airports' => 'KSFO-PACD',
                    'coordinates' => 'N45 43.7 W143 53.1',
                    'scenario' => 'ALL ENGINE/DECOMPRESSION/LRC',
                ]],
                'eent_coordinates' => 'N40 31.1 W131 22.6',
                'eexp_coordinates' => 'N45 19.3 E151 36.4',
            ],
        ));

        $this->assertNotNull($flightPlan->etops);
        $this->assertTrue($flightPlan->etops->sectionPresent);
        $this->assertSame('unknown', $flightPlan->etops->applicability->value);
        $this->assertSame('N40 31.1', $flightPlan->etops->entryPoint?->coordinate->latitude);
        $this->assertSame('W131 22.6', $flightPlan->etops->entryPoint->coordinate->longitude);
        $this->assertSame('N45 19.3', $flightPlan->etops->exitPoint?->coordinate->latitude);
        $this->assertSame('E151 36.4', $flightPlan->etops->exitPoint->coordinate->longitude);
        $this->assertSame('ETP1', $flightPlan->etops->equalTimePoints[0]->label);
        $this->assertSame('KSFO', $flightPlan->etops->equalTimePoints[0]->firstAlternate?->value);
        $this->assertSame('PACD', $flightPlan->etops->equalTimePoints[0]->secondAlternate?->value);
        $this->assertSame('ALL ENGINE/DECOMPRESSION/LRC', $flightPlan->etops->scenarios[0]->name);
        $this->assertSame('ETP1', $flightPlan->etops->scenarios[0]->equalTimePointLabel);
    }

    public function test_it_omits_malformed_etops_values_instead_of_failing_the_release(): void
    {
        $flightPlan = (new BuildFlightPlanData)->handle($this->partialParsedData(
            etops: [
                'etps' => [[
                    'label' => 'ETP1',
                    'airports' => 'INVALID',
                    'coordinates' => 'N99 00.0 W143 53.1',
                    'scenario' => 'ALL ENGINE/DECOMPRESSION/LRC',
                ]],
                'eent_coordinates' => 'invalid',
                'eexp_coordinates' => null,
            ],
        ));

        $this->assertNull($flightPlan->etops);
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $schedule
     * @param  array<string, mixed>  $route
     * @param  array<string, mixed>  $fuel
     * @param  array<string, mixed>  $etops
     */
    private function partialParsedData(
        array $identity = [],
        array $schedule = [],
        array $route = [],
        array $fuel = [],
        array $etops = [],
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
            etops: $etops,
        );
    }
}
