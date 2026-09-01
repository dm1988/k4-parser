<?php

namespace App\Services\FlightPlan;

use App\DTOs\FlightPlanData;
use App\DTOs\ParsedFlightPlanData;
use App\Services\FlightPlan\Extractor\FlightRouteExtractor;

class FlightPlanResultSerializer
{
    public function __construct(
        private readonly FlightRouteExtractor $routeExtractor,
    ) {}

    /** @return array<string, mixed> */
    public function serialize(FlightPlanData $flightPlan, ParsedFlightPlanData $parsed): array
    {
        return [
            'departure' => $flightPlan->route->departure->value,
            'destination' => $flightPlan->route->destination->value,
            'alternate' => $flightPlan->route->alternate?->value,
            'departure_airport' => $flightPlan->route->departureAirport?->toArray(),
            'destination_airport' => $flightPlan->route->destinationAirport?->toArray(),
            'alternate_airport' => $flightPlan->route->alternateAirport?->toArray(),
            'departure_runway' => $flightPlan->route->departureRunway,
            'arrival_runway' => $flightPlan->route->arrivalRunway,
            'departure_sid' => $flightPlan->route->departureSid,
            'arrival_star' => $flightPlan->route->arrivalStar,
            'etps' => $parsed->etops['etps'] ?? [],
            'eent_coordinates' => $parsed->etops['eent_coordinates'] ?? null,
            'eexp_coordinates' => $parsed->etops['eexp_coordinates'] ?? null,
            'initial_altitude' => $this->legacyInitialAltitude($parsed->flightInit['filed_initial_altitude'] ?? null),
            'duration' => $flightPlan->schedule->blockDuration ?? '',
            'route' => $this->routeExtractor->formatForIcaoDisplay($flightPlan->route->route ?? ''),
            'flight_plan_data' => $flightPlan->toArray(),
        ];
    }

    private function legacyInitialAltitude(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return preg_match('/^F(?<level>\d{3,4})$/', $value, $matches) === 1
            ? 'FL '.$matches['level']
            : $value;
    }
}
