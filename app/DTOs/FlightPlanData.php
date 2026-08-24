<?php

namespace App\DTOs;

use App\DTOs\Etops\EtopsData;
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
        public array $crewMembers = [],
        public array $waypoints = [],
    ) {}

    /**
     * @return array{
     *     identity: array<string, string|null>,
     *     schedule: array<string, string|list<string>|null>,
     *     route: array<string, int|string|null>,
     *     fuelPlan: array<string, array{amount: float, unit: 'kg'|'lb'}|null>|null,
     *     maintenanceLog: array{sectionPresent: bool, etopsApplicability: string, items: list<array{type: string, number: string, description: string, reference: ?string, status: ?string, limitations: ?string, procedures: ?string}>}|null,
     *     envelope: array<string, mixed>|null,
     *     flightInit: array{sectionPresent: bool, acarsInitDate: ?string, initialAltitude: array{value: int, unit: string, isFlightLevel: bool}|null}|null,
     *     etops: array<string, mixed>|null,
     *     crewMembers: list<array{name: string, role: ?string, base: ?string, employeeNumber: ?string}>,
     *     waypoints: list<array{identifier: string, coordinate: string, legDurationMinutes: ?int, cumulativeDurationMinutes: ?int, remainingFuel: array{amount: float, unit: 'kg'|'lb'}|null}>
     * }
     */
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

    /**
     * @return array{
     *     identity: array<string, string|null>,
     *     schedule: array<string, string|list<string>|null>,
     *     route: array<string, int|string|null>,
     *     fuelPlan: array<string, array{amount: float, unit: 'kg'|'lb'}|null>|null,
     *     maintenanceLog: array{sectionPresent: bool, etopsApplicability: string, items: list<array{type: string, number: string, description: string, reference: ?string, status: ?string, limitations: ?string, procedures: ?string}>}|null,
     *     envelope: array<string, mixed>|null,
     *     flightInit: array{sectionPresent: bool, acarsInitDate: ?string, initialAltitude: array{value: int, unit: string, isFlightLevel: bool}|null}|null,
     *     etops: array<string, mixed>|null,
     *     crewMembers: list<array{name: string, role: ?string, base: ?string, employeeNumber: ?string}>,
     *     waypoints: list<array{identifier: string, coordinate: string, legDurationMinutes: ?int, cumulativeDurationMinutes: ?int, remainingFuel: array{amount: float, unit: 'kg'|'lb'}|null}>
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
