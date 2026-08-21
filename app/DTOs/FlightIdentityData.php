<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;
use JsonSerializable;

final readonly class FlightIdentityData implements JsonSerializable
{
    public function __construct(
        public ?string $flightNumber = null,
        public ?string $tripNumber = null,
        public ?string $aircraftType = null,
        public ?string $tailNumber = null,
        public ?CarbonImmutable $flightDate = null,
        public ?string $releaseRevision = null,
    ) {}

    /**
     * @return array{flightNumber: string|null, tripNumber: string|null, aircraftType: string|null, tailNumber: string|null, flightDate: string|null, releaseRevision: string|null}
     */
    public function toArray(): array
    {
        return [
            'flightNumber' => $this->flightNumber,
            'tripNumber' => $this->tripNumber,
            'aircraftType' => $this->aircraftType,
            'tailNumber' => $this->tailNumber,
            'flightDate' => $this->flightDate?->toDateString(),
            'releaseRevision' => $this->releaseRevision,
        ];
    }

    /**
     * @return array{flightNumber: string|null, tripNumber: string|null, aircraftType: string|null, tailNumber: string|null, flightDate: string|null, releaseRevision: string|null}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
