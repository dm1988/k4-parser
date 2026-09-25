<?php

namespace App\Services\FlightPlan;

use App\DTOs\WeightBalance\AircraftWeightLimits;
use App\Models\Aircraft;
use App\ValueObjects\WeightQuantity;
use Illuminate\Support\Str;

class AircraftWeightLimitResolver
{
    public function resolve(?string $tailNumber): AircraftWeightLimits
    {
        if ($tailNumber === null || trim($tailNumber) === '') {
            return new AircraftWeightLimits;
        }

        $aircraft = Aircraft::query()
            ->byTailNumber(Str::upper(trim($tailNumber)))
            ->first([
                'max_ramp_weight',
                'max_zero_fuel_weight',
                'max_takeoff_weight',
                'max_landing_weight',
            ]);

        return new AircraftWeightLimits(
            ramp: $this->pounds($aircraft?->max_ramp_weight),
            zeroFuel: $this->pounds($aircraft?->max_zero_fuel_weight),
            takeoff: $this->pounds($aircraft?->max_takeoff_weight),
            landing: $this->pounds($aircraft?->max_landing_weight),
        );
    }

    private function pounds(mixed $amount): ?WeightQuantity
    {
        return is_int($amount) && $amount > 0
            ? WeightQuantity::pounds($amount)
            : null;
    }
}
