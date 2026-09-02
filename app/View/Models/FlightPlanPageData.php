<?php

namespace App\View\Models;

use App\DTOs\FlightPlanData;
use App\Enums\EtopsApplicability;
use App\Enums\FlightPlanTask;
use App\Enums\FlightPlanTaskAvailability;

final readonly class FlightPlanPageData
{
    public function __construct(
        public FlightPlanData $flightPlan,
    ) {}

    /** @return array<string, FlightPlanTaskAvailability> */
    public function taskAvailability(): array
    {
        $availability = [];

        foreach (FlightPlanTask::cases() as $task) {
            $availability[$task->value] = $this->availabilityFor($task);
        }

        return $availability;
    }

    public function availabilityFor(FlightPlanTask $task): FlightPlanTaskAvailability
    {
        return match ($task) {
            FlightPlanTask::Overview,
            FlightPlanTask::JeppPdPro,
            FlightPlanTask::MaintenanceLog,
            FlightPlanTask::Envelope,
            FlightPlanTask::Fms => FlightPlanTaskAvailability::Available,
            FlightPlanTask::ReviewMelCdl => ($this->flightPlan->maintenanceLog->items ?? []) === []
                ? FlightPlanTaskAvailability::NotPresent
                : FlightPlanTaskAvailability::Available,
            FlightPlanTask::FlightInit => $this->flightPlan->flightInit !== null || $this->flightPlan->crewMembers !== []
                ? FlightPlanTaskAvailability::Available
                : FlightPlanTaskAvailability::NotPresent,
            FlightPlanTask::SlotTimes => $this->flightPlan->schedule->slots === []
                ? FlightPlanTaskAvailability::NotPresent
                : FlightPlanTaskAvailability::Available,
            FlightPlanTask::FuelScore => $this->flightPlan->fuelPlan === null && $this->flightPlan->waypoints === []
                ? FlightPlanTaskAvailability::NotPresent
                : FlightPlanTaskAvailability::Available,
            FlightPlanTask::Etops => $this->etopsAvailability(),
            FlightPlanTask::Weather => $this->flightPlan->weather === null
                ? FlightPlanTaskAvailability::NotPresent
                : FlightPlanTaskAvailability::Available,
            FlightPlanTask::WeightAndBalance => $this->flightPlan->weightBalance?->hasSourceData() === true
                ? FlightPlanTaskAvailability::Available
                : FlightPlanTaskAvailability::NotPresent,
        };
    }

    public function hasEtopsData(): bool
    {
        $etops = $this->flightPlan->etops;

        return $etops !== null && (
            $etops->ratingMinutes !== null
            || $etops->entryPoint !== null
            || $etops->equalTimePoints !== []
            || $etops->exitPoint !== null
        );
    }

    private function etopsAvailability(): FlightPlanTaskAvailability
    {
        if ($this->hasEtopsData()) {
            return FlightPlanTaskAvailability::Available;
        }

        if ($this->flightPlan->etops?->applicability === EtopsApplicability::ConfirmedNonEtops) {
            return FlightPlanTaskAvailability::NotPresent;
        }

        return $this->flightPlan->etops?->sectionPresent === true
            ? FlightPlanTaskAvailability::NotSupported
            : FlightPlanTaskAvailability::NotPresent;
    }
}
