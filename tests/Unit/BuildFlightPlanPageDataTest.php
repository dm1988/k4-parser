<?php

namespace Tests\Unit;

use App\Actions\BuildFlightPlanPageData;
use App\Enums\FlightPlanTask;
use App\Enums\FlightPlanTaskAvailability;
use PHPUnit\Framework\TestCase;

class BuildFlightPlanPageDataTest extends TestCase
{
    public function test_it_builds_typed_page_data_from_the_normalized_contract_and_legacy_supplements(): void
    {
        $pageData = (new BuildFlightPlanPageData)->handle($this->resultPayload());

        $this->assertNotNull($pageData);
        $this->assertSame('CKS256', $pageData->flightPlan->identity->flightNumber);
        $this->assertSame('2026-05-25', $pageData->flightPlan->identity->flightDate?->toDateString());
        $this->assertSame('2026-05-25T02:20:00+00:00', $pageData->flightPlan->schedule->etdUtc);
        $this->assertSame('PANC', $pageData->flightPlan->route->departure->value);
        $this->assertSame('DCT Q139 TEST', $pageData->flightPlan->route->route);
        $this->assertSame(5549, $pageData->flightPlan->route->distanceNauticalMiles);
        $this->assertSame(200, $pageData->flightPlan->fuelPlan?->costIndex);
        $this->assertSame(0.0, $pageData->flightPlan->fuelPlan?->contingency?->amount);
        $this->assertSame('Ted Stevens Anchorage International Airport', $pageData->departureAirport?->name);
        $this->assertSame(34000, $pageData->flightPlan->flightInit?->filedInitialAltitude?->value);
        $this->assertSame(29000, $pageData->flightPlan->flightInit?->fmsInitialAltitude?->value);
        $this->assertSame('12h10m', $pageData->duration);
        $this->assertSame('ETP1', $pageData->flightPlan->etops?->equalTimePoints[0]->label);
        $this->assertSame('N40 31.1', $pageData->flightPlan->etops->entryPoint?->coordinate->latitude);
        $this->assertTrue($pageData->flightPlan->maintenanceLog?->sectionPresent);
        $this->assertSame('28-22-01', $pageData->flightPlan->maintenanceLog->items[0]->number);
        $this->assertSame('Alex Morgan', $pageData->flightPlan->crewMembers[0]->name);
        $this->assertSame('4827', $pageData->flightPlan->crewMembers[0]->employeeNumber);
        $this->assertSame('11', $pageData->flightPlan->flightInit?->acarsInitDate);
        $this->assertSame(612400, $pageData->flightPlan->envelope?->plannedTakeoffWeight?->amount);
        $this->assertSame(1015, $pageData->flightPlan->envelope->qnhHectopascals);
        $this->assertSame(['FIX01', 'FIX01'], array_column($pageData->flightPlan->waypoints, 'identifier'));
        $this->assertSame(11, $pageData->flightPlan->waypoints[0]->cumulativeDurationMinutes);
        $this->assertSame(0.0, $pageData->flightPlan->waypoints[0]->remainingFuel?->amount);
    }

    public function test_normalized_core_values_take_precedence_over_conflicting_flat_compatibility_values(): void
    {
        $payload = $this->resultPayload();
        $payload['departure'] = 'XXXX';
        $payload['destination'] = 'YYYY';
        $payload['alternate'] = null;
        $payload['departure_runway'] = '01';
        $payload['route'] = 'LEGACY ROUTE';

        $pageData = (new BuildFlightPlanPageData)->handle($payload);

        $this->assertNotNull($pageData);
        $this->assertSame('PANC', $pageData->flightPlan->route->departure->value);
        $this->assertSame('KMIA', $pageData->flightPlan->route->destination->value);
        $this->assertSame('KRSW', $pageData->flightPlan->route->alternate?->value);
        $this->assertSame('25R', $pageData->flightPlan->route->departureRunway);
        $this->assertSame('DCT Q139 TEST', $pageData->flightPlan->route->route);
    }

