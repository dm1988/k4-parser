<?php

namespace App\Services\FlightPlan;

use App\DTOs\AirportData;
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
        $legacy = $parsed->legacy;

        return [
            'departure' => $flightPlan->route->departure->value,
            'destination' => $flightPlan->route->destination->value,
            'alternate' => $flightPlan->route->alternate?->value,
            'departure_airport' => $this->airportArray($legacy['departure_airport'] ?? null),
            'destination_airport' => $this->airportArray($legacy['destination_airport'] ?? null),
            'alternate_airport' => $this->airportArray($legacy['alternate_airport'] ?? null),
            'departure_runway' => $flightPlan->route->departureRunway,
            'arrival_runway' => $flightPlan->route->arrivalRunway,
            'departure_sid' => $flightPlan->route->departureSid,
            'arrival_star' => $flightPlan->route->arrivalStar,
            'etps' => $parsed->etops['etps'] ?? [],
            'eent_coordinates' => $parsed->etops['eent_coordinates'] ?? null,
            'eexp_coordinates' => $parsed->etops['eexp_coordinates'] ?? null,
            'initial_altitude' => $this->legacyInitialAltitude($parsed->flightInit['filed_initial_altitude'] ?? null),
            'duration' => is_string($parsed->schedule['block_duration'] ?? null)
                ? $parsed->schedule['block_duration']
                : '',
            'route' => $this->routeExtractor->formatForIcaoDisplay($flightPlan->route->route ?? ''),
            'flight_plan_data' => $flightPlan->toArray(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function airportArray(mixed $airport): ?array
    {
        if ($airport instanceof AirportData) {
            return $airport->toArray();
        }

        return is_array($airport) ? $airport : null;
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
