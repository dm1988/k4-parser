<?php

namespace App\DTOs;

use App\ValueObjects\AirportCode;
use InvalidArgumentException;
use JsonSerializable;

final readonly class RouteData implements JsonSerializable
{
    public function __construct(
        public AirportCode $departure,
        public AirportCode $destination,
        public ?AirportCode $alternate = null,
        public ?string $route = null,
        public ?string $departureRunway = null,
        public ?string $arrivalRunway = null,
        public ?string $departureSid = null,
        public ?string $arrivalStar = null,
        public ?int $distanceNauticalMiles = null,
    ) {
        if ($distanceNauticalMiles !== null && $distanceNauticalMiles < 0) {
            throw new InvalidArgumentException('Route distance must not be negative.');
        }
    }

    /**
     * @return array{departure: string, destination: string, alternate: string|null, route: string|null, departureRunway: string|null, arrivalRunway: string|null, departureSid: string|null, arrivalStar: string|null, distanceNauticalMiles: int|null}
     */
    public function toArray(): array
    {
        return [
            'departure' => $this->departure->value,
            'destination' => $this->destination->value,
            'alternate' => $this->alternate?->value,
            'route' => $this->route,
            'departureRunway' => $this->departureRunway,
            'arrivalRunway' => $this->arrivalRunway,
            'departureSid' => $this->departureSid,
            'arrivalStar' => $this->arrivalStar,
            'distanceNauticalMiles' => $this->distanceNauticalMiles,
        ];
    }

    /**
     * @return array{departure: string, destination: string, alternate: string|null, route: string|null, departureRunway: string|null, arrivalRunway: string|null, departureSid: string|null, arrivalStar: string|null, distanceNauticalMiles: int|null}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
