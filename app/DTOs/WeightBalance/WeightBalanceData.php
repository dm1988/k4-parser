<?php

namespace App\DTOs\WeightBalance;

use App\Enums\WeightBalanceSourceStatus;
use JsonSerializable;

final readonly class WeightBalanceData implements JsonSerializable
{
    public function __construct(
        public WeightBalanceFieldData $basicOperatingWeight,
        public WeightBalanceFieldData $plannedPayload,
        public WeightBalanceFieldData $plannedTakeoffFuel,
        public WeightBalanceFieldData $plannedZeroFuelWeight,
        public WeightBalanceFieldData $plannedRampWeight,
        public WeightBalanceFieldData $plannedTakeoffGrossWeight,
        public WeightBalanceFieldData $plannedEstimatedLandingWeight,
    ) {}

    /** @return array<string, array<string, mixed>> */
    public function toArray(): array
    {
        return [
            'basicOperatingWeight' => $this->basicOperatingWeight->toArray(),
            'plannedPayload' => $this->plannedPayload->toArray(),
            'plannedTakeoffFuel' => $this->plannedTakeoffFuel->toArray(),
            'plannedZeroFuelWeight' => $this->plannedZeroFuelWeight->toArray(),
            'plannedRampWeight' => $this->plannedRampWeight->toArray(),
            'plannedTakeoffGrossWeight' => $this->plannedTakeoffGrossWeight->toArray(),
            'plannedEstimatedLandingWeight' => $this->plannedEstimatedLandingWeight->toArray(),
        ];
    }

    public function hasSourceData(): bool
    {
        foreach ($this->fields() as $field) {
            if ($field->sourceStatus !== WeightBalanceSourceStatus::NotPresent) {
                return true;
            }
        }

        return false;
    }

    /** @return list<WeightBalanceFieldData> */
    private function fields(): array
    {
        return [
            $this->basicOperatingWeight,
            $this->plannedPayload,
            $this->plannedTakeoffFuel,
            $this->plannedZeroFuelWeight,
            $this->plannedRampWeight,
            $this->plannedTakeoffGrossWeight,
            $this->plannedEstimatedLandingWeight,
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