    public function test_it_reports_availability_for_every_flight_plan_task(): void
    {
        $pageData = (new BuildFlightPlanPageData)->handle($this->resultPayload());

        $this->assertNotNull($pageData);
        $this->assertSame([
            FlightPlanTask::Overview->value => FlightPlanTaskAvailability::Available,
            FlightPlanTask::JeppPdPro->value => FlightPlanTaskAvailability::Available,
            FlightPlanTask::MaintenanceLog->value => FlightPlanTaskAvailability::Available,
            FlightPlanTask::Envelope->value => FlightPlanTaskAvailability::Available,
            FlightPlanTask::FlightInit->value => FlightPlanTaskAvailability::Available,
            FlightPlanTask::Fms->value => FlightPlanTaskAvailability::Available,
            FlightPlanTask::SlotTimes->value => FlightPlanTaskAvailability::Available,
            FlightPlanTask::FuelScore->value => FlightPlanTaskAvailability::Available,
            FlightPlanTask::Etops->value => FlightPlanTaskAvailability::Available,
            FlightPlanTask::Weather->value => FlightPlanTaskAvailability::NotSupported,
            FlightPlanTask::WeightAndBalance->value => FlightPlanTaskAvailability::NotSupported,
        ], $pageData->taskAvailability());
    }

    public function test_it_preserves_a_sparse_normalized_result_and_ignores_malformed_optional_legacy_data(): void
    {
        $payload = $this->resultPayload();
        $payload['flight_plan_data']['schedule']['slots'] = [];
        $payload['flight_plan_data']['schedule']['slotTimesUtc'] = [];
        $payload['flight_plan_data']['fuelPlan'] = null;
        $payload['flight_plan_data']['waypoints'] = [];
        $payload['flight_plan_data']['maintenanceLog'] = null;
        $payload['flight_plan_data']['envelope'] = null;
        unset($payload['flight_plan_data']['flightInit']);
        unset($payload['flight_plan_data']['crewMembers'][0]['employeeNumber']);
        $payload['departure_airport'] = 'invalid';
        $payload['initial_altitude'] = [];
        $payload['flight_plan_data']['etops'] = null;
        $payload['etps'] = $this->legacyEtps();
        $payload['eent_coordinates'] = 'N40 31.1 W131 22.6';
        $payload['eexp_coordinates'] = 'N45 19.3 E151 36.4';

        $pageData = (new BuildFlightPlanPageData)->handle($payload);

        $this->assertNotNull($pageData);
        $this->assertNull($pageData->flightPlan->fuelPlan);
        $this->assertNull($pageData->flightPlan->flightInit);
        $this->assertNull($pageData->flightPlan->crewMembers[0]->employeeNumber);
        $this->assertNull($pageData->departureAirport);
        $this->assertNull($pageData->flightPlan->flightInit?->filedInitialAltitude);
        $this->assertNull($pageData->flightPlan->flightInit?->fmsInitialAltitude);
        $this->assertNull($pageData->flightPlan->etops);
        $this->assertSame(FlightPlanTaskAvailability::Available, $pageData->availabilityFor(FlightPlanTask::JeppPdPro));
        $this->assertSame(FlightPlanTaskAvailability::NotPresent, $pageData->availabilityFor(FlightPlanTask::SlotTimes));
        $this->assertSame(FlightPlanTaskAvailability::NotPresent, $pageData->availabilityFor(FlightPlanTask::FuelScore));
        $this->assertSame(FlightPlanTaskAvailability::NotPresent, $pageData->availabilityFor(FlightPlanTask::Etops));
        $this->assertSame(FlightPlanTaskAvailability::Available, $pageData->availabilityFor(FlightPlanTask::MaintenanceLog));
        $this->assertSame(FlightPlanTaskAvailability::NotPresent, $pageData->availabilityFor(FlightPlanTask::Envelope));
    }

    public function test_it_keeps_maintenance_context_available_without_a_dedicated_item_section(): void
    {
        $payload = $this->resultPayload();
        $payload['flight_plan_data']['maintenanceLog']['sectionPresent'] = false;
        $payload['flight_plan_data']['maintenanceLog']['items'] = [];

        $pageData = (new BuildFlightPlanPageData)->handle($payload);

        $this->assertNotNull($pageData);
        $this->assertSame(FlightPlanTaskAvailability::Available, $pageData->availabilityFor(FlightPlanTask::MaintenanceLog));
    }

