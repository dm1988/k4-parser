<?php

namespace App\Services\FlightPlan\Extractor;

use App\DTOs\ParsedFlightPlanData;
use App\Services\FlightPlan\Extractor\Etops\EtopsQualificationExtractor;

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
        private readonly FlightInitExtractor $flightInitExtractor,
        private readonly WaypointExtractor $waypointExtractor,
        private readonly WeatherExtractor $weatherExtractor,
        private readonly WeightBalanceExtractor $weightBalanceExtractor = new WeightBalanceExtractor,
        private readonly EtopsQualificationExtractor $etopsQualificationExtractor = new EtopsQualificationExtractor,
        private readonly GeneralDeclarationExtractor $generalDeclarationExtractor = new GeneralDeclarationExtractor,
        private readonly ReleaseAuthorizationExtractor $releaseAuthorizationExtractor = new ReleaseAuthorizationExtractor,
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
        $flightInit = $this->flightInitExtractor->extract($text);
        $waypoints = $this->waypointExtractor->extract($text);
        $weather = $this->weatherExtractor->extract($text);
        $weightBalance = $this->weightBalanceExtractor->extract($text);
        $etopsQualification = $this->etopsQualificationExtractor->extract($text);
        $generalDeclaration = $this->generalDeclarationExtractor->extract($text);
        $releaseAuthorization = $this->releaseAuthorizationExtractor->extract($text);

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
            maintenance: [
                ...$maintenance['data'],
                'etops_applicability' => $etopsQualification['data']['applicability'],
            ],
            envelope: $envelope['data'],
            flightInit: [
                ...$flightInit['data'],
                'section_present' => true,
                'filed_initial_altitude' => $route['filed_initial_altitude_source'],
            ],
            etops: [
                ...$etopsQualification['data'],
                'etps' => $route['etps'],
                'eent_coordinates' => $route['eent_coordinates'],
                'eexp_coordinates' => $route['eexp_coordinates'],
            ],
            weather: $weather['data'],
            weightBalance: $weightBalance['data'],
            generalDeclaration: $generalDeclaration['data'],
            releaseAuthorization: $releaseAuthorization['data'],
            waypoints: $waypoints['data'],
            sourceFragments: [
                ...$identity['source_fragments'],
                ...$schedule['source_fragments'],
                ...$fuel['source_fragments'],
                ...$crew['source_fragments'],
                ...$maintenance['source_fragments'],
                ...$envelope['source_fragments'],
                ...$flightInit['source_fragments'],
                'flight_init_filed_initial_altitude' => $route['filed_initial_altitude_source'],
                ...$waypoints['source_fragments'],
                ...$weather['source_fragments'],
                ...$weightBalance['source_fragments'],
                ...$etopsQualification['source_fragments'],
                ...$generalDeclaration['source_fragments'],
                ...$releaseAuthorization['source_fragments'],
            ],
            legacy: $route,
        );
    }
}
