<?php

namespace App\Services\FlightPlan\Extractor;

use App\DTOs\CrewManifestInputData;
use App\DTOs\Maintenance\MaintenanceInputData;
use App\DTOs\ParsedFlightPlanData;
use App\Services\FlightPlan\Extractor\Etops\EtopsQualificationExtractor;
use App\Services\FlightPlan\Extractor\Etops\EtopsRouteExtractor;

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
        private readonly TakeoffLandingReportExtractor $takeoffLandingReportExtractor,
        private readonly FlightInitExtractor $flightInitExtractor,
        private readonly WaypointExtractor $waypointExtractor,
        private readonly WeatherExtractor $weatherExtractor,
        private readonly WeightBalanceExtractor $weightBalanceExtractor,
        private readonly EtopsQualificationExtractor $etopsQualificationExtractor,
        private readonly EtopsRouteExtractor $etopsRouteExtractor,
        private readonly GeneralDeclarationExtractor $generalDeclarationExtractor,
        private readonly ReleaseAuthorizationExtractor $releaseAuthorizationExtractor,
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
        $takeoffLandingReport = $this->takeoffLandingReportExtractor->extract($text);
        $flightInit = $this->flightInitExtractor->extract($text);
        $waypoints = $this->waypointExtractor->extract($text);
        $weather = $this->weatherExtractor->extract($text);
        $weightBalance = $this->weightBalanceExtractor->extract($text);
        $etopsQualification = $this->etopsQualificationExtractor->extract($text);
        $etopsRoute = $this->etopsRouteExtractor->extract($text);
        $generalDeclaration = $this->generalDeclarationExtractor->extract($text);
        $releaseAuthorization = $this->releaseAuthorizationExtractor->extract($text);

        return new ParsedFlightPlanData(
            identity: $identity['data'],
            schedule: $schedule['data'],
            route: [
                'departure' => $route['departure'],
                'destination' => $route['destination'],
                'alternate' => $route['alternate'],
                'departure_airport' => $route['departure_airport'],
                'destination_airport' => $route['destination_airport'],
                'alternate_airport' => $route['alternate_airport'],
                'route' => $route['route'],
                'departure_runway' => $route['departure_runway'],
                'arrival_runway' => $route['arrival_runway'],
                'departure_sid' => $route['departure_sid'],
                'arrival_star' => $route['arrival_star'],
                'distance_nautical_miles' => $route['distance_nautical_miles'],
            ],
            fuel: $fuel['data'],
            crewMembers: new CrewManifestInputData($crew['data']),
            maintenance: MaintenanceInputData::fromExtracted($maintenance['data']),
            takeoffLandingReport: $takeoffLandingReport['data'],
            flightInit: $flightInit['data'],
            etops: [
                ...$etopsQualification['data'],
                ...$etopsRoute['data'],
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
                ...$takeoffLandingReport['source_fragments'],
                ...$flightInit['source_fragments'],
                ...$waypoints['source_fragments'],
                ...$weather['source_fragments'],
                ...$weightBalance['source_fragments'],
                ...$etopsQualification['source_fragments'],
                ...$etopsRoute['source_fragments'],
                ...$generalDeclaration['source_fragments'],
                ...$releaseAuthorization['source_fragments'],
            ],
            legacy: $route,
        );
    }
}
