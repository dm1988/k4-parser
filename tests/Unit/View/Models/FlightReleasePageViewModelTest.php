<?php

namespace Tests\Unit\View\Models;

use App\DTOs\AirportData;
use App\Enums\RouteTokenType;
use App\ValueObjects\FlightPlan;
use App\View\Models\FlightReleasePageViewModel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FlightReleasePageViewModelTest extends TestCase
{
    #[Test]
    public function it_returns_empty_display_values_without_a_flight_plan(): void
    {
        $viewModel = new FlightReleasePageViewModel(null);

        $this->assertFalse($viewModel->hasFlightPlan());
        $this->assertSame('', $viewModel->departure());
        $this->assertSame('', $viewModel->destination());
        $this->assertNull($viewModel->alternate());
        $this->assertSame('', $viewModel->initialAltitude());
        $this->assertSame('', $viewModel->duration());
        $this->assertSame('', $viewModel->route());
    }

    #[Test]
    public function it_builds_airport_display_fields_from_dtos(): void
    {
        $viewModel = new FlightReleasePageViewModel(new FlightPlan(
            departure: 'PANC',
            destination: 'KMIA',
            alternate: 'KRSW',
            departureAirport: new AirportData('PANC', 'ANC', 'Ted Stevens Anchorage International Airport', 'Anchorage', 'Alaska', 'United States'),
            destinationAirport: new AirportData('KMIA', 'MIA', 'Miami International Airport', 'Miami', 'Florida', 'United States'),
            alternateAirport: new AirportData('KRSW', 'RSW', 'Southwest Florida International Airport', 'Fort Myers', 'Florida', 'United States'),
            initialAltitude: 'FL 330',
            duration: '07h12m',
            route: 'DCT TEST',
        ));

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
    }

    #[Test]
    public function it_normalizes_numeric_regions_and_iso_country_codes_for_airport_display(): void
    {
        $viewModel = new FlightReleasePageViewModel(new FlightPlan(
            departure: 'KLAX',
            destination: 'RKSI',
            alternate: 'RKTU',
            departureAirport: null,
            destinationAirport: new AirportData('RKSI', 'ICN', 'Incheon International Airport', 'Seoul', '28', 'KR'),
            alternateAirport: new AirportData('RKTU', 'CJJ', 'Cheongju International Airport', 'Cheongju', '43', 'KR'),
            initialAltitude: '',
            duration: '',
            route: '',
        ));

        $this->assertSame('Seoul, South Korea', $viewModel->destinationAirport()['location']);
        $this->assertSame('Cheongju, South Korea', $viewModel->alternateAirport()['location']);
    }

    #[Test]
    public function it_classifies_route_tokens_for_display(): void
    {
        $viewModel = new FlightReleasePageViewModel(new FlightPlan(
            departure: '',
            destination: '',
            alternate: null,
            departureAirport: null,
            destinationAirport: null,
            alternateAirport: null,
            initialAltitude: '',
            duration: '',
            route: 'DCT OSUDO4A Q139 DSM/N0486F350 GETME',
        ));

        $this->assertSame([
            [
                'value' => 'DCT',
                'type' => RouteTokenType::DIRECT,
                'class' => 'text-[#4A5568]/50',
            ],
            [
                'value' => 'OSUDO4A',
                'type' => RouteTokenType::FIX,
                'class' => 'text-[#0B0E14]',
            ],
            [
                'value' => 'Q139',
                'type' => RouteTokenType::AIRWAY,
                'class' => 'font-bold text-[#1B365D]',
            ],
            [
                'value' => 'DSM/N0486F350',
                'type' => RouteTokenType::SPEED,
                'class' => 'text-amber-700',
            ],
            [
                'value' => 'GETME',
                'type' => RouteTokenType::FIX,
                'class' => 'text-[#0B0E14]',
            ],
        ], $viewModel->routeTokens());
    }

    #[Test]
    public function it_builds_airport_display_fields_from_session_arrays(): void
    {
        session([
            'flight_plan' => [
                'departure' => 'PANC',
                'destination' => 'KMIA',
                'alternate' => null,
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
                'etps' => [
                    [
                        'label' => 'ETP1',
                        'airports' => 'KSFO-PACD',
                        'coordinates' => 'N45 43.7 W143 53.1',
                        'scenario' => 'ALL ENGINE/DECOMPRESSION/LRC',
                    ],
                ],
                'eent_coordinates' => 'N40 31.1 W131 22.6',
                'eexp_coordinates' => 'N45 19.3 E151 36.4',
                'initial_altitude' => 'FL 330',
                'duration' => '07h12m',
                'route' => 'DCT TEST',
            ],
        ]);

        $viewModel = FlightReleasePageViewModel::fromCurrentSession();

        $this->assertSame('None listed', $viewModel->alternateLabel());
        $this->assertNull($viewModel->destinationAirport());
        $this->assertNull($viewModel->alternateAirport());
        $this->assertSame('No alternate airport listed.', $viewModel->alternateAirportFallback());
        $this->assertSame('Ted Stevens Anchorage International Airport', $viewModel->departureAirport()['name']);
        $this->assertSame('ANC', $viewModel->departureAirport()['iata']);
        $this->assertSame('PANC', $viewModel->departureAirport()['icao']);
        $this->assertTrue($viewModel->hasEtopsData());
        $this->assertSame('KSFO-PACD', $viewModel->etps()[0]['airports']);
        $this->assertSame(['KSFO', 'PACD'], $viewModel->etpAirports($viewModel->etps()[0]));
        $this->assertSame('N40 31.1 W131 22.6', $viewModel->eentCoordinates());
        $this->assertSame('N45 19.3 E151 36.4', $viewModel->eexpCoordinates());
    }

    #[Test]
    public function it_reads_the_flight_plan_from_the_current_session(): void
    {
        session([
            'flight_plan' => [
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
                'initial_altitude' => 'FL 330',
                'duration' => '07h12m',
                'route' => 'DCT TEST',
            ],
        ]);

        $viewModel = FlightReleasePageViewModel::fromCurrentSession();

        $this->assertTrue($viewModel->hasFlightPlan());
        $this->assertSame('PANC', $viewModel->departure());
        $this->assertSame('KRSW', $viewModel->alternate());
        $this->assertSame('Ted Stevens Anchorage International Airport', $viewModel->departureAirport()['name']);
    }

    #[Test]
    public function it_reports_missing_alternate_airport_details_when_an_alternate_code_exists(): void
    {
        $viewModel = new FlightReleasePageViewModel(new FlightPlan(
            departure: '',
            destination: '',
            alternate: 'KRSW',
            departureAirport: null,
            destinationAirport: null,
            alternateAirport: null,
            initialAltitude: '',
            duration: '',
            route: '',
        ));

        $this->assertSame('Airport details unavailable.', $viewModel->alternateAirportFallback());
    }
}
