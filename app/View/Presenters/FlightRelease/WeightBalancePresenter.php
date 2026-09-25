<?php

namespace App\View\Presenters\FlightRelease;

use App\DTOs\WeightBalance\WeightBalanceFieldData;
use App\Enums\WeightBalanceComparisonTone;
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

    public function operationalAlertCount(): int
    {
        return count(array_filter(
            $this->comparisonTones(),
            static fn (WeightBalanceComparisonTone $tone): bool => $tone->isOperationalAlert(),
        ));
    }

    public function operationalAlertCountLabel(): string
    {
        $count = $this->operationalAlertCount();

        return $count.' operational weight '.($count === 1 ? 'alert' : 'alerts');
    }

    public function operationalAlertTextClasses(): ?string
    {
        return $this->operationalAlertTone()?->textClasses();
    }

    public function operationalAlertSummary(): ?string
    {
        $heavyCount = $this->comparisonToneCount(WeightBalanceComparisonTone::Heavy);
        $cautionCount = $this->comparisonToneCount(WeightBalanceComparisonTone::Caution);
        $exceededCount = $this->comparisonToneCount(WeightBalanceComparisonTone::Exceeded);
        $summaryParts = [];

        if ($heavyCount > 0) {
            $summaryParts[] = 'Heavy weight operation';
        }

        if ($cautionCount > 0) {
            $summaryParts[] = $this->conditionCountLabel($cautionCount, 'caution');
        }

        if ($exceededCount > 0) {
            $summaryParts[] = $this->conditionCountLabel($exceededCount, 'exceeded');
        }

        return $summaryParts === [] ? null : implode(' · ', $summaryParts);
    }

    public function operationalAlertTone(): ?WeightBalanceComparisonTone
    {
        $highestTone = null;

        foreach ($this->comparisonTones() as $tone) {
            if (! $tone->isOperationalAlert()) {
                continue;
            }

            if ($highestTone === null || $tone->severity() > $highestTone->severity()) {
                $highestTone = $tone;
            }
        }

        return $highestTone;
    }

    /** @return list<WeightBalanceComparisonTone> */
    private function comparisonTones(): array
    {
        $tones = [];

        foreach ($this->groups() as $group) {
            foreach ($group['fields'] as $field) {
                $tone = $field->comparisonTone();

                if ($tone !== null) {
                    $tones[] = $tone;
                }
            }
        }

        return $tones;
    }

    private function comparisonToneCount(WeightBalanceComparisonTone $comparisonTone): int
    {
        return count(array_filter(
            $this->comparisonTones(),
            static fn (WeightBalanceComparisonTone $tone): bool => $tone === $comparisonTone,
        ));
    }

    private function conditionCountLabel(int $count, string $condition): string
    {
        return $count.' '.$condition.' '.($count === 1 ? 'item' : 'items');
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
