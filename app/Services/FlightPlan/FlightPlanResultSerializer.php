<?php

namespace App\Services\FlightPlan;

use App\DTOs\FlightPlanData;

class FlightPlanResultSerializer
{
    /** @return array<string, mixed> */
    public function serialize(FlightPlanData $flightPlan): array
    {
        return [
            'flight_plan_data' => $flightPlan->toArray(),
        ];
    }
}
