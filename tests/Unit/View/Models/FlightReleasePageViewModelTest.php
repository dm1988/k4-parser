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
            ],
        ];
    }
}
