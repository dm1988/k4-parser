<?php

namespace Tests\Feature\Livewire\FlightPlanBrief;

use App\DTOs\AirportData;
use App\DTOs\CrewManifestInputData;
use App\DTOs\Maintenance\MaintenanceInputData;
use App\DTOs\ParsedFlightPlanData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\Expectation;
use Mockery\ExpectationInterface;
use Mockery\MockInterface;
use Mockery\VerificationDirector;
use Tests\TestCase;

abstract class FlightPlanBriefTestCase extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    protected function flightPlan(): array
    {
        return [
            'departure' => 'PANC',
            'destination' => 'KMIA',
            'alternate' => 'KRSW',
            'departure_airport' => new AirportData('PANC', 'ANC', 'Ted Stevens Anchorage International Airport', 'Anchorage', 'Alaska', 'United States'),
            'destination_airport' => new AirportData('KMIA', 'MIA', 'Miami International Airport', 'Miami', 'Florida', 'United States'),
            'alternate_airport' => new AirportData('KRSW', 'RSW', 'Southwest Florida International Airport', 'Fort Myers', 'Florida', 'United States'),
            'departure_runway' => '25R',
            'arrival_runway' => '33R',
            'departure_sid' => 'SUMMR2 SCTRR',
            'arrival_star' => 'GUKDO GUKD2E',
            'etps' => [[
                'label' => 'ETP1',
                'airports' => 'KSFO-PACD',
                'coordinates' => 'N45 43.7 W143 53.1',
                'scenario' => 'ALL ENGINE/DECOMPRESSION/LRC',
            ]],
            'eent_coordinates' => 'N40 31.1 W131 22.6',
            'eexp_coordinates' => 'N45 19.3 E151 36.4',
            'initial_altitude' => 'FL 330',
            'duration' => '07h12m',
            'route' => 'DCT Q139 TEST',
        ];
    }

    /** @param array<string, mixed>|null $extractedRouteData */
    protected function parsedFlightPlan(
        ?array $extractedRouteData = null,
        ?array $identity = null,
        ?array $schedule = null,
        ?array $route = null,
        ?array $fuel = null,
        ?array $crewMembers = null,
        ?array $maintenance = null,
        ?array $takeoffLandingReport = null,
        ?array $flightInit = null,
        ?array $etops = null,
        ?array $waypoints = null,
        ?array $weather = null,
        ?array $weightBalance = null,
        ?array $generalDeclaration = null,
        ?array $releaseAuthorization = null,
        ?array $dispatcherNotes = null,
        array $sourceFragments = [],
    ): ParsedFlightPlanData {
        $extractedRouteData ??= $this->flightPlan();

        $routeData = [
            'departure' => (string) $extractedRouteData['departure'],
            'destination' => (string) $extractedRouteData['destination'],
            'alternate' => is_string($extractedRouteData['alternate'] ?? null) ? $extractedRouteData['alternate'] : null,
            'departure_airport' => ($extractedRouteData['departure_airport'] ?? null) instanceof AirportData
                ? $extractedRouteData['departure_airport']
                : null,
            'destination_airport' => ($extractedRouteData['destination_airport'] ?? null) instanceof AirportData
                ? $extractedRouteData['destination_airport']
                : null,
            'alternate_airport' => ($extractedRouteData['alternate_airport'] ?? null) instanceof AirportData
                ? $extractedRouteData['alternate_airport']
                : null,
            'route' => is_string($extractedRouteData['route'] ?? null) ? $extractedRouteData['route'] : null,
            'departure_runway' => is_string($extractedRouteData['departure_runway'] ?? null) ? $extractedRouteData['departure_runway'] : null,
            'arrival_runway' => is_string($extractedRouteData['arrival_runway'] ?? null) ? $extractedRouteData['arrival_runway'] : null,
            'departure_sid' => is_string($extractedRouteData['departure_sid'] ?? null) ? $extractedRouteData['departure_sid'] : null,
            'arrival_star' => is_string($extractedRouteData['arrival_star'] ?? null) ? $extractedRouteData['arrival_star'] : null,
            'distance_nautical_miles' => null,
        ];

        return new ParsedFlightPlanData(
            identity: $identity ?? [
                'flight_number' => null,
                'trip_number' => null,
                'recall_number' => null,
                'aircraft_type' => null,
                'tail_number' => null,
                'flight_date' => null,
                'release_revision' => null,
            ],
            schedule: $schedule ?? [
                'etd_utc' => null,
                'eta_utc' => null,
                'block_duration' => is_string($extractedRouteData['duration'] ?? null) ? $extractedRouteData['duration'] : null,
                'report_time_utc' => null,
                'duty_end_utc' => null,
                'slot_times_utc' => [],
            ],
            route: [...$routeData, ...($route ?? [])],
            fuel: $fuel ?? array_fill_keys([
                'ramp', 'taxi', 'takeoff', 'trip', 'contingency', 'alternate', 'final_reserve', 'estimated_landing',
            ], null),
            crewMembers: new CrewManifestInputData($crewMembers ?? []),
            maintenance: MaintenanceInputData::fromExtracted($maintenance ?? [
                'section_present' => false,
                'items' => [],
            ]),
            takeoffLandingReport: $takeoffLandingReport ?? [],
            flightInit: $flightInit ?? [
                'filed_initial_altitude' => is_string($extractedRouteData['initial_altitude'] ?? null) ? $extractedRouteData['initial_altitude'] : null,
            ],
            etops: [
                'section_present' => is_array($extractedRouteData['etps'] ?? null) && $extractedRouteData['etps'] !== [],
                'applicability' => is_array($extractedRouteData['etps'] ?? null) && $extractedRouteData['etps'] !== []
                    ? 'confirmed_etops'
                    : 'unknown',
                ...($etops ?? []),
                'etps' => is_array($extractedRouteData['etps'] ?? null) ? $extractedRouteData['etps'] : [],
                'eent_coordinates' => is_string($extractedRouteData['eent_coordinates'] ?? null) ? $extractedRouteData['eent_coordinates'] : null,
                'eexp_coordinates' => is_string($extractedRouteData['eexp_coordinates'] ?? null) ? $extractedRouteData['eexp_coordinates'] : null,
            ],
            weather: $weather ?? [],
            weightBalance: $weightBalance ?? [],
            generalDeclaration: $generalDeclaration ?? [],
            releaseAuthorization: $releaseAuthorization ?? [],
            dispatcherNotes: $dispatcherNotes ?? [],
            waypoints: $waypoints ?? [],
            sourceFragments: $sourceFragments,
        );
    }

    /** @phpstan-return Expectation */
    protected function expectOnce(MockInterface $mock, string $method): ExpectationInterface
    {
        return $mock->shouldReceive($method)->once();
    }

    protected function assertReceivedOnce(MockInterface $mock, string $method): VerificationDirector
    {
        return $mock->shouldHaveReceived($method)->once();
    }
}