    public function test_it_keeps_a_partial_entry_only_etops_section_available(): void
    {
        $payload = $this->resultPayload();
        $payload['flight_plan_data']['etops'] = [
            'sectionPresent' => true,
            'applicability' => 'unknown',
            'entryPoint' => [
                'label' => 'EENT',
                'coordinate' => ['latitude' => 'N40 31.1', 'longitude' => 'W131 22.6'],
                'sequence' => 0,
            ],
            'exitPoint' => null,
            'equalTimePoints' => [],
            'alternates' => [],
            'scenarios' => [],
        ];

        $pageData = (new BuildFlightPlanPageData)->handle($payload);

        $this->assertNotNull($pageData);
        $this->assertSame([], $pageData->flightPlan->etops?->equalTimePoints);
        $this->assertNotNull($pageData->flightPlan->etops->entryPoint);
        $this->assertTrue($pageData->hasEtopsData());
        $this->assertSame(FlightPlanTaskAvailability::Available, $pageData->availabilityFor(FlightPlanTask::Etops));
    }

    public function test_it_distinguishes_an_unsupported_envelope_result_from_an_absent_section(): void
    {
        $payload = $this->resultPayload();
        $payload['flight_plan_data']['envelope'] = [
            'sectionPresent' => true,
            'sourceType' => 'takeoff_landing_report',
            'plannedTakeoffWeight' => null,
        ];

        $pageData = (new BuildFlightPlanPageData)->handle($payload);

        $this->assertNotNull($pageData);
        $this->assertSame(
            FlightPlanTaskAvailability::NotSupported,
            $pageData->availabilityFor(FlightPlanTask::Envelope),
        );
    }

    public function test_it_fails_closed_for_missing_or_malformed_normalized_payloads(): void
    {
        $builder = new BuildFlightPlanPageData;

        $this->assertNull($builder->handle(null));
        $this->assertNull($builder->handle(['departure' => 'PANC']));
        $this->assertNull($builder->handle(['flight_plan_data' => 'invalid']));
        $this->assertNull($builder->handle([
            'flight_plan_data' => [
                'identity' => [],
                'schedule' => [],
                'route' => ['departure' => 'PANC'],
            ],
        ]));
    }

