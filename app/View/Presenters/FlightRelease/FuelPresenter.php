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

    public function overviewRampFuel(): ?string
    {
        return $this->pageData?->flightPlan->fuelPlan?->ramp?->format();
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

    /** @return list<array{identifier: string, legDurationMinutes: ?int, cumulativeDurationMinutes: ?int, remainingFuel: ?string}> */
    public function waypoints(): array
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
