<?php

namespace App\View\Models;

use App\Enums\EtopsApplicability;
use App\Enums\FlightPlanTask;
use App\Enums\FlightPlanTaskAvailability;
use App\Enums\OperationsSpecification;
use App\Enums\RouteTokenType;
use App\Enums\TaskTone;
use App\View\Presenters\FlightRelease\CrewPresenter;
use App\View\Presenters\FlightRelease\EtopsPresenter;
use App\View\Presenters\FlightRelease\FlightInitPresenter;
use App\View\Presenters\FlightRelease\FuelPresenter;
use App\View\Presenters\FlightRelease\MaintenancePresenter;
use App\View\Presenters\FlightRelease\RoutePresenter;
use App\View\Presenters\FlightRelease\SchedulePresenter;
use App\View\Presenters\FlightRelease\TakeoffLandingReportPresenter;
use App\View\Presenters\FlightRelease\WeatherPresenter;
use App\View\Presenters\FlightRelease\WeightBalancePresenter;

readonly class FlightReleasePageViewModel
{
    public function __construct(
        public ?FlightPlanPageData $pageData,
        private EtopsPresenter $etopsPresenter,
        private SchedulePresenter $schedulePresenter,
        private FuelPresenter $fuelPresenter,
        private RoutePresenter $routePresenter,
        private FlightInitPresenter $flightInitPresenter,
        private MaintenancePresenter $maintenancePresenter,
        private CrewPresenter $crewPresenter,
        private WeatherPresenter $weatherPresenter,
        private TakeoffLandingReportPresenter $takeoffLandingReportPresenter,
        private WeightBalancePresenter $weightBalancePresenter,
    ) {}

    public function hasFlightPlan(): bool
    {
        return $this->pageData !== null;
    }

    /** @return list<FlightPlanTask> */
    public function tasks(): array
    {
        $reviewTask = FlightPlanTask::ReviewMelCdl;
        $tasks = array_values(array_filter(
            FlightPlanTask::cases(),
            fn (FlightPlanTask $task): bool => $task !== $reviewTask && $this->isTaskVisible($task),
        ));

        if ($this->maintenanceItemCount() === 0) {
            return [...$tasks, $reviewTask];
        }

        return [
            FlightPlanTask::Overview,
            $reviewTask,
            ...array_values(array_filter(
                $tasks,
                static fn (FlightPlanTask $task): bool => $task !== FlightPlanTask::Overview,
            )),
        ];
    }

    public function isTaskVisible(FlightPlanTask $task): bool
    {
        return match ($task) {
            FlightPlanTask::SlotTimes => $this->hasSlotTimes(),
            FlightPlanTask::Etops => $this->availabilityFor($task) !== FlightPlanTaskAvailability::NotPresent,
            default => true,
        };
    }

    public function shouldShowEtopsOverviewCard(): bool
    {
        return $this->etopsApplicability() === EtopsApplicability::ConfirmedEtops;
    }

    public function etopsApplicability(): EtopsApplicability
    {
        return $this->etopsPresenter->applicability();
    }

    public function operationsSpecification(): OperationsSpecification
    {
        return $this->pageData?->flightPlan->releaseAuthorization->operationsSpecification
            ?? OperationsSpecification::Unknown;
    }

    public function b44BadgeLabel(): ?string
    {
        return $this->operationsSpecification() === OperationsSpecification::B44 ? 'B44' : null;
    }

    public function taskCounter(FlightPlanTask $task): ?int
    {
        return match ($task) {
            FlightPlanTask::ReviewMelCdl => $this->maintenanceItemCount(),
            FlightPlanTask::SlotTimes => count($this->slotTimes()),
            default => null,
        };
    }

    public function hasSlotTimes(): bool
    {
        return $this->slotTimes() !== [];
    }

    public function flightNumber(): ?string
    {
        return $this->pageData?->flightPlan->identity->flightNumber;
    }

    public function flightDate(): ?string
    {
        return $this->pageData?->flightPlan->identity->flightDate?->format('M j, Y');
    }

    public function aircraftType(): ?string
    {
        return $this->pageData?->flightPlan->identity->aircraftType;
    }

    public function tailNumber(): ?string
    {
        return $this->pageData?->flightPlan->identity->tailNumber;
    }

    public function tripNumber(): ?string
    {
        return $this->pageData?->flightPlan->identity->tripNumber;
    }

    public function recallNumber(): ?string
    {
        $recallNumber = $this->pageData?->flightPlan->identity->recallNumber;

        return is_string($recallNumber) && strlen($recallNumber) === 5 && ctype_digit($recallNumber)
            ? $recallNumber
            : null;
    }

    public function etdUtc(): ?string
    {
        return $this->schedulePresenter->etdUtc();
    }

    public function etaUtc(): ?string
    {
        return $this->schedulePresenter->etaUtc();
    }

    public function releaseRevision(): ?string
    {
        return $this->pageData?->flightPlan->identity->releaseRevision;
    }

    public function overviewEtdUtc(): ?string
    {
        return $this->schedulePresenter->overviewEtdUtc();
    }

    public function overviewEtaUtc(): ?string
    {
        return $this->schedulePresenter->overviewEtaUtc();
    }

    public function releaseHeaderDepartureDate(): ?string
    {
        return $this->schedulePresenter->departureDate($this->flightDate());
    }

    public function releaseHeaderDepartureTime(): ?string
    {
        return $this->schedulePresenter->departureTime();
    }

    public function releaseHeaderArrivalDate(): ?string
    {
        return $this->schedulePresenter->arrivalDate();
    }

    public function releaseHeaderArrivalTime(): ?string
    {
        return $this->schedulePresenter->arrivalTime();
    }

    public function overviewInitialAltitude(): ?string
    {
        return $this->routePresenter->overviewInitialAltitude();
    }

    public function overviewRouteDistance(): ?string
    {
        return $this->routePresenter->overviewDistance();
    }

    public function overviewRampFuel(): ?string
    {
        return $this->fuelPresenter->overviewRampFuel();
    }

    public function overviewSlotSummary(): ?string
    {
        return $this->schedulePresenter->overviewSlotSummary();
    }

    /** @return list<array{direction: string, airport: string, date: string, time: string, sourceTime: string, timeBasis: string, tolerance: ?string, window: ?string, plannedArrival: ?string, comparison: ?string, plannedPosition: ?float}> */
    public function slotTimes(): array
    {
        return $this->schedulePresenter->slotTimes();
    }

    public function slotSourceText(): ?string
    {
        return $this->schedulePresenter->slotSourceText();
    }

    public function overviewEtopsSummary(): ?string
    {
        return $this->etopsPresenter->overviewSummary();
    }

    public function fmsDistanceToDestination(): ?string
    {
        return $this->overviewRouteDistance();
    }

    public function fmsAlternateReserve(): ?string
    {
        return $this->fuelPresenter->alternateReserve();
    }

    /** @return list<array{label: string, value: ?string, unit: ?string}> */
    public function fuelScoreFields(): array
    {
        return $this->fuelPresenter->fields();
    }

    /**
     * @return list<array{identifier: string, legDurationMinutes: ?int, cumulativeDurationMinutes: ?int, remainingFuel: ?string}>
     */
    public function fuelScoreWaypoints(): array
    {
        return $this->fuelPresenter->waypoints();
    }

    /** @return list<array{label: string, value: ?string}> */
    public function fmsFields(): array
    {
        if ($this->pageData === null) {
            return [];
        }

        return [
            ['label' => 'Flight Number', 'value' => $this->flightNumber()],
            ['label' => 'AC Type', 'value' => $this->aircraftType()],
            ['label' => 'Recall Number', 'value' => $this->recallNumber()],
            ['label' => 'Cost Index', 'value' => $this->fmsCostIndex()],
            ['label' => 'Distance to Destination', 'value' => $this->fmsDistanceToDestination()],
            ['label' => 'FMS initial altitude', 'value' => $this->fmsInitialAltitude()],
            ['label' => 'Planned Duration', 'value' => $this->pageData->flightPlan->schedule->blockDuration],
            ['label' => 'Alternate Airport Reserves', 'value' => $this->fmsAlternateReserve()],
        ];
    }

    private function fmsCostIndex(): ?string
    {
        $costIndex = $this->pageData?->flightPlan->fuelPlan?->costIndex;

        return $costIndex === null ? null : (string) $costIndex;
    }

    /**
     * @return list<array{label: string, availability: FlightPlanTaskAvailability, statusLabel?: string, absenceIsGood?: bool, tone?: TaskTone}>
     */
    public function overviewUnsupportedIndicators(): array
    {
        $indicators = [
            [
                'label' => 'GENDEC',
                'availability' => $this->pageData?->flightPlan->generalDeclaration->sectionPresent === true
                    ? FlightPlanTaskAvailability::Available
                    : FlightPlanTaskAvailability::NotPresent,
            ],
            ['label' => 'Weather / RAIM', 'availability' => $this->availabilityFor(FlightPlanTask::Weather)],
            [
                'label' => 'Maintenance',
                'availability' => $this->maintenanceItemCount() > 0
                    ? FlightPlanTaskAvailability::Available
                    : FlightPlanTaskAvailability::NotPresent,
                'absenceIsGood' => $this->hasMaintenanceSection(),
            ],
        ];

        if ($this->etopsApplicability() === EtopsApplicability::ConfirmedNonEtops) {
            array_unshift($indicators, [
                'label' => 'ETOPS',
                'availability' => FlightPlanTaskAvailability::NotPresent,
                'statusLabel' => 'Non ETOPS',
                'tone' => TaskTone::Neutral,
            ]);
        }

        return $indicators;
    }

    public function maintenanceEtopsLabel(): string
    {
        return $this->etopsPresenter->applicabilityLabel();
    }

    /**
     * @return list<array{role: string, airport: ?string, metars: list<string>, tafs: list<string>}>
     */
    public function weatherAirportGroups(): array
    {
        return $this->weatherPresenter->airportGroups();
    }

    public function weatherRaim(): ?string
    {
        return $this->weatherPresenter->raim();
    }

    public function hasMaintenanceSection(): bool
    {
        return $this->maintenancePresenter->hasSection();
    }

    public function maintenanceRampFuel(): ?string
    {
        return $this->maintenancePresenter->rampFuel();
    }

    public function maintenanceDate(): ?string
    {
        return $this->maintenancePresenter->date();
    }

    public function maintenanceRampFuelLabel(): string
    {
        return $this->maintenancePresenter->rampFuelLabel();
    }

    public function maintenanceItemCountLabel(): string
    {
        return $this->maintenancePresenter->itemCountLabel();
    }

    public function maintenanceItemCount(): int
    {
        return $this->maintenancePresenter->itemCount();
    }

    public function maintenanceTypeSummary(): ?string
    {
        return $this->maintenancePresenter->typeSummary();
    }

    public function maintenanceStatusSummary(): ?string
    {
        return $this->maintenancePresenter->statusSummary();
    }

    /** @return list<array{name: string, details: ?string, highMins: bool}> */
    public function crewMembers(): array
    {
        return $this->crewPresenter->maintenanceMembers();
    }

    public function flightInitEtdUtc(): ?string
    {
        return $this->flightInitPresenter->etdUtc();
    }

    public function flightInitRampFuel(): ?string
    {
        return $this->flightInitPresenter->rampFuel();
    }

    public function flightInitAcarsDate(): ?string
    {
        return $this->flightInitPresenter->acarsDate();
    }

    /** @return list<array{id: string, label: string, value: ?string}> */
    public function flightInitFields(): array
    {
        return $this->flightInitPresenter->fields();
    }

    /** @return list<array{name: string, details: ?string, employeeNumber: ?string, highMins: bool}> */
    public function flightInitCrewMembers(): array
    {
        return $this->crewPresenter->flightInitMembers();
    }

    /**
     * @return list<array{type: string, typeTitle: string, typeDescription: string, typeBadgeColor: string, number: string, description: string, reference: ?string, status: ?string, limitations: ?string, procedures: ?string, copyable: bool}>
     */
    public function maintenanceItems(): array
    {
        return $this->maintenancePresenter->items();
    }

    public function tlrSourceLabel(): string
    {
        return $this->takeoffLandingReportPresenter->sourceLabel();
    }

    public function tlrReportReference(): ?string
    {
        return $this->takeoffLandingReportPresenter->reportReference();
    }

    public function tlrAirport(): ?string
    {
        return $this->takeoffLandingReportPresenter->airport();
    }

    public function tlrPlannedRunway(): ?string
    {
        return $this->takeoffLandingReportPresenter->plannedRunway();
    }

    public function tlrOutsideAirTemperature(): ?string
    {
        return $this->takeoffLandingReportPresenter->outsideAirTemperature();
    }

    public function tlrWind(): ?string
    {
        return $this->takeoffLandingReportPresenter->wind();
    }

    public function tlrQnh(): ?string
    {
        return $this->takeoffLandingReportPresenter->qnh();
    }

    public function tlrFlapSetting(): ?string
    {
        return $this->takeoffLandingReportPresenter->flapSetting();
    }

    public function tlrAntiIce(): ?string
    {
        return $this->takeoffLandingReportPresenter->antiIce();
    }

    public function tlrMaximumRunwayTakeoffWeight(): ?string
    {
        return $this->takeoffLandingReportPresenter->maximumRunwayTakeoffWeight();
    }

    public function tlrMaximumFieldTakeoffWeight(): ?string
    {
        return $this->takeoffLandingReportPresenter->maximumFieldTakeoffWeight();
    }

    public function tlrPlannedTakeoffWeight(): ?string
    {
        return $this->takeoffLandingReportPresenter->plannedTakeoffWeight();
    }

    public function tlrV1(): ?string
    {
        return $this->takeoffLandingReportPresenter->v1();
    }

    public function tlrRotateSpeed(): ?string
    {
        return $this->takeoffLandingReportPresenter->rotateSpeed();
    }

    public function tlrV2(): ?string
    {
        return $this->takeoffLandingReportPresenter->v2();
    }

    /** @return list<string> */
    public function tlrWarnings(): array
    {
        return $this->takeoffLandingReportPresenter->warnings();
    }

    /** @return list<array{label: string, description: string, fields: list<array<string, mixed>>}> */
    public function weightBalanceGroups(): array
    {
        return $this->weightBalancePresenter->groups();
    }

    public function departure(): string
    {
        return $this->routePresenter->departure();
    }

    public function destination(): string
    {
        return $this->routePresenter->destination();
    }

    public function alternate(): ?string
    {
        return $this->routePresenter->alternate();
    }

    public function alternateLabel(): string
    {
        return $this->routePresenter->alternateLabel();
    }

    /**
     * @return array{name: string, location: string, iata: string, icao: string}|null
     */
    public function departureAirport(): ?array
    {
        return $this->routePresenter->departureAirport();
    }

    /**
     * @return array{name: string, location: string, iata: string, icao: string}|null
     */
    public function destinationAirport(): ?array
    {
        return $this->routePresenter->destinationAirport();
    }

    /**
     * @return array{name: string, location: string, iata: string, icao: string}|null
     */
    public function alternateAirport(): ?array
    {
        return $this->routePresenter->alternateAirport();
    }

    public function alternateAirportFallback(): string
    {
        return $this->routePresenter->alternateAirportFallback();
    }

    public function filedInitialAltitude(): string
    {
        return $this->routePresenter->filedInitialAltitude();
    }

    public function fmsInitialAltitude(): string
    {
        return $this->routePresenter->fmsInitialAltitude();
    }

    public function departureRunway(): ?string
    {
        return $this->routePresenter->departureRunway();
    }

    public function arrivalRunway(): ?string
    {
        return $this->routePresenter->arrivalRunway();
    }

    public function departureSid(): ?string
    {
        return $this->routePresenter->departureSid();
    }

    public function arrivalStar(): ?string
    {
        return $this->routePresenter->arrivalStar();
    }

    public function hasPlannedRunways(): bool
    {
        return $this->routePresenter->hasPlannedRunways();
    }

    /**
     * @return list<array{label: string, airports: string, coordinates: string, scenario: string}>
     */
    public function etps(): array
    {
        return $this->etopsPresenter->criticalPoints();
    }

    public function etopsApplicabilityLabel(): string
    {
        return $this->etopsPresenter->applicabilityLabel();
    }

    /** @return list<array{label: string, coordinates: string}> */
    public function etopsBoundaryPoints(): array
    {
        return $this->etopsPresenter->boundaryPoints();
    }

    /** @return list<string> */
    public function etopsAlternates(): array
    {
        return $this->etopsPresenter->alternates();
    }

    /** @return list<array{name: string, equalTimePointLabel: ?string}> */
    public function etopsScenarios(): array
    {
        return $this->etopsPresenter->scenarios();
    }

    /**
     * @param  array{label: string, airports: string, coordinates: string, scenario: string}  $etp
     * @return list<string>
     */
    public function etpAirports(array $etp): array
    {
        return $this->etopsPresenter->airports($etp);
    }

    public function eentCoordinates(): ?string
    {
        return $this->etopsPresenter->entryCoordinates();
    }

    public function eexpCoordinates(): ?string
    {
        return $this->etopsPresenter->exitCoordinates();
    }

    public function hasEtopsData(): bool
    {
        return $this->etopsPresenter->hasData();
    }

    public function duration(): string
    {
        return $this->pageData?->flightPlan->schedule->blockDuration ?? '';
    }

    public function etopsBadgeLabel(): ?string
    {
        return $this->etopsPresenter->badgeLabel();
    }

    public function route(): string
    {
        return $this->routePresenter->route();
    }

    public function availabilityFor(FlightPlanTask $task): FlightPlanTaskAvailability
    {
        return $this->pageData?->availabilityFor($task) ?? FlightPlanTaskAvailability::NotPresent;
    }

    /** @return array<string, FlightPlanTaskAvailability> */
    public function taskAvailability(): array
    {
        return $this->pageData?->taskAvailability() ?? [];
    }

    /**
     * @return list<array{
     *     value: string,
     *     type: RouteTokenType,
     *     class: string
     * }>
     */
    public function routeTokens(): array
    {
        return $this->routePresenter->tokens();
    }
}
