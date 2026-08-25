<?php

namespace App\Services\FlightPlan;

class FuelPlanFieldNormalizer
{
    public function costIndex(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 && $value <= 999 ? $value : null;
    }
}
