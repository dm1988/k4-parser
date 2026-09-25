<?php

namespace App\View\Models\FlightRelease;

use App\DTOs\WeightBalance\WeightBalanceFieldData;
use App\Enums\WeightBalanceComparisonTone;
use App\Enums\WeightBalanceSourceStatus;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

final readonly class WeightBalanceFieldViewModel
{
    public WeightBalanceSourceStatus $sourceStatus;

    public function __construct(
        public string $label,
        private WeightBalanceFieldData $field,
        private bool $comparesToLimit = false,
        private bool $integratesLimitInProgress = false,
    ) {
        $this->sourceStatus = $field->sourceStatus;
    }

    public function showsSourceStatusBadge(): bool
    {
        return $this->sourceStatus !== WeightBalanceSourceStatus::Confirmed;
    }

    public function valueLayoutClasses(): string
    {
        return $this->showsStandaloneLimit() ? 'grid grid-cols-2 gap-4' : '';
    }

    public function plannedAmountLabel(): string
    {
        return $this->field->plannedValue === null
            ? $this->sourceStatus->label()
            : Number::format($this->field->plannedValue->amount);
    }

    public function plannedUnit(): ?string
    {
        return $this->field->plannedValue === null
            ? null
            : Str::upper($this->field->plannedValue->unit);
    }

    public function showsStandaloneLimit(): bool
    {
        return $this->comparesToLimit && ! $this->integratesLimitInProgress;
    }

    public function limitAmountLabel(): string
    {
        return $this->field->permittedLimit === null
            ? $this->field->limitStatus->label()
            : Number::format($this->field->permittedLimit->amount);
    }

    public function limitUnit(): ?string
    {
        return $this->field->permittedLimit === null
            ? null
            : Str::upper($this->field->permittedLimit->unit);
    }

    public function hasUtilizationComparison(): bool
    {
        return $this->utilizationPercent() !== null;
    }

    public function integratesLimitWithinProgress(): bool
    {
        return $this->integratesLimitInProgress;
    }

    public function comparisonAriaLabel(): string
    {
        return $this->label.' structural limit comparison';
    }

    public function utilizationAriaLabel(): string
    {
        return $this->label.' utilization';
    }

    public function utilizationAriaValueText(): ?string
    {
        $utilizationPercent = $this->formattedUtilizationPercent();

        if ($utilizationPercent === null) {
            return null;
        }

        return $utilizationPercent.'% of '.$this->limitAmountLabel().' '.$this->limitUnit().' limit';
    }

    public function progressValue(): ?float
    {
        $utilizationPercent = $this->utilizationPercent();

        return $utilizationPercent === null ? null : min($utilizationPercent, 100);
    }

    public function utilizationLabel(): ?string
    {
        $utilizationPercent = $this->formattedUtilizationPercent();

        return $utilizationPercent === null
            ? null
            : $utilizationPercent.'% of structural limit';
    }

    public function progressOverlayLabel(): ?string
    {
        $utilizationPercent = $this->formattedUtilizationPercent();

        if ($utilizationPercent === null) {
            return null;
        }

        return Str::upper(
            $utilizationPercent.'% of '.$this->limitAmountLabel().' '.$this->limitUnit().' limit',
        );
    }

    public function comparisonLabel(): ?string
    {
        return $this->comparisonTone()?->label();
    }

    public function comparisonTextClasses(): ?string
    {
        return $this->comparisonTone()?->textClasses();
    }

    public function comparisonProgressClass(): ?string
    {
        return $this->comparisonTone()?->progressClass();
    }

    public function comparisonUnavailableLabel(): ?string
    {
        if (! $this->comparesToLimit || $this->field->plannedValue === null) {
            return null;
        }

        if ($this->field->permittedLimit === null) {
            return $this->field->limitStatus->label();
        }

        return $this->field->plannedValue->unit !== $this->field->permittedLimit->unit
            ? 'Comparison unavailable: units differ'
            : null;
    }

    public function isDerived(): bool
    {
        return $this->field->derived;
    }

    private function formattedUtilizationPercent(): ?string
    {
        $utilizationPercent = $this->utilizationPercent();

        return $utilizationPercent === null
            ? null
            : Number::format($utilizationPercent, precision: 1);
    }

    private function utilizationPercent(): ?float
    {
        $planned = $this->field->plannedValue;
        $limit = $this->field->permittedLimit;

        if ($planned === null || $limit === null || $planned->unit !== $limit->unit || $limit->amount <= 0) {
            return null;
        }

        return round(($planned->amount / $limit->amount) * 100, 1);
    }

    public function comparisonTone(): ?WeightBalanceComparisonTone
    {
        $utilizationPercent = $this->utilizationPercent();

        if ($utilizationPercent === null) {
            return null;
        }

        return match (true) {
            $utilizationPercent > 100 => WeightBalanceComparisonTone::Exceeded,
            $utilizationPercent >= 98 => WeightBalanceComparisonTone::Caution,
            $utilizationPercent >= 90 => WeightBalanceComparisonTone::Heavy,
            default => WeightBalanceComparisonTone::Safe,
        };
    }
}
