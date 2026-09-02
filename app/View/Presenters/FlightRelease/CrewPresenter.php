<?php

namespace App\View\Presenters\FlightRelease;

use App\DTOs\CrewMemberData;
use App\View\Models\FlightPlanPageData;

final readonly class CrewPresenter
{
    public function __construct(private ?FlightPlanPageData $pageData) {}

    /** @return list<array{name: string, details: ?string, highMins: bool}> */
    public function maintenanceMembers(): array
    {
        return array_map(function (CrewMemberData $member): array {
            $details = $this->details($member);

            return [
                'name' => $member->name,
                'details' => $details,
                'highMins' => $member->highMins,
            ];
        }, $this->pageData?->flightPlan->crewMembers ?? []);
    }

    /** @return list<array{name: string, details: ?string, employeeNumber: ?string, highMins: bool}> */
    public function flightInitMembers(): array
    {
        return array_map(fn (CrewMemberData $member): array => [
            'name' => $member->name,
            'details' => $this->details($member),
            'employeeNumber' => $member->employeeNumber,
            'highMins' => $member->highMins,
        ], $this->pageData?->flightPlan->crewMembers ?? []);
    }

    private function details(CrewMemberData $member): ?string
    {
        $details = array_values(array_filter([
            $member->role,
            $member->base,
        ], static fn (?string $value): bool => $value !== null));

        return $details === [] ? null : implode(' · ', $details);
    }
}
