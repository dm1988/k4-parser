<?php

namespace App\Services\FlightPlan\Extractor;

use App\DTOs\ParsedFlightPlanData;

class ExtractFlightPlanData
{
    public function __construct(
        private readonly FlightPlanTextExtractor $textExtractor,
        private readonly FlightIdentityExtractor $identityExtractor,
        private readonly FlightScheduleExtractor $scheduleExtractor,
        private readonly FlightRouteExtractor $routeExtractor,
        private readonly FlightFuelExtractor $fuelExtractor,
        private readonly FlightCrewExtractor $crewExtractor,
        private readonly MaintenanceLogExtractor $maintenanceLogExtractor,
        private readonly EnvelopeExtractor $envelopeExtractor,
    ) {}

    public function extractFile(string $filePath): ParsedFlightPlanData
    {
        return $this->extract($this->textExtractor->extract($filePath));
    }

    public function extract(string $text): ParsedFlightPlanData
    {
        $identity = $this->identityExtractor->extract($text);
        $schedule = $this->scheduleExtractor->extract($text, $identity['data']['flight_date']);
        $route = $this->routeExtractor->extractFlightPlanDataFromText($text);
        $fuel = $this->fuelExtractor->extract($text);
        $crew = $this->crewExtractor->extract($text);
        $maintenance = $this->maintenanceLogExtractor->extract($text);
        $envelope = $this->envelopeExtractor->extract($text);

        return new ParsedFlightPlanData(
            identity: $identity['data'],
            schedule: $schedule['data'],
            route: [
                'departure' => $route['departure'],
                'destination' => $route['destination'],
                'alternate' => $route['alternate'],
                'route' => $route['route'],
                'departure_runway' => $route['departure_runway'],
                'arrival_runway' => $route['arrival_runway'],
                'departure_sid' => $route['departure_sid'],
                'arrival_star' => $route['arrival_star'],
                'distance_nautical_miles' => $route['distance_nautical_miles'],
            ],
            fuel: $fuel['data'],
            crewMembers: $crew['data'],
            maintenance: $maintenance['data'],
            envelope: $envelope['data'],
            sourceFragments: [
                ...$identity['source_fragments'],
                ...$schedule['source_fragments'],
                ...$fuel['source_fragments'],
                ...$crew['source_fragments'],
                ...$maintenance['source_fragments'],
                ...$envelope['source_fragments'],
            ],
            legacy: $route,
        );
    }
}
