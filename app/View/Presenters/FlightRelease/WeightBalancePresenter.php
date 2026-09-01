<?php

namespace App\View\Presenters\FlightRelease;

use App\DTOs\WeightBalance\WeightBalanceFieldData;
use App\View\Models\FlightPlanPageData;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

final readonly class WeightBalancePresenter
{
    public function __construct(private ?FlightPlanPageData $pageData) {}

    /** @return list<array{label: string, description: string, fields: list<array<string, mixed>>}> */
    public function groups(): array
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
                    $this->field('Basic operating weight', $weightBalance->basicOperatingWeight),
                    $this->field('Payload', $weightBalance->plannedPayload),
                    $this->field('Zero-fuel weight', $weightBalance->plannedZeroFuelWeight),
                ],
            ],
            [
                'label' => 'Departure',
                'description' => 'Ramp, fuel, and takeoff values for departure review.',
                'fields' => [
                    $this->field('Ramp weight', $weightBalance->plannedRampWeight),
                    $this->field('Takeoff fuel', $weightBalance->plannedTakeoffFuel),
                    $this->field('Takeoff gross weight', $weightBalance->plannedTakeoffGrossWeight),
                ],
            ],
            [
                'label' => 'Arrival',
                'description' => 'Estimated landing mass from the confirmed release source.',
                'fields' => [
                    $this->field('Estimated landing weight', $weightBalance->plannedEstimatedLandingWeight),
                ],
            ],
        ];
    }

    /** @return array{label: string, plannedAmount: ?string, plannedUnit: ?string, sourceStatus: string, sourceStatusLabel: string, limitAmount: ?string, limitUnit: ?string, derived: bool} */
    private function field(string $label, WeightBalanceFieldData $field): array
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
}
