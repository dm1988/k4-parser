<?php

namespace App\View\Models;

use App\DTOs\AirportData;
use App\DTOs\FlightPlanData;
use App\Enums\FlightPlanTask;
use App\Enums\FlightPlanTaskAvailability;

final readonly class FlightPlanPageData
{
    public function __construct(
        public FlightPlanData $flightPlan,
        public ?AirportData $departureAirport = null,
        public ?AirportData $destinationAirport = null,
        public ?AirportData $alternateAirport = null,
        public ?string $duration = null,
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
            FlightPlanTask::FlightInit,
            FlightPlanTask::Fms => FlightPlanTaskAvailability::Available,
            FlightPlanTask::ReviewMelCdl => $this->flightPlan->maintenanceLog->items === []
                ? FlightPlanTaskAvailability::NotPresent
                : FlightPlanTaskAvailability::Available,
            FlightPlanTask::SlotTimes => $this->flightPlan->schedule->slots === []
                ? FlightPlanTaskAvailability::NotPresent
                : FlightPlanTaskAvailability::Available,
            FlightPlanTask::FuelScore => $this->flightPlan->fuelPlan === null && $this->flightPlan->waypoints === []
                ? FlightPlanTaskAvailability::NotPresent
                : FlightPlanTaskAvailability::Available,
            FlightPlanTask::Etops => $this->hasEtopsData()
                ? FlightPlanTaskAvailability::Available
                : FlightPlanTaskAvailability::NotPresent,
            FlightPlanTask::Weather => $this->flightPlan->weather === null
                ? FlightPlanTaskAvailability::NotPresent
                : FlightPlanTaskAvailability::Available,
            FlightPlanTask::WeightAndBalance => $this->flightPlan->weightBalance?->hasSourceData() === true
                ? FlightPlanTaskAvailability::Available
                : FlightPlanTaskAvailability::NotPresent,
            FlightPlanTask::Envelope => $this->takeoffLandingReportAvailability(),
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

    private function takeoffLandingReportAvailability(): FlightPlanTaskAvailability
    {
        if ($this->flightPlan->takeoffLandingReport === null) {
            return FlightPlanTaskAvailability::NotPresent;
        }

        return $this->flightPlan->takeoffLandingReport->plannedTakeoffWeight === null
            ? FlightPlanTaskAvailability::NotSupported
            : FlightPlanTaskAvailability::Available;
    }
}
