<?php

namespace App\Services\FlightPlan;

use App\DTOs\FuelPlanData;
use App\DTOs\WeightBalance\WeightBalanceData;
use App\DTOs\WeightBalance\WeightBalanceFieldData;
use App\Enums\WeightBalanceSourceStatus;
use App\ValueObjects\FuelQuantity;
use App\ValueObjects\WeightQuantity;
use InvalidArgumentException;

class WeightBalanceDataBuilder
{
    /** @param array<string, array{amount?: ?int, unit?: string, status?: string}> $source */
    public function build(array $source, ?FuelPlanData $fuelPlan, array $fuelSource = []): WeightBalanceData
    {
        $zeroFuelWeight = $this->sourceField($source['planned_zero_fuel_weight'] ?? null);
        $rampFuel = $this->fuelField($fuelPlan?->ramp, $fuelSource['ramp_status'] ?? null);

        return new WeightBalanceData(
            basicOperatingWeight: $this->sourceField($source['basic_operating_weight'] ?? null),
            plannedPayload: $this->sourceField($source['planned_payload'] ?? null),
            plannedTakeoffFuel: $this->fuelField($fuelPlan?->takeoff, $fuelSource['takeoff_status'] ?? null),
            plannedZeroFuelWeight: $zeroFuelWeight,
            plannedRampWeight: $this->rampWeight($zeroFuelWeight, $rampFuel),
            plannedTakeoffGrossWeight: $this->sourceField($source['planned_takeoff_gross_weight'] ?? null),
            plannedEstimatedLandingWeight: $this->sourceField($source['planned_estimated_landing_weight'] ?? null),
        );
    }

    public function fromSerialized(mixed $source): ?WeightBalanceData
    {
        if (! is_array($source)) {
            return null;
        }

        return new WeightBalanceData(
            basicOperatingWeight: $this->serializedField($source['basicOperatingWeight'] ?? null),
            plannedPayload: $this->serializedField($source['plannedPayload'] ?? null),
            plannedTakeoffFuel: $this->serializedField($source['plannedTakeoffFuel'] ?? null),
            plannedZeroFuelWeight: $this->serializedField($source['plannedZeroFuelWeight'] ?? null),
            plannedRampWeight: $this->serializedField($source['plannedRampWeight'] ?? null),
            plannedTakeoffGrossWeight: $this->serializedField($source['plannedTakeoffGrossWeight'] ?? null),
            plannedEstimatedLandingWeight: $this->serializedField($source['plannedEstimatedLandingWeight'] ?? null),
        );
    }

    /** @param array{amount?: ?int, unit?: string, status?: string}|null $source */
    private function sourceField(?array $source): WeightBalanceFieldData
    {
        $status = isset($source['status'])
            ? WeightBalanceSourceStatus::tryFrom($source['status'])
            : null;

        if ($status !== WeightBalanceSourceStatus::Confirmed
            || ! is_int($source['amount'] ?? null)
            || ! is_string($source['unit'] ?? null)) {
            return new WeightBalanceFieldData(
                plannedValue: null,
                sourceStatus: $status ?? WeightBalanceSourceStatus::NotPresent,
            );
        }

        return new WeightBalanceFieldData(
            plannedValue: new WeightQuantity($source['amount'], $source['unit']),
            sourceStatus: WeightBalanceSourceStatus::Confirmed,
        );
    }

    private function fuelField(?FuelQuantity $fuel, mixed $sourceStatus): WeightBalanceFieldData
    {
        if ($sourceStatus === WeightBalanceSourceStatus::Conflict->value) {
            return new WeightBalanceFieldData(null, WeightBalanceSourceStatus::Conflict);
        }

        if ($fuel === null || floor($fuel->amount) !== $fuel->amount) {
            return new WeightBalanceFieldData(null, WeightBalanceSourceStatus::NotPresent);
        }

        return new WeightBalanceFieldData(
            new WeightQuantity((int) $fuel->amount, $fuel->unit),
            WeightBalanceSourceStatus::Confirmed,
        );
    }

    private function serializedField(mixed $source): WeightBalanceFieldData
    {
        if (! is_array($source)) {
            return new WeightBalanceFieldData(null, WeightBalanceSourceStatus::NotPresent);
        }

        $sourceStatus = is_string($source['sourceStatus'] ?? null)
            ? WeightBalanceSourceStatus::tryFrom($source['sourceStatus'])
            : null;
        $limitStatus = is_string($source['limitStatus'] ?? null)
            ? WeightBalanceSourceStatus::tryFrom($source['limitStatus'])
            : null;
        $plannedValue = $this->weightQuantity($source['plannedValue'] ?? null);
        $permittedLimit = $this->weightQuantity($source['permittedLimit'] ?? null);

        if ($sourceStatus !== WeightBalanceSourceStatus::Confirmed) {
            $plannedValue = null;
        }

        if ($limitStatus !== WeightBalanceSourceStatus::Confirmed) {
            $permittedLimit = null;
        }

        return new WeightBalanceFieldData(
            plannedValue: $plannedValue,
            sourceStatus: $sourceStatus ?? WeightBalanceSourceStatus::NotPresent,
            permittedLimit: $permittedLimit,
            limitStatus: $limitStatus ?? WeightBalanceSourceStatus::LimitUnavailable,
            derived: ($source['derived'] ?? false) === true && $plannedValue !== null,
        );
    }

    private function weightQuantity(mixed $value): ?WeightQuantity
    {
        if (! is_array($value) || ! is_int($value['amount'] ?? null) || ! is_string($value['unit'] ?? null)) {
            return null;
        }

        try {
            return new WeightQuantity($value['amount'], $value['unit']);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function rampWeight(
        WeightBalanceFieldData $zeroFuelWeight,
        WeightBalanceFieldData $rampFuel,
    ): WeightBalanceFieldData {
        if ($zeroFuelWeight->sourceStatus === WeightBalanceSourceStatus::Conflict
            || $rampFuel->sourceStatus === WeightBalanceSourceStatus::Conflict) {
            return new WeightBalanceFieldData(null, WeightBalanceSourceStatus::Conflict);
        }

        if ($zeroFuelWeight->plannedValue === null || $rampFuel->plannedValue === null) {
            return new WeightBalanceFieldData(null, WeightBalanceSourceStatus::NotPresent);
        }

        if ($zeroFuelWeight->plannedValue->unit !== $rampFuel->plannedValue->unit) {
            return new WeightBalanceFieldData(null, WeightBalanceSourceStatus::Conflict);
        }

        return new WeightBalanceFieldData(
            plannedValue: new WeightQuantity(
                $zeroFuelWeight->plannedValue->amount + $rampFuel->plannedValue->amount,
                $zeroFuelWeight->plannedValue->unit,
            ),
            sourceStatus: WeightBalanceSourceStatus::Confirmed,
            derived: true,
        );
    }
}
