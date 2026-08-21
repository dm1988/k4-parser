<?php

namespace App\DTOs;

use JsonSerializable;

final readonly class FlightPlanData implements JsonSerializable
{
    public function __construct(
        public FlightIdentityData $identity,
        public ScheduleData $schedule,
        public RouteData $route,
        public ?FuelPlanData $fuelPlan = null,
    ) {}

    /**
     * @return array{
     *     identity: array<string, string|null>,
     *     schedule: array<string, string|list<string>|null>,
     *     route: array<string, int|string|null>,
     *     fuelPlan: array<string, array{amount: float, unit: 'kg'|'lb'}|null>|null
     * }
     */
    public function toArray(): array
    {
        return [
            'identity' => $this->identity->toArray(),
            'schedule' => $this->schedule->toArray(),
            'route' => $this->route->toArray(),
            'fuelPlan' => $this->fuelPlan?->toArray(),
        ];
    }

    /**
     * @return array{
     *     identity: array<string, string|null>,
     *     schedule: array<string, string|list<string>|null>,
     *     route: array<string, int|string|null>,
     *     fuelPlan: array<string, array{amount: float, unit: 'kg'|'lb'}|null>|null
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