    /** @return array<string, mixed> */
    private function resultPayload(): array
    {
        return [
            'departure' => 'PANC',
            'destination' => 'KMIA',
            'alternate' => 'KRSW',
            'departure_airport' => [
                'icao' => 'PANC',
                'iata' => 'ANC',
                'name' => 'Ted Stevens Anchorage International Airport',
                'city' => 'Anchorage',
                'state' => 'Alaska',
                'country' => 'United States',
            ],
            'destination_airport' => null,
            'alternate_airport' => null,
            'departure_runway' => '25R',
            'arrival_runway' => '27',
            'route' => "DCT Q139\n TEST",
            'initial_altitude' => 'FL 340',
            'duration' => '12h10m',
            'etps' => [[
                'label' => 'ETP1',
                'airports' => 'KSFO-PACD',
                'coordinates' => 'N45 43.7 W143 53.1',
                'scenario' => 'ALL ENGINE/DECOMPRESSION/LRC',
            ]],
            'eent_coordinates' => 'N40 31.1 W131 22.6',
            'eexp_coordinates' => null,
            'flight_plan_data' => [
                'identity' => [
                    'flightNumber' => 'CKS256',
                    'tripNumber' => '109546',
                    'recallNumber' => '62930',
                    'aircraftType' => 'B777-200F',
                    'tailNumber' => 'N774CK',
                    'flightDate' => '2026-05-25',
                    'releaseRevision' => null,
                ],
                'schedule' => [
                    'etdUtc' => '2026-05-25T02:20:00+00:00',
                    'etdLocal' => null,
                    'etaUtc' => '2026-05-25T14:50:00+00:00',
                    'etaLocal' => null,
                    'blockDuration' => null,
                    'reportTimeUtc' => null,
                    'reportTimeLocal' => null,
                    'dutyEndUtc' => null,
                    'dutyEndLocal' => null,
                    'slots' => [[
                        'direction' => 'departure',
                        'airport' => 'PANC',
                        'instantUtc' => '2026-05-25T15:20:00+00:00',
                        'sourceTime' => '1520Z',
                        'toleranceMinutes' => 30,
                    ]],
                    'slotTimesUtc' => ['2026-05-25T15:20:00+00:00'],
                    'slotTimesLocal' => [],
                ],
                'route' => [
                    'departure' => 'PANC',
                    'destination' => 'KMIA',
                    'alternate' => 'KRSW',
                    'route' => 'DCT Q139 TEST',
                    'departureRunway' => '25R',
                    'arrivalRunway' => '27',
                    'departureSid' => 'SUMMR2',
                    'arrivalStar' => 'FROGZ5',
                    'distanceNauticalMiles' => 5549,
                ],
                'fuelPlan' => [
                    'costIndex' => 200,
                    'ramp' => ['amount' => 216800.0, 'unit' => 'lb'],
                    'taxi' => ['amount' => 2000.0, 'unit' => 'lb'],
                    'takeoff' => ['amount' => 214800.0, 'unit' => 'lb'],
                    'trip' => ['amount' => 195100.0, 'unit' => 'lb'],
                    'contingency' => ['amount' => 0.0, 'unit' => 'lb'],
                    'alternate' => ['amount' => 5600.0, 'unit' => 'lb'],
                    'finalReserve' => ['amount' => 6900.0, 'unit' => 'lb'],
                    'estimatedLanding' => ['amount' => 19700.0, 'unit' => 'lb'],
                ],
                'maintenanceLog' => [
                    'sectionPresent' => true,
                    'etopsApplicability' => 'confirmed_etops',
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
                'envelope' => [
                    'sectionPresent' => true,
                    'sourceType' => 'takeoff_landing_report',
                    'reportReference' => 'TLR-30 SEQ-48273190 25MAY26 0115Z',
                    'airport' => 'KLAX',
                    'plannedRunway' => '25R',
                    'outsideAirTemperatureCelsius' => 18.0,
                    'wind' => '250M08',
                    'qnhInchesMercury' => null,
                    'qnhHectopascals' => 1015,
                    'maximumRunwayTakeoffWeight' => ['amount' => 768000, 'unit' => 'lb'],
                    'flapSetting' => '15',
                    'antiIce' => false,
                    'v1Knots' => 151,
                    'rotateKnots' => 158,
                    'v2Knots' => 164,
                    'plannedTakeoffWeight' => ['amount' => 612400, 'unit' => 'lb'],
                    'maximumFieldTakeoffWeight' => ['amount' => 766000, 'unit' => 'lb'],
                    'sourceWarnings' => ['Source warning'],
                ],
                'flightInit' => [
                    'sectionPresent' => true,
                    'acarsInitDate' => '11',
                    'filedInitialAltitude' => [
                        'value' => 34000,
                        'unit' => 'feet',
                        'isFlightLevel' => true,
                    ],
                    'fmsInitialAltitude' => [
                        'value' => 29000,
                        'unit' => 'feet',
                        'isFlightLevel' => true,
                    ],
                ],
                'etops' => [
                    'sectionPresent' => true,
                    'applicability' => 'unknown',
                    'entryPoint' => [
                        'label' => 'EENT',
                        'coordinate' => ['latitude' => 'N40 31.1', 'longitude' => 'W131 22.6'],
                        'sequence' => 0,
                    ],
                    'exitPoint' => null,
                    'equalTimePoints' => [[
                        'label' => 'ETP1',
                        'coordinate' => ['latitude' => 'N45 43.7', 'longitude' => 'W143 53.1'],
                        'sequence' => 1,
                        'firstAlternate' => 'KSFO',
                        'secondAlternate' => 'PACD',
                    ]],
                    'alternates' => [],
                    'scenarios' => [[
                        'name' => 'ALL ENGINE/DECOMPRESSION/LRC',
                        'equalTimePointLabel' => 'ETP1',
                        'diversion' => null,
                        'criticalFuel' => null,
                        'remarks' => null,
                    ]],
                ],
                'crewMembers' => [[
                    'name' => 'Alex Morgan',
                    'role' => 'CP',
                    'base' => 'YIP',
                    'employeeNumber' => '4827',
                ]],
                'waypoints' => [[
                    'identifier' => 'FIX01',
                    'coordinate' => 'N01 02.3 E004 05.6',
                    'legDurationMinutes' => 5,
                    'cumulativeDurationMinutes' => 11,
                    'remainingFuel' => ['amount' => 0.0, 'unit' => 'lb'],
                ], [
                    'identifier' => 'FIX01',
                    'coordinate' => 'N02 03.4 E005 06.7',
                    'legDurationMinutes' => null,
                    'cumulativeDurationMinutes' => null,
                    'remainingFuel' => null,
                ]],
            ],
        ];
    }

    /** @return list<array{label: string, airports: string, coordinates: string, scenario: string}> */
    private function legacyEtps(): array
    {
        return [[
            'label' => 'ETP1',
            'airports' => 'KSFO-PACD',
            'coordinates' => 'N45 43.7 W143 53.1',
            'scenario' => 'ALL ENGINE/DECOMPRESSION/LRC',
        ]];
    }
}
