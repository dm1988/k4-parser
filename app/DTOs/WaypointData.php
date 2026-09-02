<?php

namespace App\DTOs;

use App\ValueObjects\FuelQuantity;
use JsonSerializable;

final readonly class WaypointData implements JsonSerializable
{
    public function __construct(
        public string $identifier,
        public string $coordinate,
        public ?int $legDurationMinutes = null,
        public ?int $cumulativeDurationMinutes = null,
        public ?FuelQuantity $remainingFuel = null,
    ) {}

    /**
     * @return array{identifier: string, coordinate: string, legDurationMinutes: ?int, cumulativeDurationMinutes: ?int, remainingFuel: array{amount: float, unit: 'kg'|'lb'}|null}
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'coordinate' => $this->coordinate,
            'legDurationMinutes' => $this->legDurationMinutes,
            'cumulativeDurationMinutes' => $this->cumulativeDurationMinutes,
            'remainingFuel' => $this->remainingFuel?->toArray(),
        ];
    }

    /**
     * @return array{identifier: string, coordinate: string, legDurationMinutes: ?int, cumulativeDurationMinutes: ?int, remainingFuel: array{amount: float, unit: 'kg'|'lb'}|null}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
