<?php

namespace App\View\Presenters\FlightRelease;

use App\DTOs\CrewMemberData;
use App\Enums\CrewPosition;
use App\View\Models\FlightPlanPageData;

final readonly class CrewPresenter
{
    public function __construct(private ?FlightPlanPageData $pageData) {}

    /** @return list<array{name: string, role: ?string, roleBadgeLabel: string, roleBadgeColor: string, details: ?string, employeeNumber: ?string, highMins: bool}> */
    public function maintenanceMembers(): array
    {
        return $this->members();
    }

    /** @return list<array{name: string, role: ?string, roleBadgeLabel: string, roleBadgeColor: string, details: ?string, employeeNumber: ?string, highMins: bool}> */
    public function flightInitMembers(): array
    {
        return $this->members();
    }

    /** @return list<array{name: string, role: ?string, roleBadgeLabel: string, roleBadgeColor: string, details: ?string, employeeNumber: ?string, highMins: bool}> */
    private function members(): array
    {
        return array_map(function (CrewMemberData $member): array {
            $position = CrewPosition::tryFrom($member->role ?? '');

            return [
                'name' => $member->name,
                'role' => $member->role,
                'roleBadgeLabel' => $position?->badgeLabel() ?? $member->role ?? '—',
                'roleBadgeColor' => $position?->badgeColor() ?? CrewPosition::defaultBadgeColor(),
                'details' => $member->base,
                'employeeNumber' => $member->employeeNumber,
                'highMins' => $member->highMins,
            ];
        }, $this->pageData?->flightPlan->crewMembers ?? []);
    }
}
