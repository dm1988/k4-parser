<?php

namespace App\View\Models;

use App\DTOs\AirportData;
use App\DTOs\CrewMemberData;
use App\DTOs\EnvelopeData;
use App\DTOs\Etops\EtopsEqualTimePointData;
use App\DTOs\Etops\EtopsScenarioData;
use App\DTOs\MaintenanceItemData;
use App\Enums\FlightPlanTask;
use App\Enums\FlightPlanTaskAvailability;
use App\Enums\MaintenanceItemType;
use App\Enums\RouteTokenType;
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

    public function overviewInitialAltitude(): ?string
    {
        return $this->pageData?->initialAltitude;
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
        $slotCount = count($this->pageData?->flightPlan->schedule->slotTimesUtc ?? []);

        if ($slotCount === 0) {
            return null;
        }

        return $slotCount.' approved UTC '.($slotCount === 1 ? 'slot' : 'slots');
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

    /** @return list<array{label: string, value: ?string}> */
    public function fmsFields(): array
    {
        if ($this->pageData === null) {
            return [];
        }

        return [
            ['label' => 'Flight Number', 'value' => $this->flightNumber()],
            ['label' => 'AC Type', 'value' => $this->aircraftType()],
            ['label' => 'RECALL Number', 'value' => $this->recallNumber()],
            ['label' => 'Distance to Destination', 'value' => $this->fmsDistanceToDestination()],
            ['label' => 'Initial Altitude', 'value' => $this->pageData->initialAltitude],
            ['label' => 'Planned Duration', 'value' => $this->pageData->duration],
            ['label' => 'Alternate Airport Reserves', 'value' => $this->fmsAlternateReserve()],
        ];
    }

    /**
     * @return list<array{label: string, availability: FlightPlanTaskAvailability}>
     */
    public function overviewUnsupportedIndicators(): array
    {
        return [
            ['label' => 'GENDEC', 'availability' => FlightPlanTaskAvailability::NotSupported],
            ['label' => 'Flight plan filing', 'availability' => FlightPlanTaskAvailability::NotSupported],
            ['label' => 'Weather / RAIM', 'availability' => FlightPlanTaskAvailability::NotSupported],
            [
                'label' => 'Maintenance',
                'availability' => $this->hasMaintenanceSection()
                    ? FlightPlanTaskAvailability::Available
                    : FlightPlanTaskAvailability::NotPresent,
            ],
        ];
    }

    public function maintenanceEtopsLabel(): string
    {
        return $this->pageData?->flightPlan->maintenanceLog?->etopsApplicability->label() ?? 'Not confirmed';
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
        $count = count($this->pageData?->flightPlan->maintenanceLog->items ?? []);

        return $count.' source-listed '.($count === 1 ? 'item' : 'items');
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

    /** @return list<array{name: string, details: ?string}> */
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

    /** @return list<array{name: string, details: ?string, employeeNumber: ?string}> */
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
            ];
        }, $this->pageData?->flightPlan->crewMembers ?? []);
    }

    /**
     * @return list<array{type: string, number: string, description: string, reference: ?string, status: ?string, limitations: ?string, procedures: ?string, copyable: bool}>
     */
    public function maintenanceItems(): array
    {
        return array_map(static fn (MaintenanceItemData $item): array => [
            'type' => $item->type->value,
            'number' => $item->number,
            'description' => $item->description,
            'reference' => $item->reference,
            'status' => $item->status,
            'limitations' => $item->limitations,
            'procedures' => $item->procedures,
            'copyable' => in_array($item->type, [MaintenanceItemType::Mel, MaintenanceItemType::Cdl], true),
        ], $this->pageData?->flightPlan->maintenanceLog->items ?? []);
    }

    public function envelopeSourceLabel(): string
    {
        return match ($this->envelope()?->sourceType) {
            'takeoff_landing_report' => 'Takeoff and Landing Report',
            default => 'Confirmed release section',
        };
    }

    public function envelopeReportReference(): ?string
    {
        return $this->envelope()?->reportReference;
    }

    public function envelopeAirport(): ?string
    {
        return $this->envelope()?->airport;
    }

    public function envelopePlannedRunway(): ?string
    {
        return $this->envelope()?->plannedRunway;
    }

    public function envelopeOutsideAirTemperature(): ?string
    {
        $temperature = $this->envelope()?->outsideAirTemperatureCelsius;

        return $temperature === null ? null : Number::format($temperature, precision: 1).' °C';
    }

    public function envelopeWind(): ?string
    {
        return $this->envelope()?->wind;
    }

    public function envelopeQnh(): ?string
    {
        $envelope = $this->envelope();

        if ($envelope?->qnhHectopascals !== null) {
            return $envelope->qnhHectopascals.' hPa';
        }

        return $envelope?->qnhInchesMercury === null
            ? null
            : number_format($envelope->qnhInchesMercury, 2).' inHg';
    }

    public function envelopeFlapSetting(): ?string
    {
        return $this->envelope()?->flapSetting;
    }

    public function envelopeAntiIce(): ?string
    {
        $antiIce = $this->envelope()?->antiIce;

        return $antiIce === null ? null : ($antiIce ? 'Yes' : 'No');
    }

    public function envelopeMaximumRunwayTakeoffWeight(): ?string
    {
        return $this->formatWeight($this->envelope()?->maximumRunwayTakeoffWeight);
    }

    public function envelopeMaximumFieldTakeoffWeight(): ?string
    {
        return $this->formatWeight($this->envelope()?->maximumFieldTakeoffWeight);
    }

    public function envelopePlannedTakeoffWeight(): ?string
    {
        return $this->formatWeight($this->envelope()?->plannedTakeoffWeight);
    }

    public function envelopeV1(): ?string
    {
        return $this->formatSpeed($this->envelope()?->v1Knots);
    }

    public function envelopeRotateSpeed(): ?string
    {
        return $this->formatSpeed($this->envelope()?->rotateKnots);
    }

    public function envelopeV2(): ?string
    {
        return $this->formatSpeed($this->envelope()?->v2Knots);
    }

    /** @return list<string> */
    public function envelopeWarnings(): array
    {
        return $this->envelope()->sourceWarnings ?? [];
    }

    private function envelope(): ?EnvelopeData
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

    public function initialAltitude(): string
    {
        return $this->pageData->initialAltitude ?? '';
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
        if ($value === null || preg_match('/(?:Z|\+00:00)\z/', $value) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)
                ->utc()
                ->format('M j, Y · Hi\Z');
        } catch (Throwable) {
            return null;
        }
    }
}
