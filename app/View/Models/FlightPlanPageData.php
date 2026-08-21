<?php

namespace App\View\Models;

use App\DTOs\AirportData;
use App\DTOs\FlightPlanData;
use App\Enums\FlightPlanTask;
use App\Enums\FlightPlanTaskAvailability;

final readonly class FlightPlanPageData
{
    /**
     * @param  list<array{label: string, airports: string, coordinates: string, scenario: string}>  $etps
     */
    public function __construct(
        public FlightPlanData $flightPlan,
        public ?AirportData $departureAirport = null,
        public ?AirportData $destinationAirport = null,
        public ?AirportData $alternateAirport = null,
        public ?string $initialAltitude = null,
        public ?string $duration = null,
        public array $etps = [],
        public ?string $eentCoordinates = null,
        public ?string $eexpCoordinates = null,
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
            FlightPlanTask::FlightInit,
            FlightPlanTask::Fms => FlightPlanTaskAvailability::Available,
            FlightPlanTask::SlotTimes => $this->flightPlan->schedule->slotTimesUtc === []
                ? FlightPlanTaskAvailability::NotPresent
                : FlightPlanTaskAvailability::Available,
            FlightPlanTask::FuelScore => $this->flightPlan->fuelPlan === null
                ? FlightPlanTaskAvailability::NotPresent
                : FlightPlanTaskAvailability::Available,
            FlightPlanTask::Etops => $this->hasEtopsData()
                ? FlightPlanTaskAvailability::Available
                : FlightPlanTaskAvailability::NotPresent,
            FlightPlanTask::MaintenanceLog,
            FlightPlanTask::Envelope,
            FlightPlanTask::Weather,
            FlightPlanTask::WeightAndBalance => FlightPlanTaskAvailability::NotSupported,
        };
    }

    public function hasEtopsData(): bool
    {
        return $this->etps !== []
            || $this->eentCoordinates !== null
            || $this->eexpCoordinates !== null;
    }
}
