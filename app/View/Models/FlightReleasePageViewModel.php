<?php

namespace App\View\Models;

use App\DTOs\AirportData;
use App\DTOs\CrewMemberData;
use App\DTOs\EnvelopeData;
use App\DTOs\Etops\EtopsEqualTimePointData;
use App\DTOs\Etops\EtopsScenarioData;
use App\DTOs\MaintenanceItemData;
use App\DTOs\SlotTimeData;
use App\DTOs\WaypointData;
use App\DTOs\WeightBalance\WeightBalanceFieldData;
use App\Enums\AltitudeUnit;
use App\Enums\EtopsApplicability;
use App\Enums\FlightPlanTask;
use App\Enums\FlightPlanTaskAvailability;
use App\Enums\MaintenanceItemType;
use App\Enums\OperationsSpecification;
use App\Enums\RouteTokenType;
use App\Enums\TaskTone;
use App\ValueObjects\FuelQuantity;
use App\ValueObjects\InitialAltitude;
use App\ValueObjects\WeightQuantity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Locale;
use Throwable;

readonly class FlightReleasePageViewModel
{
    public function __construct(
        public ?FlightPlanPageData $pageData,
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
        return $task !== FlightPlanTask::Etops
            || $this->etopsApplicability() === EtopsApplicability::ConfirmedEtops;
    }

    public function shouldShowEtopsOverviewCard(): bool
    {
        return $this->etopsApplicability() === EtopsApplicability::ConfirmedEtops;
    }

    public function etopsApplicability(): EtopsApplicability
    {
        $etops = $this->pageData?->flightPlan->etops;

        return $etops === null ? EtopsApplicability::Unknown : $etops->applicability;
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
        return $task === FlightPlanTask::ReviewMelCdl
            ? $this->maintenanceItemCount()
            : null;
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
        return $this->pageData?->flightPlan->schedule->etdUtc;
    }

    public function etaUtc(): ?string
    {
        return $this->pageData?->flightPlan->schedule->etaUtc;
    }

    public function releaseRevision(): ?string
    {
        return $this->pageData?->flightPlan->identity->releaseRevision;
    }

    public function overviewEtdUtc(): ?string
    {
        return $this->formatUtcTime($this->etdUtc());
    }

    public function overviewEtaUtc(): ?string
    {
        return $this->formatUtcTime($this->etaUtc());
    }

    public function releaseHeaderDepartureDate(): ?string
    {
        return $this->formatUtcPart($this->etdUtc(), 'M j, Y') ?? $this->flightDate();
    }

    public function releaseHeaderDepartureTime(): ?string
    {
        return $this->formatUtcPart($this->etdUtc(), 'Hi');
    }

    public function releaseHeaderArrivalDate(): ?string
    {
        return $this->formatUtcPart($this->etaUtc(), 'M j, Y');
    }

    public function releaseHeaderArrivalTime(): ?string
    {
        return $this->formatUtcPart($this->etaUtc(), 'Hi');
    }

    public function overviewInitialAltitude(): ?string
    {
        $altitude = $this->filedInitialAltitude();

        return $altitude === '' ? null : $altitude;
    }

    public function overviewRouteDistance(): ?string
    {
        $distance = $this->pageData?->flightPlan->route->distanceNauticalMiles;

        return $distance === null ? null : Number::format($distance).' NM';
    }

    public function overviewRampFuel(): ?string
    {
        return $this->pageData?->flightPlan->fuelPlan?->ramp?->format();
    }

    public function overviewSlotSummary(): ?string
    {
        $slotCount = count($this->pageData?->flightPlan->schedule->slots ?? []);

        if ($slotCount === 0) {
            return null;
        }

        return $slotCount.' approved UTC '.($slotCount === 1 ? 'slot' : 'slots');
    }

    /** @return list<array{direction: string, airport: string, date: string, time: string, sourceTime: string, timeBasis: string, tolerance: ?string, window: ?string, plannedArrival: ?string, comparison: ?string, plannedPosition: ?float}> */
    public function slotTimes(): array
    {
        return array_map(
            fn (SlotTimeData $slot): array => $this->slotTime($slot),
            $this->pageData?->flightPlan->schedule->slots ?? [],
        );
    }

    public function slotSourceText(): ?string
    {
        return $this->pageData?->flightPlan->schedule->slotSourceText;
    }

    /** @return array{direction: string, airport: string, date: string, time: string, sourceTime: string, timeBasis: string, tolerance: ?string, window: ?string, plannedArrival: ?string, comparison: ?string, plannedPosition: ?float} */
    private function slotTime(SlotTimeData $slot): array
    {
        $tolerance = $slot->toleranceMinutes;
        $plannedArrival = null;
        $comparison = null;
        $plannedPosition = null;

        if ($slot->direction->value === 'arrival' && $tolerance !== null && $tolerance > 0 && $this->etaUtc() !== null) {
            try {
                $eta = CarbonImmutable::parse($this->etaUtc())->utc();
                $offsetMinutes = $slot->instantUtc->diffInMinutes($eta, false);
                $plannedArrival = $eta->format('M j, Hi\Z').' UTC';
                $comparison = abs($offsetMinutes) <= $tolerance
                    ? 'Planned ETA is within the confirmed window'
                    : 'Planned ETA is outside the confirmed window';
                $plannedPosition = max(0, min(100, 50 + (($offsetMinutes / ($tolerance * 4)) * 100)));
            } catch (Throwable) {
            }
        }

        return [
            'direction' => $slot->direction->label(),
            'airport' => $slot->airport->value,
            'date' => $slot->instantUtc->format('M j, Y'),
            'time' => $slot->instantUtc->format('Hi').'Z',
            'sourceTime' => $slot->sourceTime,
            'timeBasis' => 'UTC',
            'tolerance' => $tolerance === null ? null : '± '.$tolerance.' min',
            'window' => $tolerance === null ? null : sprintf(
                '%s–%s UTC',
                $slot->instantUtc->subMinutes($tolerance)->format('M j, Hi\Z'),
                $slot->instantUtc->addMinutes($tolerance)->format('M j, Hi\Z'),
            ),
            'plannedArrival' => $plannedArrival,
            'comparison' => $comparison,
            'plannedPosition' => $plannedPosition,
        ];
    }

    public function overviewEtopsSummary(): ?string
    {
        if (! $this->hasEtopsData()) {
            return null;
        }

        $summary = [];
        $criticalPointCount = count($this->etps());

        if ($criticalPointCount > 0) {
            $summary[] = $criticalPointCount.' critical '.($criticalPointCount === 1 ? 'point' : 'points');
        }

        if ($this->eentCoordinates() !== null) {
            $summary[] = 'EENT';
        }

        if ($this->eexpCoordinates() !== null) {
            $summary[] = 'EEXP';
        }

        return implode(' · ', $summary);
    }

    public function fmsDistanceToDestination(): ?string
    {
        return $this->overviewRouteDistance();
    }

    public function fmsAlternateReserve(): ?string
    {
        return $this->pageData?->flightPlan->fuelPlan?->alternate?->format();
    }

    /** @return list<array{label: string, value: ?string, unit: ?string}> */
    public function fuelScoreFields(): array
    {
        $fuelPlan = $this->pageData?->flightPlan->fuelPlan;

        return [
            $this->fuelScoreField('Ramp', $fuelPlan?->ramp),
            $this->fuelScoreField('Taxi', $fuelPlan?->taxi),
            $this->fuelScoreField('Takeoff', $fuelPlan?->takeoff),
            $this->fuelScoreField('Trip', $fuelPlan?->trip),
            $this->fuelScoreField('Alternate', $fuelPlan?->alternate),
            $this->fuelScoreField('Reserve', $fuelPlan?->finalReserve),
            $this->fuelScoreField('Estimated landing', $fuelPlan?->estimatedLanding),
        ];
    }

    /**
     * @return list<array{identifier: string, legDurationMinutes: ?int, cumulativeDurationMinutes: ?int, remainingFuel: ?string}>
     */
    public function fuelScoreWaypoints(): array
    {
        return array_map(
            fn (WaypointData $waypoint): array => [
                'identifier' => $waypoint->identifier,
                'legDurationMinutes' => $waypoint->legDurationMinutes,
                'cumulativeDurationMinutes' => $waypoint->cumulativeDurationMinutes,
                'remainingFuel' => $this->formatWaypointFuel($waypoint->remainingFuel),
            ],
            $this->pageData?->flightPlan->waypoints ?? [],
        );
    }

    private function formatWaypointFuel(?FuelQuantity $quantity): ?string
    {
        if ($quantity === null) {
            return null;
        }

        return $quantity->unit === 'lb'
            ? Number::format($quantity->amount / 1000, precision: 1).' k lbs'
            : Number::format($quantity->amount).' '.Str::upper($quantity->unit);
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
            ['label' => 'Planned Duration', 'value' => $this->pageData->duration],
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
            ['label' => 'Flight plan filing', 'availability' => FlightPlanTaskAvailability::NotSupported],
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
        return $this->pageData?->flightPlan->maintenanceLog?->etopsApplicability->label() ?? 'Not confirmed';
    }

    /**
     * @return list<array{role: string, airport: ?string, metars: list<string>, tafs: list<string>}>
     */
    public function weatherAirportGroups(): array
    {
        $weather = $this->pageData?->flightPlan->weather;

        return [
            [
                'role' => 'Departure',
                'airport' => $weather?->departure?->airport->value ?? $this->departure(),
                'metars' => $weather?->departure->metars ?? [],
                'tafs' => $weather?->departure->tafs ?? [],
            ],
            [
                'role' => 'Destination',
                'airport' => $weather?->destination?->airport->value ?? $this->destination(),
                'metars' => $weather?->destination->metars ?? [],
                'tafs' => $weather?->destination->tafs ?? [],
            ],
            [
                'role' => 'Alternate',
                'airport' => $weather?->alternate?->airport->value ?? $this->alternate(),
                'metars' => $weather?->alternate->metars ?? [],
                'tafs' => $weather?->alternate->tafs ?? [],
            ],
        ];
    }

    public function weatherRaim(): ?string
    {
        return $this->pageData?->flightPlan->weather?->raim;
    }

    public function hasMaintenanceSection(): bool
    {
        return $this->pageData?->flightPlan->maintenanceLog?->sectionPresent === true;
    }

    public function maintenanceRampFuel(): ?string
    {
        $rampFuel = $this->pageData?->flightPlan->fuelPlan?->ramp;

        return $rampFuel === null
            ? null
            : Number::format($rampFuel->amount / 1000, precision: 1);
    }

    public function maintenanceDate(): ?string
    {
        return $this->pageData?->flightPlan->identity->flightDate?->format('m d y');
    }

    public function maintenanceRampFuelLabel(): string
    {
        $unit = $this->pageData?->flightPlan->fuelPlan?->ramp?->unit;

        return $unit === null
            ? 'Estimated ramp fuel'
            : 'Estimated ramp fuel (1,000 '.Str::upper($unit).')';
    }

    public function maintenanceItemCountLabel(): string
    {
        $count = $this->maintenanceItemCount();

        return $count.' source-listed '.($count === 1 ? 'item' : 'items');
    }

    public function maintenanceItemCount(): int
    {
        return count($this->pageData?->flightPlan->maintenanceLog->items ?? []);
    }

    public function maintenanceTypeSummary(): ?string
    {
        $counts = [];

        foreach ($this->pageData?->flightPlan->maintenanceLog->items ?? [] as $item) {
            $counts[$item->type->value] = ($counts[$item->type->value] ?? 0) + 1;
        }

        return $this->maintenanceCountSummary($counts);
    }

    public function maintenanceStatusSummary(): ?string
    {
        $counts = [];

        foreach ($this->pageData?->flightPlan->maintenanceLog->items ?? [] as $item) {
            if ($item->status !== null) {
                $counts[$item->status] = ($counts[$item->status] ?? 0) + 1;
            }
        }

        return $this->maintenanceCountSummary($counts);
    }

    /** @return list<array{name: string, details: ?string, highMins: bool}> */
    public function crewMembers(): array
    {
        return array_map(static function (CrewMemberData $member): array {
            $details = array_values(array_filter([
                $member->role,
                $member->base,
            ], static fn (?string $value): bool => $value !== null));

            return [
                'name' => $member->name,
                'details' => $details === [] ? null : implode(' · ', $details),
                'highMins' => $member->highMins,
            ];
        }, $this->pageData?->flightPlan->crewMembers ?? []);
    }

    public function flightInitEtdUtc(): ?string
    {
        $etdUtc = $this->etdUtc();

        if ($etdUtc === null || preg_match('/(?:Z|\+00:00)\z/', $etdUtc) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::parse($etdUtc)->utc()->format('Hi\Z');
        } catch (Throwable) {
            return null;
        }
    }

    public function flightInitRampFuel(): ?string
    {
        return $this->pageData?->flightPlan->fuelPlan?->ramp?->format();
    }

    public function flightInitAcarsDate(): ?string
    {
        return $this->pageData?->flightPlan->flightInit?->acarsInitDate;
    }

    /** @return list<array{id: string, label: string, value: ?string}> */
    public function flightInitFields(): array
    {
        return [
            ['id' => 'flight-init-tail-number', 'label' => 'Tail number', 'value' => $this->tailNumber()],
            ['id' => 'flight-init-etd', 'label' => 'ETD (UTC)', 'value' => $this->flightInitEtdUtc()],
            ['id' => 'flight-init-ramp-fuel', 'label' => 'Estimated ramp fuel', 'value' => $this->flightInitRampFuel()],
            ['id' => 'flight-init-flight-number', 'label' => 'Flight number', 'value' => $this->flightNumber()],
            ['id' => 'flight-init-departure', 'label' => 'Departure', 'value' => $this->departure()],
            ['id' => 'flight-init-destination', 'label' => 'Destination', 'value' => $this->destination()],
            ['id' => 'flight-init-acars-init-date', 'label' => 'ACARS init date', 'value' => $this->flightInitAcarsDate()],
        ];
    }

    /** @return list<array{name: string, details: ?string, employeeNumber: ?string, highMins: bool}> */
    public function flightInitCrewMembers(): array
    {
        return array_map(static function (CrewMemberData $member): array {
            $details = array_values(array_filter([
                $member->role,
                $member->base,
            ], static fn (?string $value): bool => $value !== null));

            return [
                'name' => $member->name,
                'details' => $details === [] ? null : implode(' · ', $details),
                'employeeNumber' => $member->employeeNumber,
                'highMins' => $member->highMins,
            ];
        }, $this->pageData?->flightPlan->crewMembers ?? []);
    }

    /**
     * @return list<array{type: string, typeTitle: string, typeDescription: string, typeBadgeColor: string, number: string, description: string, reference: ?string, status: ?string, limitations: ?string, procedures: ?string, copyable: bool}>
     */
    public function maintenanceItems(): array
    {
        return array_map(static fn (MaintenanceItemData $item): array => [
            'type' => $item->type->value,
            'typeTitle' => $item->type->title(),
            'typeDescription' => $item->type->description(),
            'typeBadgeColor' => $item->type->badgeColor(),
            'number' => $item->number,
            'description' => $item->description,
            'reference' => $item->reference,
            'status' => $item->status,
            'limitations' => $item->limitations,
            'procedures' => $item->procedures,
            'copyable' => in_array($item->type, [MaintenanceItemType::Mel, MaintenanceItemType::Cdl, MaintenanceItemType::Nef], true),
        ], $this->pageData?->flightPlan->maintenanceLog->items ?? []);
    }

    public function tlrSourceLabel(): string
    {
        return match ($this->tlr()?->sourceType) {
            'takeoff_landing_report' => 'Takeoff and Landing Report',
            default => 'Confirmed release section',
        };
    }

    public function tlrReportReference(): ?string
    {
        return $this->tlr()?->reportReference;
    }

    public function tlrAirport(): ?string
    {
        return $this->tlr()?->airport;
    }

    public function tlrPlannedRunway(): ?string
    {
        return $this->tlr()?->plannedRunway;
    }

    public function tlrOutsideAirTemperature(): ?string
    {
        $temperature = $this->tlr()?->outsideAirTemperatureCelsius;

        return $temperature === null ? null : Number::format($temperature, precision: 1).' °C';
    }

    public function tlrWind(): ?string
    {
        return $this->tlr()?->wind;
    }

    public function tlrQnh(): ?string
    {
        $tlr = $this->tlr();

        if ($tlr?->qnhHectopascals !== null) {
            return $tlr->qnhHectopascals.' hPa';
        }

        return $tlr?->qnhInchesMercury === null
            ? null
            : number_format($tlr->qnhInchesMercury, 2).' inHg';
    }

    public function tlrFlapSetting(): ?string
    {
        return $this->tlr()?->flapSetting;
    }

    public function tlrAntiIce(): ?string
    {
        $antiIce = $this->tlr()?->antiIce;

        return $antiIce === null ? null : ($antiIce ? 'Yes' : 'No');
    }

    public function tlrMaximumRunwayTakeoffWeight(): ?string
    {
        return $this->formatWeight($this->tlr()?->maximumRunwayTakeoffWeight);
    }

    public function tlrMaximumFieldTakeoffWeight(): ?string
    {
        return $this->formatWeight($this->tlr()?->maximumFieldTakeoffWeight);
    }

    public function tlrPlannedTakeoffWeight(): ?string
    {
        return $this->formatWeight($this->tlr()?->plannedTakeoffWeight);
    }

    public function tlrV1(): ?string
    {
        return $this->formatSpeed($this->tlr()?->v1Knots);
    }

    public function tlrRotateSpeed(): ?string
    {
        return $this->formatSpeed($this->tlr()?->rotateKnots);
    }

    public function tlrV2(): ?string
    {
        return $this->formatSpeed($this->tlr()?->v2Knots);
    }

    /** @return list<string> */
    public function tlrWarnings(): array
    {
        return $this->tlr()->sourceWarnings ?? [];
    }

    /** @return list<array{label: string, description: string, fields: list<array<string, mixed>>}> */
    public function weightBalanceGroups(): array
    {
        $weightBalance = $this->pageData?->flightPlan->weightBalance;

        if ($weightBalance === null) {
            return [];
        }

        return [
            [
                'label' => 'Base & Payload',
                'description' => 'Operating weight plus payload establishes planned zero-fuel weight.',
                'fields' => [
                    $this->weightBalanceField('Basic operating weight', $weightBalance->basicOperatingWeight),
                    $this->weightBalanceField('Payload', $weightBalance->plannedPayload),
                    $this->weightBalanceField('Zero-fuel weight', $weightBalance->plannedZeroFuelWeight),
                ],
            ],
            [
                'label' => 'Departure',
                'description' => 'Ramp, fuel, and takeoff values for departure review.',
                'fields' => [
                    $this->weightBalanceField('Ramp weight', $weightBalance->plannedRampWeight),
                    $this->weightBalanceField('Takeoff fuel', $weightBalance->plannedTakeoffFuel),
                    $this->weightBalanceField('Takeoff gross weight', $weightBalance->plannedTakeoffGrossWeight),
                ],
            ],
            [
                'label' => 'Arrival',
                'description' => 'Estimated landing mass from the confirmed release source.',
                'fields' => [
                    $this->weightBalanceField('Estimated landing weight', $weightBalance->plannedEstimatedLandingWeight),
                ],
            ],
        ];
    }

    /**
     * @return array{label: string, plannedAmount: ?string, plannedUnit: ?string, sourceStatus: string, sourceStatusLabel: string, limitAmount: ?string, limitUnit: ?string, derived: bool}
     */
    private function weightBalanceField(string $label, WeightBalanceFieldData $field): array
    {
        return [
            'label' => $label,
            'plannedAmount' => $field->plannedValue === null ? null : Number::format($field->plannedValue->amount),
            'plannedUnit' => $field->plannedValue === null ? null : Str::upper($field->plannedValue->unit),
            'sourceStatus' => $field->sourceStatus->value,
            'sourceStatusLabel' => $field->sourceStatus->label(),
            'limitAmount' => $field->permittedLimit === null ? null : Number::format($field->permittedLimit->amount),
            'limitUnit' => $field->permittedLimit === null ? null : Str::upper($field->permittedLimit->unit),
            'derived' => $field->derived,
        ];
    }

    private function tlr(): ?EnvelopeData
    {
        return $this->pageData?->flightPlan->envelope;
    }

    private function formatWeight(?WeightQuantity $weight): ?string
    {
        return $weight === null
            ? null
            : Number::format($weight->amount).' '.Str::upper($weight->unit);
    }

    private function formatSpeed(?int $speed): ?string
    {
        return $speed === null ? null : $speed.' kt';
    }

    /** @return array{label: string, value: ?string, unit: ?string} */
    private function fuelScoreField(string $label, ?FuelQuantity $quantity): array
    {
        if ($quantity === null) {
            return ['label' => $label, 'value' => null, 'unit' => null];
        }

        return [
            'label' => $label,
            'value' => $quantity->unit === 'lb'
                ? Number::format($quantity->amount / 1000, precision: 1)
                : Number::format($quantity->amount),
            'unit' => $quantity->unit === 'lb' ? 'k lbs' : Str::upper($quantity->unit),
        ];
    }

    /** @param array<string, int> $counts */
    private function maintenanceCountSummary(array $counts): ?string
    {
        if ($counts === []) {
            return null;
        }

        return implode(' · ', array_map(
            static fn (string $label, int $count): string => $count.' '.Str::upper($label),
            array_keys($counts),
            array_values($counts),
        ));
    }

    public function departure(): string
    {
        return $this->pageData?->flightPlan->route->departure->value ?? '';
    }

    public function destination(): string
    {
        return $this->pageData?->flightPlan->route->destination->value ?? '';
    }

    public function alternate(): ?string
    {
        return $this->pageData?->flightPlan->route->alternate?->value;
    }

    public function alternateLabel(): string
    {
        return $this->alternate() ?? 'None listed';
    }

    /**
     * @return array{name: string, location: string, iata: string, icao: string}|null
     */
    public function departureAirport(): ?array
    {
        return $this->airportDetails($this->pageData?->departureAirport);
    }

    /**
     * @return array{name: string, location: string, iata: string, icao: string}|null
     */
    public function destinationAirport(): ?array
    {
        return $this->airportDetails($this->pageData?->destinationAirport);
    }

    /**
     * @return array{name: string, location: string, iata: string, icao: string}|null
     */
    public function alternateAirport(): ?array
    {
        return $this->airportDetails($this->pageData?->alternateAirport);
    }

    public function alternateAirportFallback(): string
    {
        if ($this->alternate() !== null) {
            return 'Airport details unavailable.';
        }

        return 'No alternate airport listed.';
    }

    public function filedInitialAltitude(): string
    {
        return $this->formatInitialAltitude($this->pageData?->flightPlan->flightInit?->filedInitialAltitude);
    }

    public function fmsInitialAltitude(): string
    {
        return $this->formatInitialAltitude($this->pageData?->flightPlan->flightInit?->fmsInitialAltitude);
    }

    private function formatInitialAltitude(?InitialAltitude $altitude): string
    {

        if ($altitude === null) {
            return '';
        }

        if ($altitude->isFlightLevel) {
            $wholeHundreds = intdiv($altitude->value, 100);
            $remainder = $altitude->value % 100;
            $level = str_pad((string) $wholeHundreds, 3, '0', STR_PAD_LEFT);

            if ($remainder !== 0) {
                $level .= '.'.rtrim(str_pad((string) $remainder, 2, '0', STR_PAD_LEFT), '0');
            }

            return 'FL'.$level.($altitude->unit === AltitudeUnit::Meters ? 'M' : '');
        }

        return Number::format($altitude->value).' '.$altitude->unit->abbreviation();
    }

    public function departureRunway(): ?string
    {
        return $this->pageData?->flightPlan->route->departureRunway;
    }

    public function arrivalRunway(): ?string
    {
        return $this->pageData?->flightPlan->route->arrivalRunway;
    }

    public function departureSid(): ?string
    {
        return $this->pageData?->flightPlan->route->departureSid;
    }

    public function arrivalStar(): ?string
    {
        return $this->pageData?->flightPlan->route->arrivalStar;
    }

    public function hasPlannedRunways(): bool
    {
        return $this->departureRunway() !== null || $this->arrivalRunway() !== null;
    }

    /**
     * @return list<array{label: string, airports: string, coordinates: string, scenario: string}>
     */
    public function etps(): array
    {
        $etops = $this->pageData?->flightPlan->etops;

        if ($etops === null) {
            return [];
        }

        return array_map(
            static function (EtopsEqualTimePointData $point, int $index) use ($etops): array {
                $scenario = $etops->scenarios[$index] ?? null;

                return [
                    'label' => $point->label,
                    'airports' => implode('-', array_filter([
                        $point->firstAlternate?->value,
                        $point->secondAlternate?->value,
                    ])),
                    'coordinates' => $point->coordinate->latitude.' '.$point->coordinate->longitude,
                    'scenario' => $scenario->name ?? '',
                ];
            },
            $etops->equalTimePoints,
            array_keys($etops->equalTimePoints),
        );
    }

    public function etopsApplicabilityLabel(): string
    {
        return $this->pageData?->flightPlan->etops?->applicability->label() ?? 'Not confirmed';
    }

    /** @return list<array{label: string, coordinates: string}> */
    public function etopsBoundaryPoints(): array
    {
        $etops = $this->pageData?->flightPlan->etops;
        $points = [];

        foreach ([$etops?->entryPoint, $etops?->exitPoint] as $point) {
            if ($point === null) {
                continue;
            }

            $points[] = [
                'label' => $point->label,
                'coordinates' => $point->coordinate->latitude.' '.$point->coordinate->longitude,
            ];
        }

        return $points;
    }

    /** @return list<string> */
    public function etopsAlternates(): array
    {
        $etops = $this->pageData?->flightPlan->etops;

        if ($etops === null) {
            return [];
        }

        $alternates = [];

        foreach ($etops->equalTimePoints as $point) {
            foreach ([$point->firstAlternate, $point->secondAlternate] as $alternate) {
                if ($alternate !== null) {
                    $alternates[$alternate->value] = $alternate->value;
                }
            }
        }

        return array_values($alternates);
    }

    /** @return list<array{name: string, equalTimePointLabel: ?string}> */
    public function etopsScenarios(): array
    {
        $etops = $this->pageData?->flightPlan->etops;

        if ($etops === null) {
            return [];
        }

        return array_map(
            static fn (EtopsScenarioData $scenario): array => [
                'name' => $scenario->name,
                'equalTimePointLabel' => $scenario->equalTimePointLabel,
            ],
            $etops->scenarios,
        );
    }

    /**
     * @param  array{label: string, airports: string, coordinates: string, scenario: string}  $etp
     * @return list<string>
     */
    public function etpAirports(array $etp): array
    {
        return array_values(array_filter(
            explode('-', $etp['airports']),
            static fn (string $airport): bool => $airport !== '',
        ));
    }

    public function eentCoordinates(): ?string
    {
        $coordinate = $this->pageData?->flightPlan->etops?->entryPoint?->coordinate;

        return $coordinate === null ? null : $coordinate->latitude.' '.$coordinate->longitude;
    }

    public function eexpCoordinates(): ?string
    {
        $coordinate = $this->pageData?->flightPlan->etops?->exitPoint?->coordinate;

        return $coordinate === null ? null : $coordinate->latitude.' '.$coordinate->longitude;
    }

    public function hasEtopsData(): bool
    {
        return $this->pageData?->hasEtopsData() ?? false;
    }

    public function duration(): string
    {
        return $this->pageData->duration ?? '';
    }

    public function etopsBadgeLabel(): ?string
    {
        $etops = $this->pageData?->flightPlan->etops;

        if ($etops?->applicability !== EtopsApplicability::ConfirmedEtops || $etops->ratingMinutes === null) {
            return null;
        }

        return 'ETOPS '.$etops->ratingMinutes;
    }

    public function route(): string
    {
        return $this->pageData?->flightPlan->route->route ?? '';
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
        $tokens = preg_split('/\s+/', trim($this->route()));

        if ($tokens === false) {
            return [];
        }

        return array_values(array_map(function (string $token): array {
            $isAirway = preg_match('/^(?:[A-Z]\d+|Q\d+)$/', $token) === 1;
            $isSpeed = str_contains($token, '/');
            $isDirect = $token === 'DCT';
            $type = match (true) {
                $isSpeed => RouteTokenType::SPEED,
                $isAirway => RouteTokenType::AIRWAY,
                $isDirect => RouteTokenType::DIRECT,
                default => RouteTokenType::FIX,
            };

            return [
                'value' => $token,
                'type' => $type,
                'class' => $type->cssClass(),
            ];
        }, array_filter($tokens, static fn (string $token): bool => $token !== '')));
    }

    /**
     * @return array{name: string, location: string, iata: string, icao: string}|null
     */
    private function airportDetails(?AirportData $airport): ?array
    {
        if ($airport === null || $airport->name === '') {
            return null;
        }

        return [
            'name' => $airport->name,
            'location' => $this->airportLocation($airport),
            'iata' => $airport->iata !== '' ? $airport->iata : 'N/A',
            'icao' => $airport->icao !== '' ? $airport->icao : 'N/A',
        ];
    }

    private function airportLocation(AirportData $airport): string
    {
        return collect([
            $airport->city,
            $this->normalizedAirportState($airport->state),
            $this->normalizedAirportCountry($airport->country),
        ])
            ->filter(static fn (?string $value): bool => ! empty($value))
            ->implode(', ');
    }

    private function normalizedAirportState(?string $state): ?string
    {
        if ($state === null || ctype_digit(trim($state))) {
            return null;
        }

        return $state;
    }

    private function normalizedAirportCountry(string $country): string
    {
        $countryCode = strtoupper(trim($country));

        if (strlen($countryCode) !== 2 || ! ctype_alpha($countryCode)) {
            return $country;
        }

        $countryName = Locale::getDisplayRegion('-'.$countryCode, 'en');

        return $countryName !== '' ? $countryName : $country;
    }

    private function formatUtcTime(?string $value): ?string
    {
        return $this->formatUtcPart($value, 'M j, Y · Hi\Z');
    }

    private function formatUtcPart(?string $value, string $format): ?string
    {
        if ($value === null || preg_match('/(?:Z|\+00:00)\z/', $value) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)
                ->utc()
                ->format($format);
        } catch (Throwable) {
            return null;
        }
    }
}
