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
