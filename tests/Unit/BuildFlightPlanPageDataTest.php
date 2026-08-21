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
        $this->assertSame(0.0, $pageData->flightPlan->fuelPlan?->contingency?->amount);
        $this->assertSame('Ted Stevens Anchorage International Airport', $pageData->departureAirport?->name);
        $this->assertSame('FL 340', $pageData->initialAltitude);
        $this->assertSame('12h10m', $pageData->duration);
        $this->assertSame('ETP1', $pageData->etps[0]['label']);
        $this->assertSame('N40 31.1 W131 22.6', $pageData->eentCoordinates);
        $this->assertTrue($pageData->flightPlan->maintenanceLog?->sectionPresent);
        $this->assertSame('28-22-01', $pageData->flightPlan->maintenanceLog->items[0]->number);
        $this->assertSame('Alex Morgan', $pageData->flightPlan->crewMembers[0]->name);
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
            FlightPlanTask::Envelope->value => FlightPlanTaskAvailability::NotSupported,
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
        $payload['flight_plan_data']['schedule']['slotTimesUtc'] = [];
        $payload['flight_plan_data']['fuelPlan'] = null;
        $payload['flight_plan_data']['maintenanceLog'] = null;
        $payload['departure_airport'] = 'invalid';
        $payload['initial_altitude'] = [];
        $payload['etps'] = [['label' => 'incomplete']];
        $payload['eent_coordinates'] = null;
        $payload['eexp_coordinates'] = null;

        $pageData = (new BuildFlightPlanPageData)->handle($payload);

        $this->assertNotNull($pageData);
        $this->assertNull($pageData->flightPlan->fuelPlan);
        $this->assertNull($pageData->departureAirport);
        $this->assertNull($pageData->initialAltitude);
        $this->assertSame([], $pageData->etps);
        $this->assertSame(FlightPlanTaskAvailability::Available, $pageData->availabilityFor(FlightPlanTask::JeppPdPro));
        $this->assertSame(FlightPlanTaskAvailability::NotPresent, $pageData->availabilityFor(FlightPlanTask::SlotTimes));
        $this->assertSame(FlightPlanTaskAvailability::NotPresent, $pageData->availabilityFor(FlightPlanTask::FuelScore));
        $this->assertSame(FlightPlanTaskAvailability::NotPresent, $pageData->availabilityFor(FlightPlanTask::Etops));
        $this->assertSame(FlightPlanTaskAvailability::Available, $pageData->availabilityFor(FlightPlanTask::MaintenanceLog));
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
                'crewMembers' => [[
                    'name' => 'Alex Morgan',
                    'role' => 'CP',
                    'base' => 'YIP',
                ]],
            ],
        ];
    }
}
