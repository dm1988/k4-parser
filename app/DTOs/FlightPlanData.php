<?php

namespace App\DTOs;

use JsonSerializable;

final readonly class FlightPlanData implements JsonSerializable
{
    /** @param list<CrewMemberData> $crewMembers */
    public function __construct(
        public FlightIdentityData $identity,
        public ScheduleData $schedule,
        public RouteData $route,
        public ?FuelPlanData $fuelPlan = null,
        public ?MaintenanceLogData $maintenanceLog = null,
        public ?EnvelopeData $envelope = null,
        public array $crewMembers = [],
    ) {}

    /**
     * @return array{
     *     identity: array<string, string|null>,
     *     schedule: array<string, string|list<string>|null>,
     *     route: array<string, int|string|null>,
     *     fuelPlan: array<string, array{amount: float, unit: 'kg'|'lb'}|null>|null,
     *     maintenanceLog: array{sectionPresent: bool, etopsApplicability: string, items: list<array{type: string, number: string, description: string, reference: ?string, status: ?string, limitations: ?string, procedures: ?string}>}|null,
     *     envelope: array<string, mixed>|null,
     *     crewMembers: list<array{name: string, role: ?string, base: ?string}>
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
            'crewMembers' => array_map(
                static fn (CrewMemberData $member): array => $member->toArray(),
                $this->crewMembers,
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
     *     crewMembers: list<array{name: string, role: ?string, base: ?string}>
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
