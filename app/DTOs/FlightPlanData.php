<?php

namespace App\DTOs;

use App\DTOs\Etops\EtopsData;
use App\DTOs\Weather\WeatherData;
use App\DTOs\WeightBalance\WeightBalanceData;
use JsonSerializable;

final readonly class FlightPlanData implements JsonSerializable
{
    /**
     * @param  list<CrewMemberData>  $crewMembers
     * @param  list<WaypointData>  $waypoints
     */
    public function __construct(
        public FlightIdentityData $identity,
        public ScheduleData $schedule,
        public RouteData $route,
        public ?FuelPlanData $fuelPlan = null,
        public ?MaintenanceLogData $maintenanceLog = null,
        public ?EnvelopeData $envelope = null,
        public ?FlightInitData $flightInit = null,
        public ?EtopsData $etops = null,
        public ?WeatherData $weather = null,
        public ?WeightBalanceData $weightBalance = null,
        public GeneralDeclarationData $generalDeclaration = new GeneralDeclarationData(false),
        public array $crewMembers = [],
        public array $waypoints = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'identity' => $this->identity->toArray(),
            'schedule' => $this->schedule->toArray(),
            'route' => $this->route->toArray(),
            'fuelPlan' => $this->fuelPlan?->toArray(),
            'maintenanceLog' => $this->maintenanceLog?->toArray(),
            'envelope' => $this->envelope?->toArray(),
            'flightInit' => $this->flightInit?->toArray(),
            'etops' => $this->etops?->toArray(),
            'weather' => $this->weather?->toArray(),
            'weightBalance' => $this->weightBalance?->toArray(),
            'generalDeclaration' => $this->generalDeclaration->toArray(),
            'crewMembers' => array_map(
                static fn (CrewMemberData $member): array => $member->toArray(),
                $this->crewMembers,
            ),
            'waypoints' => array_map(
                static fn (WaypointData $waypoint): array => $waypoint->toArray(),
                $this->waypoints,
            ),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
