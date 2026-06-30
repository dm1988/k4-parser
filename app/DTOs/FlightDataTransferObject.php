<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;

abstract readonly class FlightDataTransferObject
{
    abstract public function getSource(): string;

    abstract public function getExternalId(): string;

    abstract public function getTailNumber(): string;

    abstract public function getFlightNumber(): string;

    abstract public function getOrigin(): string;

    abstract public function getDestination(): string;

    abstract public function getStart(): CarbonImmutable;

    abstract public function getEnd(): CarbonImmutable;

    abstract public function getStatus(): string;

    abstract public function getBadgeColor(): string;

    /** @return array<string, mixed> */
    abstract public function getMetadata(): array;

    public function getTypeDescription(): string
    {
        return ucfirst($this->getSource()).' flight data';
    }

    /** @return array<string, mixed> */
    final public function toFlightEventAttributes(int $aircraftId): array
    {
        $start = $this->getStart();
        $end = $this->getEnd();
        $origin = $this->getOrigin();
        $destination = $this->getDestination();
        $flightNumber = $this->getFlightNumber();
        $durationMinutes = $start->diffInMinutes($end);

        return [
            'source' => $this->getSource(),
            'external_id' => $this->getExternalId(),
            'aircraft_id' => $aircraftId,
            'title' => "{$flightNumber} {$origin}-{$destination}",
            'type' => 'flight',
            'start' => $start->toDateTimeString(),
            'end' => $end->toDateTimeString(),
            'timezone' => 'UTC',
            'metadata' => $this->getMetadata(),
            'type_label' => 'FLIGHT',
            'type_description' => $this->getTypeDescription(),
            'type_icon' => 'plane',
            'schedule_label' => "{$origin}-{$destination}",
            'duration_label' => sprintf('%d:%02d', intdiv($durationMinutes, 60), $durationMinutes % 60),
            'tail_number' => $this->getTailNumber(),
            'origin' => $origin,
            'destination' => $destination,
            'is_deadhead' => false,
            'badge_color' => $this->getBadgeColor(),
            'flight_number' => $flightNumber,
            'status' => $this->getStatus(),
        ];
    }
}
