<?php

namespace App\View\Presenters\FlightRelease;

use App\DTOs\WeightBalance\WeightBalanceFieldData;
use App\View\Models\FlightPlanPageData;
use App\View\Models\FlightRelease\WeightBalanceFieldViewModel;

final readonly class WeightBalancePresenter
{
    public function __construct(private ?FlightPlanPageData $pageData) {}

    /** @return list<array{label: string, description: string, fields: list<WeightBalanceFieldViewModel>}> */
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
                    $this->field(
                        'Zero-fuel weight',
                        $weightBalance->plannedZeroFuelWeight,
                        comparesToLimit: true,
                        integratesLimitInProgress: true,
                    ),
                ],
            ],
            [
                'label' => 'Departure',
                'description' => 'Ramp, fuel, and takeoff values for departure review.',
                'fields' => [
                    $this->field(
                        'Ramp weight',
                        $weightBalance->plannedRampWeight,
                        comparesToLimit: true,
                        integratesLimitInProgress: true,
                    ),
                    $this->field('Takeoff fuel', $weightBalance->plannedTakeoffFuel),
                    $this->field(
                        'Takeoff gross weight',
                        $weightBalance->plannedTakeoffGrossWeight,
                        comparesToLimit: true,
                        integratesLimitInProgress: true,
                    ),
                ],
            ],
            [
                'label' => 'Arrival',
                'description' => 'Estimated landing mass from the confirmed release source.',
                'fields' => [
                    $this->field(
                        'Estimated landing weight',
                        $weightBalance->plannedEstimatedLandingWeight,
                        comparesToLimit: true,
                        integratesLimitInProgress: true,
                    ),
                ],
            ],
        ];
    }

    private function field(
        string $label,
        WeightBalanceFieldData $field,
        bool $comparesToLimit = false,
        bool $integratesLimitInProgress = false,
    ): WeightBalanceFieldViewModel {
        return new WeightBalanceFieldViewModel(
            label: $label,
            field: $field,
            comparesToLimit: $comparesToLimit,
            integratesLimitInProgress: $integratesLimitInProgress,
        );
    }
}
