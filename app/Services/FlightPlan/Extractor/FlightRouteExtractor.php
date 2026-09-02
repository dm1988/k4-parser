<?php

namespace App\Services\FlightPlan\Extractor;

use App\DTOs\AirportData;
use App\Exceptions\FlightPlanDataConflictException;
use App\Exceptions\FlightRouteNotFoundException;
use App\Services\Clients\AirportLookupClient;

class FlightRouteExtractor
{
    private const FLIGHT_PLAN_DETAILS_PATTERN = '/\(FPL-[^-]+-[^-]+\s*-[^\r\n]*\s*-([A-Z]{4})\d{4}\s*-(?:N\d{4}|K\d{4}|M\d{3})([A-Z]\d{3,4})\h+(.+?)\s*-([A-Z]{4})(\d{4})(?:\h+([A-Z]{4}))?\b/s';

    public function __construct(
        private readonly FlightPlanTextExtractor $textExtractor,
        private readonly AirportLookupClient $airportLookupClient,
    ) {}

    /**
     * @throws FlightRouteNotFoundException
     */
    public function extractRoute(string $filePath): string
    {
        return $this->extractRouteFromText($this->textExtractor->extract($filePath));
    }

    /**
     * @return array{
     *     departure: string,
     *     destination: string,
     *     alternate: ?string,
     *     departure_airport: ?AirportData,
     *     destination_airport: ?AirportData,
     *     alternate_airport: ?AirportData,
     *     departure_runway: ?string,
     *     arrival_runway: ?string,
     *     departure_sid: ?string,
     *     arrival_star: ?string,
     *     distance_nautical_miles: ?int,
     *     route: string
     * }
     *
     * @throws FlightRouteNotFoundException
     */
    public function extractFlightPlanData(string $filePath): array
    {
        return $this->extractFlightPlanDataFromText($this->textExtractor->extract($filePath));
    }

    /**
     * @throws FlightRouteNotFoundException
     */
    public function extractRouteFromText(string $text): string
    {
        $flightPlanBlock = $this->extractFlightPlanBlock($text);
        $pattern = '/-(?:N\d{4}|K\d{4}|M\d{3})[A-Z]?\d{3,4}\h+(.+?)\s*-[A-Z]{4}\d{4}\b/s';

        if (! preg_match($pattern, $flightPlanBlock, $matches)) {
            throw FlightRouteNotFoundException::routeSegmentMissing();
        }

        return $this->normalizeExtractedRoute($matches[1]);
    }

    /**
     * @return array{
     *     departure: string,
     *     destination: string,
     *     alternate: ?string,
     *     departure_airport: ?AirportData,
     *     destination_airport: ?AirportData,
     *     alternate_airport: ?AirportData,
     *     departure_runway: ?string,
     *     arrival_runway: ?string,
     *     departure_sid: ?string,
     *     arrival_star: ?string,
     *     distance_nautical_miles: ?int,
     *     route: string
     * }
     *
     * @throws FlightRouteNotFoundException
     */
    public function extractFlightPlanDataFromText(string $text): array
    {
        $flightPlanBlock = $this->extractFlightPlanBlock($text);
        $plannedRunways = $this->extractPlannedRunways($text);

        if (! preg_match(self::FLIGHT_PLAN_DETAILS_PATTERN, $flightPlanBlock, $matches)) {
            throw FlightRouteNotFoundException::routeSegmentMissing();
        }

        $route = $this->normalizeExtractedRoute($matches[3]);

        $departure = $matches[1];
        $destination = $matches[4];
        $alternate = $matches[6] ?? null;

        return [
            'departure' => $departure,
            'destination' => $destination,
            'alternate' => $alternate,
            'departure_airport' => $this->lookupAirport($departure),
            'destination_airport' => $this->lookupAirport($destination),
            'alternate_airport' => $this->lookupAirport($alternate),
            'departure_runway' => $plannedRunways['departure_runway'],
            'arrival_runway' => $plannedRunways['arrival_runway'],
            'departure_sid' => $plannedRunways['departure_sid'],
            'arrival_star' => $plannedRunways['arrival_star'],
            'distance_nautical_miles' => $this->extractDistanceNauticalMiles($text),
            'route' => $route,
        ];
    }

    private function lookupAirport(?string $icao): ?AirportData
    {
        if (! is_string($icao) || $icao === '') {
            return null;
        }

        return $this->airportLookupClient->lookupByIcao($icao);
    }

    /**
     * @return array{
     *     departure_runway: ?string,
     *     arrival_runway: ?string,
     *     departure_sid: ?string,
     *     arrival_star: ?string
     * }
     */
    private function extractPlannedRunways(string $text): array
    {
        $departure = $this->extractPlannedRunwayLine($text, 'DEPT');
        $arrival = $this->extractPlannedRunwayLine($text, 'ARRV');

        return [
            'departure_runway' => $departure['runway'],
            'arrival_runway' => $arrival['runway'],
            'departure_sid' => $departure['procedure'],
            'arrival_star' => $arrival['procedure'],
        ];
    }

    /**
     * @return array{runway: ?string, procedure: ?string}
     */
    private function extractPlannedRunwayLine(string $text, string $type): array
    {
        $pattern = '/PLANNED\s+TO\s+'.preg_quote($type, '/').'\s+RUNWAY:\s*'
            .'(\d{2}[LCR]?)\h*(.*?)'
            .'(?=PLANNED\s+TO\s+(?:DEPT|ARRV)\s+RUNWAY:|\h*\*|\R|$)/i';

        if (preg_match($pattern, $text, $matches) !== 1) {
            return [
                'runway' => null,
                'procedure' => null,
            ];
        }

        $procedure = preg_replace('/\s+/', ' ', trim($matches[2]));

        return [
            'runway' => $matches[1],
            'procedure' => is_string($procedure) && $procedure !== '' ? rtrim($procedure, '.') : null,
        ];
    }

    /**
     * @throws FlightRouteNotFoundException
     */
    private function extractFlightPlanBlock(string $text): string
    {
        if (! preg_match('/\(FPL-.*?\)/s', $text, $matches)) {
            throw FlightRouteNotFoundException::flightPlanBlockMissing();
        }

        return $matches[0];
    }

    public function extractDistanceNauticalMiles(string $text): ?int
    {
        $matches = [];
        preg_match_all('/TOTAL\s+DIST\/DEST\s+(\d{1,5})\b/i', $text, $matches);
        $distances = array_map(static fn (string $distance): int => (int) $distance, $matches[1]);

        if (count(array_unique($distances)) > 1) {
            throw FlightPlanDataConflictException::forField('route distance');
        }

        return $distances[0] ?? null;
    }

    private static function normalizeRouteLine(string $line): string
    {
        return preg_replace('/\h+/', ' ', trim($line)) ?? '';
    }

    /**
     * @throws FlightRouteNotFoundException
     */
    private function normalizeExtractedRoute(string $routeText): string
    {
        $lines = preg_split('/\R/', trim($routeText));

        if ($lines === false) {
            throw FlightRouteNotFoundException::routeSegmentEmpty();
        }

        $normalizedLines = array_values(array_filter(array_map(
            static fn (string $line): string => self::normalizeRouteLine($line),
            $lines,
        )));

        if ($normalizedLines === []) {
            throw FlightRouteNotFoundException::routeSegmentEmpty();
        }

        return implode(PHP_EOL, $normalizedLines);
    }
}
