<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;

final readonly class AeroDataBoxFlightData extends FlightDataTransferObject
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $externalId,
        public string $tailNumber,
        public string $flightNumber,
        public string $origin,
        public string $destination,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public string $status,
        public string $badgeColor,
        public array $metadata,
    ) {}

    public function getSource(): string
    {
        return 'aerodatabox';
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getTailNumber(): string
    {
        return $this->tailNumber;
    }

    public function getFlightNumber(): string
    {
        return $this->flightNumber;
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }

    public function getDestination(): string
    {
        return $this->destination;
    }

    public function getStart(): CarbonImmutable
    {
        return $this->start;
    }

    public function getEnd(): CarbonImmutable
    {
        return $this->end;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getBadgeColor(): string
    {
        return $this->badgeColor;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getTypeDescription(): string
    {
        return 'AeroDataBox flight data';
    }
}
