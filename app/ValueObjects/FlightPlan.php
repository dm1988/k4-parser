<?php

namespace App\ValueObjects;

use App\DTOs\AirportData;

readonly class FlightPlan
{
    /**
     * @param  list<array{label: string, airports: string, coordinates: string, scenario: string}>  $etps
     */
    public function __construct(
        public string $departure,
        public string $destination,
        public ?string $alternate,
        public ?AirportData $departureAirport,
        public ?AirportData $destinationAirport,
        public ?AirportData $alternateAirport,
        public string $initialAltitude,
        public string $duration,
        public string $route,
        public ?string $departureRunway = null,
        public ?string $arrivalRunway = null,
        public ?string $departureSid = null,
        public ?string $arrivalStar = null,
        public array $etps = [],
        public ?string $eentCoordinates = null,
        public ?string $eexpCoordinates = null,
    ) {}
}
