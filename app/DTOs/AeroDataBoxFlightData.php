<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;

final readonly class AeroDataBoxFlightData
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

    /** @return array<string, mixed> */
    public function toFlightEventAttributes(int $aircraftId): array
    {
        $durationMinutes = $this->start->diffInMinutes($this->end);

        return [
            'source' => 'aerodatabox',
            'external_id' => $this->externalId,
            'aircraft_id' => $aircraftId,
            'title' => "{$this->flightNumber} {$this->origin}-{$this->destination}",
            'type' => 'flight',
            'start' => $this->start,
            'end' => $this->end,
            'timezone' => 'UTC',
            'metadata' => $this->metadata,
            'type_label' => 'FLIGHT',
            'type_description' => 'AeroDataBox flight data',
            'type_icon' => 'plane',
            'schedule_label' => "{$this->origin}-{$this->destination}",
            'duration_label' => sprintf('%d:%02d', intdiv($durationMinutes, 60), $durationMinutes % 60),
            'tail_number' => $this->tailNumber,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'is_deadhead' => false,
            'badge_color' => $this->badgeColor,
            'flight_number' => $this->flightNumber,
            'status' => $this->status,
        ];
    }
}
