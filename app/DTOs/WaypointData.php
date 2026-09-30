<?php

namespace App\DTOs;

use App\Enums\WaypointKind;
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
        public ?string $displayLabel = null,
        public WaypointKind $kind = WaypointKind::Fix,
    ) {}

    /**
     * @return array{identifier: string, coordinate: string, legDurationMinutes: ?int, cumulativeDurationMinutes: ?int, remainingFuel: array{amount: float, unit: 'kg'|'lb'}|null, tbo: ?string, displayLabel: string, kind: string}
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
            'displayLabel' => $this->label(),
            'kind' => $this->kind->value,
        ];
    }

    public function label(): string
    {
        return $this->displayLabel ?? $this->identifier;
    }

    /**
     * @return array{identifier: string, coordinate: string, legDurationMinutes: ?int, cumulativeDurationMinutes: ?int, remainingFuel: array{amount: float, unit: 'kg'|'lb'}|null, tbo: ?string, displayLabel: string, kind: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
