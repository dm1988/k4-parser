<?php

namespace Tests\Unit;

use App\Actions\BuildFlightPlanData;
use App\DTOs\ParsedFlightPlanData;
use App\Enums\OperationsSpecification;
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
                'slots' => [[
                    'direction' => 'departure',
                    'airport' => 'KLAX',
                    'instant_utc' => '2026-05-25T15:20:00+00:00',
                    'source_time' => '1520Z',
                    'tolerance_minutes' => 30,
                ]],
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
                'cost_index' => 200,
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
                'high_mins' => true,
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
                'filed_initial_altitude' => 'S0890',
                'fms_initial_altitude' => 'F290',
            ],
            weather: [
                'departure' => [
                    'airport' => 'KLAX',
                    'metars' => ['METAR KLAX 242153Z 27011KT 10SM FEW020 19/11 A3000'],
                    'tafs' => ['TAF KLAX 241739Z 2418/2524 25007KT P6SM OVC020'],
                ],
                'destination' => [
                    'airport' => 'RKSI',
                    'metars' => ['METAR RKSI 242130Z 10003KT 5000 BR NSC 18/17 Q1012 NOSIG'],
                    'tafs' => [],
                ],
                'alternate' => null,
                'raim' => 'PASSED RAIM REQUIREMENTS FOR PRIMARY NAVIGATION VALID FROM 0200Z TO 0420Z',
            ],
            weightBalance: [
                'basic_operating_weight' => ['amount' => 335858, 'unit' => 'lb', 'status' => 'confirmed'],
                'planned_payload' => ['amount' => 18000, 'unit' => 'lb', 'status' => 'confirmed'],
                'planned_zero_fuel_weight' => ['amount' => 353858, 'unit' => 'lb', 'status' => 'confirmed'],
                'planned_takeoff_gross_weight' => ['amount' => 577347, 'unit' => 'lb', 'status' => 'confirmed'],
                'planned_estimated_landing_weight' => ['amount' => 371893, 'unit' => 'lb', 'status' => 'confirmed'],
            ],
            generalDeclaration: ['section_present' => true],
            releaseAuthorization: ['operations_specification' => 'b44'],
            waypoints: [
                ['identifier' => 'FIX01', 'coordinate' => 'N01 02.3 E004 05.6', 'time' => '005', 'total_time' => '00.11', 'remaining_fuel' => '0000'],
                ['identifier' => 'FIX01', 'coordinate' => 'N02 03.4 E005 06.7', 'time' => null, 'total_time' => null, 'remaining_fuel' => null],
            ],
        );

        $flightPlan = (new BuildFlightPlanData)->handle($parsed);

        $this->assertSame('CKS256', $flightPlan->identity->flightNumber);
        $this->assertSame('62930', $flightPlan->identity->recallNumber);
        $this->assertSame('2026-05-25', $flightPlan->identity->flightDate?->toDateString());
        $this->assertSame('2026-05-25T02:20:00+00:00', $flightPlan->schedule->etdUtc);
        $this->assertSame('departure', $flightPlan->schedule->slots[0]->direction->value);
        $this->assertSame('KLAX', $flightPlan->schedule->slots[0]->airport->value);
        $this->assertSame('2026-05-25T15:20:00+00:00', $flightPlan->schedule->slots[0]->instantUtc->toIso8601String());
        $this->assertSame('1520Z', $flightPlan->schedule->slots[0]->sourceTime);
        $this->assertSame(30, $flightPlan->schedule->slots[0]->toleranceMinutes);
        $this->assertSame('KLAX', $flightPlan->route->departure->value);
        $this->assertSame(5549, $flightPlan->route->distanceNauticalMiles);
        $this->assertSame(216800.0, $flightPlan->fuelPlan?->ramp?->amount);
        $this->assertSame(200, $flightPlan->fuelPlan?->costIndex);
        $this->assertTrue($flightPlan->maintenanceLog?->sectionPresent);
        $this->assertSame('28-22-01', $flightPlan->maintenanceLog->items[0]->number);
        $this->assertSame('Alex Morgan', $flightPlan->crewMembers[0]->name);
        $this->assertSame('4827', $flightPlan->crewMembers[0]->employeeNumber);
        $this->assertTrue($flightPlan->crewMembers[0]->highMins);
        $this->assertSame('11', $flightPlan->flightInit?->acarsInitDate);
        $this->assertSame(8900, $flightPlan->flightInit?->filedInitialAltitude?->value);
        $this->assertSame('meters', $flightPlan->flightInit?->filedInitialAltitude?->unit->value);
        $this->assertTrue($flightPlan->flightInit?->filedInitialAltitude?->isFlightLevel);
        $this->assertSame(29000, $flightPlan->flightInit?->fmsInitialAltitude?->value);
        $this->assertSame('feet', $flightPlan->flightInit?->fmsInitialAltitude?->unit->value);
        $this->assertSame(612400, $flightPlan->envelope->plannedTakeoffWeight?->amount);
        $this->assertNull($flightPlan->envelope->qnhInchesMercury);
        $this->assertSame(1015, $flightPlan->envelope->qnhHectopascals);
        $this->assertFalse($flightPlan->envelope->antiIce);
        $this->assertSame(['Source warning'], $flightPlan->envelope?->sourceWarnings);
        $this->assertSame(['FIX01', 'FIX01'], array_column($flightPlan->waypoints, 'identifier'));
        $this->assertSame(11, $flightPlan->waypoints[0]->cumulativeDurationMinutes);
        $this->assertSame(0.0, $flightPlan->waypoints[0]->remainingFuel?->amount);
        $this->assertSame('lb', $flightPlan->waypoints[0]->remainingFuel?->unit);
        $this->assertNull($flightPlan->waypoints[1]->legDurationMinutes);
        $this->assertSame('KLAX', $flightPlan->weather?->departure?->airport->value);
        $this->assertSame('TAF KLAX 241739Z 2418/2524 25007KT P6SM OVC020', $flightPlan->weather?->departure?->tafs[0]);
        $this->assertSame('RKSI', $flightPlan->weather?->destination?->airport->value);
        $this->assertNull($flightPlan->weather?->alternate);
        $this->assertSame(335858, $flightPlan->weightBalance?->basicOperatingWeight->plannedValue?->amount);
        $this->assertSame(214829, $flightPlan->weightBalance?->plannedTakeoffFuel->plannedValue?->amount);
        $this->assertSame(570658, $flightPlan->weightBalance?->plannedRampWeight->plannedValue?->amount);
        $this->assertTrue($flightPlan->weightBalance?->plannedRampWeight->derived);
        $this->assertTrue($flightPlan->generalDeclaration->sectionPresent);
        $this->assertSame(OperationsSpecification::B44, $flightPlan->releaseAuthorization->operationsSpecification);
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

    public function test_it_withholds_waypoint_fuel_when_release_units_are_ambiguous(): void
    {
        $flightPlan = (new BuildFlightPlanData)->handle($this->partialParsedData(
            fuel: [
                'ramp' => ['amount' => 1000.0, 'unit' => 'lb'],
                'trip' => ['amount' => 500.0, 'unit' => 'kg'],
            ],
            waypoints: [[
                'identifier' => 'FIX01',
                'coordinate' => 'N01 02.3 E004 05.6',
                'time' => '005',
                'total_time' => '00.11',
                'remaining_fuel' => '0000',
            ]],
        ));

        $this->assertNull($flightPlan->waypoints[0]->remainingFuel);
    }

    public function test_it_expands_compact_waypoint_fuel_into_the_confirmed_release_unit(): void
    {
        $flightPlan = (new BuildFlightPlanData)->handle($this->partialParsedData(
            fuel: [
                'ramp' => ['amount' => 162800.0, 'unit' => 'lb'],
            ],
            waypoints: [[
                'identifier' => 'FIX01',
                'coordinate' => 'N01 02.3 E004 05.6',
                'time' => '005',
                'total_time' => '00.11',
                'remaining_fuel' => '1477',
            ]],
        ));

        $this->assertSame(147700.0, $flightPlan->waypoints[0]->remainingFuel?->amount);
        $this->assertSame('lb', $flightPlan->waypoints[0]->remainingFuel?->unit);
    }

    public function test_it_migrates_current_etops_values_without_changing_their_meaning(): void
    {
        $flightPlan = (new BuildFlightPlanData)->handle($this->partialParsedData(
            etops: [
                'section_present' => true,
                'applicability' => 'confirmed_etops',
                'rating_minutes' => 180,
                'etps' => [[
                    'label' => 'ETP1',
                    'airports' => 'KSFO-PACD',
                    'coordinates' => 'N45 43.7 W143 53.1',
                    'scenario' => 'ALL ENGINE/DECOMPRESSION/LRC',
                ], [
                    'label' => 'ETP1',
                    'airports' => 'PACD-RJSS',
                    'coordinates' => 'N47 02.0 W145 36.5',
                    'scenario' => 'ALL ENGINE/DECOMPRESSION/LRC',
                ]],
                'eent_coordinates' => 'N40 31.1 W131 22.6',
                'eexp_coordinates' => 'N45 19.3 E151 36.4',
            ],
        ));

        $this->assertNotNull($flightPlan->etops);
        $this->assertTrue($flightPlan->etops->sectionPresent);
        $this->assertSame('confirmed_etops', $flightPlan->etops->applicability->value);
        $this->assertSame(180, $flightPlan->etops->ratingMinutes);
        $this->assertSame('N40 31.1', $flightPlan->etops->entryPoint?->coordinate->latitude);
        $this->assertSame('W131 22.6', $flightPlan->etops->entryPoint->coordinate->longitude);
        $this->assertSame('N45 19.3', $flightPlan->etops->exitPoint?->coordinate->latitude);
        $this->assertSame('E151 36.4', $flightPlan->etops->exitPoint->coordinate->longitude);
        $this->assertSame('ETP1', $flightPlan->etops->equalTimePoints[0]->label);
        $this->assertSame('ETP1', $flightPlan->etops->equalTimePoints[1]->label);
        $this->assertSame([1, 2], array_column($flightPlan->etops->equalTimePoints, 'sequence'));
        $this->assertSame('N47 02.0', $flightPlan->etops->equalTimePoints[1]->coordinate->latitude);
        $this->assertSame('KSFO', $flightPlan->etops->equalTimePoints[0]->firstAlternate?->value);
        $this->assertSame('PACD', $flightPlan->etops->equalTimePoints[0]->secondAlternate?->value);
        $this->assertSame('ALL ENGINE/DECOMPRESSION/LRC', $flightPlan->etops->scenarios[0]->name);
        $this->assertSame('ETP1', $flightPlan->etops->scenarios[0]->equalTimePointLabel);
    }

    public function test_it_preserves_confirmed_non_etops_without_route_data(): void
    {
        $flightPlan = (new BuildFlightPlanData)->handle($this->partialParsedData(
            etops: [
                'section_present' => true,
                'applicability' => 'confirmed_non_etops',
            ],
        ));

        $this->assertNotNull($flightPlan->etops);
        $this->assertTrue($flightPlan->etops->sectionPresent);
        $this->assertSame('confirmed_non_etops', $flightPlan->etops->applicability->value);
        $this->assertNull($flightPlan->etops->ratingMinutes);
        $this->assertSame([], $flightPlan->etops->equalTimePoints);
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

    public function test_it_defaults_malformed_release_authorization_to_unknown(): void
    {
        foreach (['unsupported', 44, []] as $malformedValue) {
            $flightPlan = (new BuildFlightPlanData)->handle($this->partialParsedData(
                releaseAuthorization: ['operations_specification' => $malformedValue],
            ));

            $this->assertSame(
                OperationsSpecification::Unknown,
                $flightPlan->releaseAuthorization->operationsSpecification,
            );
        }
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
        array $waypoints = [],
        array $releaseAuthorization = [],
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
            waypoints: $waypoints,
            releaseAuthorization: $releaseAuthorization,
        );
    }
}
