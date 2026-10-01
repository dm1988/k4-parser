<?php

namespace App\View\Presenters\FlightRelease;

use App\DTOs\WaypointData;
use App\ValueObjects\FuelQuantity;
use App\View\Models\FlightPlanPageData;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

final readonly class FuelPresenter
{
    public function __construct(private ?FlightPlanPageData $pageData) {}

    /** @return array{value: string, unit: string, accessibleLabel: string, taxiLabel: ?string}|null */
    public function overviewRampFuel(): ?array
    {
        $fuelPlan = $this->pageData?->flightPlan->fuelPlan;

        if ($fuelPlan?->ramp === null) {
            return null;
        }

        $rampFuel = $fuelPlan->ramp;
        $taxiFuel = $fuelPlan->taxi;

        return [
            'value' => $rampFuel->unit === 'lb'
                ? Number::format($rampFuel->amount / 1000, precision: 1)
                : Number::format($rampFuel->amount),
            'unit' => $rampFuel->unit === 'lb' ? 'k lbs' : 'kg',
            'accessibleLabel' => 'Ramp fuel: '.Number::format($rampFuel->amount).' '.($rampFuel->unit === 'lb' ? 'pounds' : 'kilograms'),
            'taxiLabel' => $taxiFuel === null ? null : ($taxiFuel->unit === 'lb'
                ? Number::format($taxiFuel->amount / 1000, precision: 1).'k lbs taxi fuel'
                : Number::format($taxiFuel->amount).' kg taxi fuel'),
        ];
    }

    public function alternateReserve(): ?string
    {
        return $this->pageData?->flightPlan->fuelPlan?->alternate?->format();
    }

    /** @return list<array{label: string, value: ?string, unit: ?string}> */
    public function fields(): array
    {
        $fuelPlan = $this->pageData?->flightPlan->fuelPlan;

        return [
            $this->field('Ramp', $fuelPlan?->ramp),
            $this->field('Taxi', $fuelPlan?->taxi),
            $this->field('Takeoff', $fuelPlan?->takeoff),
            $this->field('Trip', $fuelPlan?->trip),
            $this->field('Alternate', $fuelPlan?->alternate),
            $this->field('Reserve', $fuelPlan?->finalReserve),
            $this->field('Estimated landing', $fuelPlan?->estimatedLanding),
        ];
    }

    /** @return list<array{identifier: string, displayLabel: string, kind: string, legDurationMinutes: ?int, cumulativeDurationMinutes: ?int, remainingFuel: ?string}> */
    public function waypoints(): array
    {
        return array_map(
            fn (WaypointData $waypoint): array => [
                'identifier' => $waypoint->identifier,
                'displayLabel' => $waypoint->label(),
                'kind' => $waypoint->kind->value,
                'legDurationMinutes' => $waypoint->legDurationMinutes,
                'cumulativeDurationMinutes' => $waypoint->cumulativeDurationMinutes,
                'remainingFuel' => $this->formatWaypointFuel($waypoint->remainingFuel),
            ],
            $this->pageData?->flightPlan->waypoints ?? [],
        );
    }

    /**
     * @return array{fuelUnit: ?string, takeoffFuel: array{amount: float, unit: 'kg'|'lb'}|null, estimatedLandingFuel: array{amount: float, unit: 'kg'|'lb'}|null, waypoints: list<array{identifier: string, displayLabel: string, kind: string, coordinate: string, tbo: ?string, legDurationMinutes: ?int, cumulativeDurationMinutes: ?int, remainingFuel: array{amount: float, unit: 'kg'|'lb'}|null}>}
     */
    public function calculatorData(): array
    {
        $waypoints = $this->pageData?->flightPlan->waypoints ?? [];
        $fuelUnit = $this->pageData?->flightPlan->fuelPlan?->takeoff?->unit;

        if ($fuelUnit === null) {
            foreach ($waypoints as $waypoint) {
                if ($waypoint->remainingFuel !== null) {
                    $fuelUnit = $waypoint->remainingFuel->unit;

                    break;
                }
            }
        }

        return [
            'fuelUnit' => $fuelUnit,
            'takeoffFuel' => $this->pageData?->flightPlan->fuelPlan?->takeoff?->toArray(),
            'estimatedLandingFuel' => $this->pageData?->flightPlan->fuelPlan?->estimatedLanding?->toArray(),
            'waypoints' => array_map(
                fn (WaypointData $waypoint): array => [
                    'identifier' => $waypoint->identifier,
                    'displayLabel' => $waypoint->label(),
                    'kind' => $waypoint->kind->value,
                    'coordinate' => $waypoint->coordinate,
                    'tbo' => $waypoint->tbo,
                    'legDurationMinutes' => $waypoint->legDurationMinutes,
                    'cumulativeDurationMinutes' => $waypoint->cumulativeDurationMinutes,
                    'remainingFuel' => $waypoint->remainingFuel?->toArray(),
                ],
                $waypoints,
            ),
        ];
    }

    /** @return array{label: string, value: ?string, unit: ?string} */
    private function field(string $label, ?FuelQuantity $quantity): array
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

    private function formatWaypointFuel(?FuelQuantity $quantity): ?string
    {
        if ($quantity === null) {
            return null;
        }

        return $quantity->unit === 'lb'
            ? Number::format($quantity->amount / 1000, precision: 1).' k lbs'
            : Number::format($quantity->amount).' '.Str::upper($quantity->unit);
    }
}
