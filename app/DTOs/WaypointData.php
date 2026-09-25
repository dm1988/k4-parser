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
        public ?string $tbo = null,
    ) {}

    /**
     * @return array{identifier: string, coordinate: string, legDurationMinutes: ?int, cumulativeDurationMinutes: ?int, remainingFuel: array{amount: float, unit: 'kg'|'lb'}|null, tbo: ?string}
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'coordinate' => $this->coordinate,
            'legDurationMinutes' => $this->legDurationMinutes,
            'cumulativeDurationMinutes' => $this->cumulativeDurationMinutes,
            'remainingFuel' => $this->remainingFuel?->toArray(),
            'tbo' => $this->tbo,
        ];
    }

    /**
     * @return array{identifier: string, coordinate: string, legDurationMinutes: ?int, cumulativeDurationMinutes: ?int, remainingFuel: array{amount: float, unit: 'kg'|'lb'}|null, tbo: ?string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
