<?php

namespace App\DTOs\WeightBalance;

use App\ValueObjects\WeightQuantity;

final readonly class AircraftWeightLimits
{
    public function __construct(
        public ?WeightQuantity $ramp = null,
        public ?WeightQuantity $zeroFuel = null,
        public ?WeightQuantity $takeoff = null,
        public ?WeightQuantity $landing = null,
    ) {}
}
