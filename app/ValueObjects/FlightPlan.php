<?php

namespace App\ValueObjects;

use App\DTOs\AirportData;

readonly class FlightPlan
{
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
    ) {}
}
