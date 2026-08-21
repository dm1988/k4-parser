<?php

namespace App\DTOs;

use App\ValueObjects\FuelQuantity;
use JsonSerializable;

final readonly class FuelPlanData implements JsonSerializable
{
    public function __construct(
        public ?FuelQuantity $ramp = null,
        public ?FuelQuantity $taxi = null,
        public ?FuelQuantity $takeoff = null,
        public ?FuelQuantity $trip = null,
        public ?FuelQuantity $contingency = null,
        public ?FuelQuantity $alternate = null,
        public ?FuelQuantity $finalReserve = null,
        public ?FuelQuantity $estimatedLanding = null,
    ) {}

    /**
     * @return array<string, array{amount: float, unit: 'kg'|'lb'}|null>
     */
    public function toArray(): array
    {
        return [
            'ramp' => $this->ramp?->toArray(),
            'taxi' => $this->taxi?->toArray(),
            'takeoff' => $this->takeoff?->toArray(),
            'trip' => $this->trip?->toArray(),
            'contingency' => $this->contingency?->toArray(),
            'alternate' => $this->alternate?->toArray(),
            'finalReserve' => $this->finalReserve?->toArray(),
            'estimatedLanding' => $this->estimatedLanding?->toArray(),
        ];
    }

    /**
     * @return array<string, array{amount: float, unit: 'kg'|'lb'}|null>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
