<?php

namespace Tests\Unit\View\Models;

use App\Actions\BuildFlightPlanPageData;
use App\Enums\FlightPlanTask;
use App\Enums\FlightPlanTaskAvailability;
use App\Enums\RouteTokenType;
use App\View\Models\FlightReleasePageViewModel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FlightReleasePageViewModelTest extends TestCase
{
    #[Test]
    public function it_returns_empty_display_values_without_page_data(): void
    {
        $viewModel = new FlightReleasePageViewModel(null);

        $this->assertFalse($viewModel->hasFlightPlan());
        $this->assertSame('', $viewModel->departure());
        $this->assertSame('', $viewModel->destination());
        $this->assertNull($viewModel->alternate());
        $this->assertSame('', $viewModel->initialAltitude());
        $this->assertSame('', $viewModel->duration());
        $this->assertSame('', $viewModel->route());
        $this->assertSame([], $viewModel->taskAvailability());
        $this->assertNull($viewModel->flightNumber());
        $this->assertNull($viewModel->flightDate());
        $this->assertNull($viewModel->aircraftType());
        $this->assertNull($viewModel->tailNumber());
        $this->assertNull($viewModel->tripNumber());
        $this->assertNull($viewModel->recallNumber());
        $this->assertNull($viewModel->etdUtc());
        $this->assertNull($viewModel->etaUtc());
        $this->assertNull($viewModel->releaseRevision());
        $this->assertNull($viewModel->overviewEtdUtc());
        $this->assertNull($viewModel->overviewEtaUtc());
        $this->assertNull($viewModel->overviewInitialAltitude());
        $this->assertNull($viewModel->overviewRouteDistance());
        $this->assertNull($viewModel->overviewRampFuel());
        $this->assertNull($viewModel->overviewSlotSummary());
        $this->assertNull($viewModel->overviewEtopsSummary());
        $this->assertNull($viewModel->fmsDistanceToDestination());
        $this->assertNull($viewModel->fmsAlternateReserve());
        $this->assertSame([], $viewModel->fmsFields());
        $this->assertSame('Not confirmed', $viewModel->maintenanceEtopsLabel());
        $this->assertNull($viewModel->maintenanceDate());
        $this->assertNull($viewModel->maintenanceRampFuel());
        $this->assertSame('Estimated ramp fuel', $viewModel->maintenanceRampFuelLabel());
        $this->assertSame('0 source-listed items', $viewModel->maintenanceItemCountLabel());
        $this->assertNull($viewModel->maintenanceTypeSummary());
        $this->assertNull($viewModel->maintenanceStatusSummary());
        $this->assertSame([], $viewModel->maintenanceItems());
        $this->assertSame([], $viewModel->crewMembers());
        $this->assertNull($viewModel->flightInitEtdUtc());
        $this->assertNull($viewModel->flightInitRampFuel());
        $this->assertNull($viewModel->flightInitAcarsDate());
        $this->assertSame([], $viewModel->flightInitCrewMembers());
        $this->assertSame('Confirmed release section', $viewModel->envelopeSourceLabel());
        $this->assertNull($viewModel->envelopePlannedTakeoffWeight());
        $this->assertSame([], $viewModel->envelopeWarnings());
    }

    #[Test]
    public function it_builds_route_and_airport_display_values_from_typed_page_data(): void
    {
        $viewModel = $this->viewModel($this->resultPayload());

        $this->assertTrue($viewModel->hasFlightPlan());
        $this->assertSame('PANC', $viewModel->departure());
        $this->assertSame('KMIA', $viewModel->destination());
        $this->assertSame('KRSW', $viewModel->alternate());
        $this->assertSame('KRSW', $viewModel->alternateLabel());
        $this->assertSame('Ted Stevens Anchorage International Airport', $viewModel->departureAirport()['name']);
        $this->assertSame('Anchorage, Alaska, United States', $viewModel->departureAirport()['location']);
        $this->assertSame('ANC', $viewModel->departureAirport()['iata']);
        $this->assertSame('PANC', $viewModel->departureAirport()['icao']);
        $this->assertSame('FL 330', $viewModel->initialAltitude());
        $this->assertSame('07h12m', $viewModel->duration());
        $this->assertSame('DCT TEST', $viewModel->route());
        $this->assertSame('25R', $viewModel->departureRunway());
        $this->assertSame('SUMMR2', $viewModel->departureSid());
        $this->assertTrue($viewModel->hasPlannedRunways());
    }

    #[Test]
    public function it_builds_source_backed_fms_fields_with_explicit_units(): void
    {
        $payload = $this->resultPayload();
        $payload['flight_plan_data']['identity']['recallNumber'] = '62930';
        $payload['flight_plan_data']['route']['distanceNauticalMiles'] = 5549;
        $payload['flight_plan_data']['fuelPlan'] = [
            'ramp' => null,
            'taxi' => null,
            'takeoff' => null,
            'trip' => null,
            'contingency' => null,
            'alternate' => ['amount' => 5600.0, 'unit' => 'lb'],
            'finalReserve' => null,
            'estimatedLanding' => null,
        ];

        $viewModel = $this->viewModel($payload);

        $this->assertSame('62930', $viewModel->recallNumber());
        $this->assertSame('5,549 NM', $viewModel->fmsDistanceToDestination());
        $this->assertSame('5,600 LB', $viewModel->fmsAlternateReserve());
        $this->assertSame([
            ['label' => 'Flight Number', 'value' => 'CKS241'],
            ['label' => 'AC Type', 'value' => 'B777-200F'],
            ['label' => 'RECALL Number', 'value' => '62930'],
            ['label' => 'Distance to Destination', 'value' => '5,549 NM'],
            ['label' => 'Initial Altitude', 'value' => 'FL 330'],
            ['label' => 'Planned Duration', 'value' => '07h12m'],
            ['label' => 'Alternate Airport Reserves', 'value' => '5,600 LB'],
        ], $viewModel->fmsFields());
    }

    #[Test]
    public function it_hides_a_non_five_digit_recall_number_from_fms_presentation(): void
    {
        $payload = $this->resultPayload();
        $payload['flight_plan_data']['identity']['recallNumber'] = '5678';

        $viewModel = $this->viewModel($payload);

        $this->assertNull($viewModel->recallNumber());
        $this->assertNull($viewModel->fmsFields()[2]['value']);
    }

    #[Test]
    public function it_preserves_a_zero_kilogram_alternate_reserve_for_fms(): void
    {
        $payload = $this->resultPayload();
        $payload['flight_plan_data']['fuelPlan'] = [
            'ramp' => null,
            'taxi' => null,
            'takeoff' => null,
            'trip' => null,
            'contingency' => null,
            'alternate' => ['amount' => 0.0, 'unit' => 'kg'],
            'finalReserve' => null,
            'estimatedLanding' => null,
        ];

        $viewModel = $this->viewModel($payload);

        $this->assertSame('0 KG', $viewModel->fmsAlternateReserve());
        $this->assertSame('0 KG', $viewModel->fmsFields()[6]['value']);
    }

    #[Test]
    public function it_formats_the_compact_release_header_from_confirmed_typed_values(): void
    {
        $payload = $this->resultPayload();
        $payload['flight_plan_data']['identity']['releaseRevision'] = '3';
        $payload['flight_plan_data']['schedule']['etdUtc'] = '2026-05-25 18:30';
        $payload['flight_plan_data']['schedule']['etaUtc'] = '2026-05-26 02:15';

        $viewModel = $this->viewModel($payload);

        $this->assertSame('CKS241', $viewModel->flightNumber());
        $this->assertSame('May 25, 2026', $viewModel->flightDate());
        $this->assertSame('B777-200F', $viewModel->aircraftType());
        $this->assertSame('N774CK', $viewModel->tailNumber());
        $this->assertSame('2026-05-25 18:30', $viewModel->etdUtc());
        $this->assertSame('2026-05-26 02:15', $viewModel->etaUtc());
        $this->assertSame('3', $viewModel->releaseRevision());
    }

    #[Test]
    public function it_builds_complete_source_backed_overview_values_with_units(): void
    {
        $payload = $this->resultPayload();
        $payload['flight_plan_data']['schedule']['etdUtc'] = '2026-05-25T18:30:00+00:00';
        $payload['flight_plan_data']['schedule']['etaUtc'] = '2026-05-26T02:15:00Z';
        $payload['flight_plan_data']['schedule']['slotTimesUtc'] = [
            '2026-05-25T18:45:00+00:00',
            '2026-05-26T02:30:00+00:00',
        ];
        $payload['flight_plan_data']['fuelPlan'] = [
            'ramp' => ['amount' => 120000.0, 'unit' => 'lb'],
            'taxi' => null,
            'takeoff' => null,
            'trip' => null,
            'contingency' => null,
            'alternate' => null,
            'finalReserve' => null,
            'estimatedLanding' => null,
        ];

        $viewModel = $this->viewModel($payload);

        $this->assertSame('May 25, 2026 · 1830Z', $viewModel->overviewEtdUtc());
        $this->assertSame('May 26, 2026 · 0215Z', $viewModel->overviewEtaUtc());
        $this->assertSame('FL 330', $viewModel->overviewInitialAltitude());
        $this->assertSame('4,000 NM', $viewModel->overviewRouteDistance());
        $this->assertSame('120,000 LB', $viewModel->overviewRampFuel());
        $this->assertSame('2 approved UTC slots', $viewModel->overviewSlotSummary());
        $this->assertSame('1 critical point · EENT · EEXP', $viewModel->overviewEtopsSummary());
        $this->assertSame([
            ['label' => 'GENDEC', 'availability' => FlightPlanTaskAvailability::NotSupported],
            ['label' => 'Flight plan filing', 'availability' => FlightPlanTaskAvailability::NotSupported],
            ['label' => 'Weather / RAIM', 'availability' => FlightPlanTaskAvailability::NotSupported],
            ['label' => 'Maintenance', 'availability' => FlightPlanTaskAvailability::Available],
        ], $viewModel->overviewUnsupportedIndicators());
    }

    #[Test]
    public function it_formats_maintenance_context_items_statuses_and_crew_from_typed_data(): void
    {
        $payload = $this->resultPayload();
        $payload['flight_plan_data']['identity']['tripNumber'] = '109546';
        $payload['flight_plan_data']['fuelPlan'] = [
            'ramp' => ['amount' => 216800.0, 'unit' => 'lb'],
            'taxi' => null,
            'takeoff' => null,
            'trip' => null,
            'contingency' => null,
            'alternate' => null,
            'finalReserve' => null,
            'estimatedLanding' => null,
        ];

        $viewModel = $this->viewModel($payload);

        $this->assertSame('109546', $viewModel->tripNumber());
        $this->assertSame('05 25 26', $viewModel->maintenanceDate());
        $this->assertSame('Yes', $viewModel->maintenanceEtopsLabel());
        $this->assertSame('216.8', $viewModel->maintenanceRampFuel());
        $this->assertSame('Estimated ramp fuel (1,000 LB)', $viewModel->maintenanceRampFuelLabel());
        $this->assertSame('2 source-listed items', $viewModel->maintenanceItemCountLabel());
        $this->assertSame('1 MEL · 1 CDL', $viewModel->maintenanceTypeSummary());
        $this->assertSame('1 OPEN · 1 DEFERRED', $viewModel->maintenanceStatusSummary());
        $this->assertSame('28-22-01', $viewModel->maintenanceItems()[0]['number']);
        $this->assertTrue($viewModel->maintenanceItems()[0]['copyable']);
        $this->assertTrue($viewModel->maintenanceItems()[1]['copyable']);
        $this->assertSame('CP · YIP', $viewModel->crewMembers()[0]['details']);
    }

    #[Test]
    public function it_does_not_offer_copying_for_dmi_item_numbers(): void
    {
        $payload = $this->resultPayload();
        $payload['flight_plan_data']['maintenanceLog']['items'][] = [
            'type' => 'DMI',
            'number' => 'DMI-2099',
            'description' => 'Source-listed inspection item.',
            'reference' => null,
            'status' => null,
            'limitations' => null,
            'procedures' => null,
        ];

        $items = $this->viewModel($payload)->maintenanceItems();

        $this->assertFalse($items[2]['copyable']);
    }

    #[Test]
    public function it_formats_flight_init_fields_and_employee_numbers_from_typed_data(): void
    {
        $payload = $this->resultPayload();
        $payload['flight_plan_data']['schedule']['etdUtc'] = '2026-05-25T02:20:00+00:00';
        $payload['flight_plan_data']['fuelPlan'] = [
            'ramp' => ['amount' => 225500.0, 'unit' => 'lb'],
            'taxi' => null,
            'takeoff' => null,
            'trip' => null,
            'contingency' => null,
            'alternate' => null,
            'finalReserve' => null,
            'estimatedLanding' => null,
        ];
        $payload['flight_plan_data']['flightInit'] = [
            'sectionPresent' => true,
            'acarsInitDate' => '11',
        ];
        $payload['flight_plan_data']['crewMembers'][0]['employeeNumber'] = '4827';

        $viewModel = $this->viewModel($payload);

        $this->assertSame('0220Z', $viewModel->flightInitEtdUtc());
        $this->assertSame('225,500 LB', $viewModel->flightInitRampFuel());
        $this->assertSame('11', $viewModel->flightInitAcarsDate());
        $this->assertSame('4827', $viewModel->flightInitCrewMembers()[0]['employeeNumber']);
        $this->assertSame('CP · YIP', $viewModel->flightInitCrewMembers()[0]['details']);
        $this->assertSame([
            'flight-init-tail-number',
            'flight-init-etd',
            'flight-init-ramp-fuel',
            'flight-init-flight-number',
            'flight-init-departure',
            'flight-init-destination',
            'flight-init-acars-init-date',
        ], array_column($viewModel->flightInitFields(), 'id'));
    }

    #[Test]
    public function it_formats_the_confirmed_envelope_result_without_calculating_a_status(): void
    {
        $viewModel = $this->viewModel($this->resultPayload());

        $this->assertSame('Takeoff and Landing Report', $viewModel->envelopeSourceLabel());
        $this->assertSame('TLR-30 SEQ-48273190 25MAY26 0115Z', $viewModel->envelopeReportReference());
        $this->assertSame('KLAX', $viewModel->envelopeAirport());
        $this->assertSame('25R', $viewModel->envelopePlannedRunway());
        $this->assertSame('18.0 °C', $viewModel->envelopeOutsideAirTemperature());
        $this->assertSame('250M08', $viewModel->envelopeWind());
        $this->assertSame('29.92 inHg', $viewModel->envelopeQnh());
        $this->assertSame('15', $viewModel->envelopeFlapSetting());
        $this->assertSame('No', $viewModel->envelopeAntiIce());
        $this->assertSame('768,000 LB', $viewModel->envelopeMaximumRunwayTakeoffWeight());
        $this->assertSame('766,000 LB', $viewModel->envelopeMaximumFieldTakeoffWeight());
        $this->assertSame('612,400 LB', $viewModel->envelopePlannedTakeoffWeight());
        $this->assertSame('151 kt', $viewModel->envelopeV1());
        $this->assertSame('158 kt', $viewModel->envelopeRotateSpeed());
        $this->assertSame('164 kt', $viewModel->envelopeV2());
        $this->assertSame(['32-41-03 - SOURCE BRAKE MESSAGE'], $viewModel->envelopeWarnings());
    }

    #[Test]
    public function it_formats_source_qnh_hectopascals_without_converting_units(): void
    {
        $payload = $this->resultPayload();
        $payload['flight_plan_data']['envelope']['qnhInchesMercury'] = null;
        $payload['flight_plan_data']['envelope']['qnhHectopascals'] = 1015;

        $this->assertSame('1015 hPa', $this->viewModel($payload)->envelopeQnh());
    }

    #[Test]
    public function it_keeps_legitimate_zero_kilogram_ramp_fuel_distinct_from_sparse_overview_values(): void
    {
        $payload = $this->resultPayload();
        $payload['initial_altitude'] = null;
        $payload['etps'] = [];
        $payload['eent_coordinates'] = null;
        $payload['eexp_coordinates'] = null;
        $payload['flight_plan_data']['schedule']['etdUtc'] = 'not-a-confirmed-utc-instant';
        $payload['flight_plan_data']['route']['alternate'] = null;
        $payload['flight_plan_data']['route']['distanceNauticalMiles'] = null;
        $payload['flight_plan_data']['fuelPlan'] = [
            'ramp' => ['amount' => 0.0, 'unit' => 'kg'],
            'taxi' => null,
            'takeoff' => null,
            'trip' => null,
            'contingency' => null,
            'alternate' => null,
            'finalReserve' => null,
            'estimatedLanding' => null,
        ];

        $viewModel = $this->viewModel($payload);

        $this->assertNull($viewModel->alternate());
        $this->assertNull($viewModel->overviewEtdUtc());
        $this->assertNull($viewModel->overviewEtaUtc());
        $this->assertNull($viewModel->overviewInitialAltitude());
        $this->assertNull($viewModel->overviewRouteDistance());
        $this->assertSame('0 KG', $viewModel->overviewRampFuel());
        $this->assertSame('0.0', $viewModel->maintenanceRampFuel());
        $this->assertSame('Estimated ramp fuel (1,000 KG)', $viewModel->maintenanceRampFuelLabel());
        $this->assertNull($viewModel->overviewSlotSummary());
        $this->assertNull($viewModel->overviewEtopsSummary());
    }

    #[Test]
    public function it_normalizes_numeric_regions_and_iso_country_codes_for_airport_display(): void
    {
        $payload = $this->resultPayload();
        $payload['destination_airport'] = [
            'icao' => 'KMIA',
            'iata' => 'MIA',
            'name' => 'Miami International Airport',
            'city' => 'Miami',
            'state' => '12',
            'country' => 'US',
        ];

        $viewModel = $this->viewModel($payload);

        $this->assertSame('Miami, United States', $viewModel->destinationAirport()['location']);
    }

    #[Test]
    public function it_classifies_normalized_route_tokens_for_display(): void
    {
        $payload = $this->resultPayload();
        $payload['flight_plan_data']['route']['route'] = 'DCT OSUDO4A Q139 DSM/N0486F350 GETME';
        $payload['route'] = 'CONFLICTING LEGACY ROUTE';

        $viewModel = $this->viewModel($payload);

        $this->assertSame([
            ['value' => 'DCT', 'type' => RouteTokenType::DIRECT, 'class' => 'text-[#4A5568]/50 dark:text-slate-400'],
            ['value' => 'OSUDO4A', 'type' => RouteTokenType::FIX, 'class' => 'text-[#0B0E14] dark:text-slate-100'],
            ['value' => 'Q139', 'type' => RouteTokenType::AIRWAY, 'class' => 'font-bold text-[#1B365D] dark:text-sky-300'],
            ['value' => 'DSM/N0486F350', 'type' => RouteTokenType::SPEED, 'class' => 'text-amber-700 dark:text-amber-300'],
            ['value' => 'GETME', 'type' => RouteTokenType::FIX, 'class' => 'text-[#0B0E14] dark:text-slate-100'],
        ], $viewModel->routeTokens());
    }

    #[Test]
    public function it_adapts_legacy_etops_fields_without_exposing_raw_arrays_to_blade(): void
    {
        $viewModel = $this->viewModel($this->resultPayload());

        $this->assertTrue($viewModel->hasEtopsData());
        $this->assertSame('KSFO-PACD', $viewModel->etps()[0]['airports']);
        $this->assertSame(['KSFO', 'PACD'], $viewModel->etpAirports($viewModel->etps()[0]));
        $this->assertSame('N40 31.1 W131 22.6', $viewModel->eentCoordinates());
        $this->assertSame(FlightPlanTaskAvailability::Available, $viewModel->availabilityFor(FlightPlanTask::Etops));
    }

    #[Test]
    public function it_reports_missing_alternate_airport_details_without_losing_the_normalized_code(): void
    {
        $payload = $this->resultPayload();
        $payload['alternate_airport'] = null;

        $viewModel = $this->viewModel($payload);

        $this->assertSame('KRSW', $viewModel->alternate());
        $this->assertNull($viewModel->alternateAirport());
        $this->assertSame('Airport details unavailable.', $viewModel->alternateAirportFallback());
    }

    /** @param array<string, mixed> $payload */
    private function viewModel(array $payload): FlightReleasePageViewModel
    {
        $pageData = (new BuildFlightPlanPageData)->handle($payload);
        $this->assertNotNull($pageData);

        return new FlightReleasePageViewModel($pageData);
    }

    /** @return array<string, mixed> */
    private function resultPayload(): array
    {
        return [
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
            'initial_altitude' => 'FL 330',
            'duration' => '07h12m',
            'route' => "DCT\n TEST",
            'etps' => [[
                'label' => 'ETP1',
                'airports' => 'KSFO-PACD',
                'coordinates' => 'N45 43.7 W143 53.1',
                'scenario' => 'ALL ENGINE/DECOMPRESSION/LRC',
            ]],
            'eent_coordinates' => 'N40 31.1 W131 22.6',
            'eexp_coordinates' => 'N45 19.3 E151 36.4',
            'flight_plan_data' => [
                'identity' => [
                    'flightNumber' => 'CKS241',
                    'tripNumber' => null,
                    'recallNumber' => null,
                    'aircraftType' => 'B777-200F',
                    'tailNumber' => 'N774CK',
                    'flightDate' => '2026-05-25',
                    'releaseRevision' => null,
                ],
                'schedule' => [
                    'etdUtc' => null,
                    'etaUtc' => null,
                    'blockDuration' => null,
                    'reportTimeUtc' => null,
                    'dutyEndUtc' => null,
                    'slotTimesUtc' => [],
                ],
                'route' => [
                    'departure' => 'PANC',
                    'destination' => 'KMIA',
                    'alternate' => 'KRSW',
                    'route' => 'DCT TEST',
                    'departureRunway' => '25R',
                    'arrivalRunway' => '27',
                    'departureSid' => 'SUMMR2',
                    'arrivalStar' => 'FROGZ5',
                    'distanceNauticalMiles' => 4000,
                ],
                'fuelPlan' => null,
                'maintenanceLog' => [
                    'sectionPresent' => true,
                    'etopsApplicability' => 'confirmed_etops',
                    'items' => [
                        [
                            'type' => 'MEL',
                            'number' => '28-22-01',
                            'description' => 'Center tank override pump inoperative.',
                            'reference' => '1042',
                            'status' => 'OPEN',
                            'limitations' => null,
                            'procedures' => null,
                        ],
                        [
                            'type' => 'CDL',
                            'number' => '52-10-02',
                            'description' => 'Forward cargo door fairing segment missing.',
                            'reference' => null,
                            'status' => 'DEFERRED',
                            'limitations' => 'Source-listed limitation.',
                            'procedures' => 'Source-listed procedure.',
                        ],
                    ],
                ],
                'envelope' => [
                    'sectionPresent' => true,
                    'sourceType' => 'takeoff_landing_report',
                    'reportReference' => 'TLR-30 SEQ-48273190 25MAY26 0115Z',
                    'airport' => 'KLAX',
                    'plannedRunway' => '25R',
                    'outsideAirTemperatureCelsius' => 18.0,
                    'wind' => '250M08',
                    'qnhInchesMercury' => 29.92,
                    'qnhHectopascals' => null,
                    'maximumRunwayTakeoffWeight' => ['amount' => 768000, 'unit' => 'lb'],
                    'flapSetting' => '15',
                    'antiIce' => false,
                    'v1Knots' => 151,
                    'rotateKnots' => 158,
                    'v2Knots' => 164,
                    'plannedTakeoffWeight' => ['amount' => 612400, 'unit' => 'lb'],
                    'maximumFieldTakeoffWeight' => ['amount' => 766000, 'unit' => 'lb'],
                    'sourceWarnings' => ['32-41-03 - SOURCE BRAKE MESSAGE'],
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
