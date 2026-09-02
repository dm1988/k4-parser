<?php

namespace App\DTOs\WeightBalance;

use App\Enums\WeightBalanceSourceStatus;
use App\ValueObjects\WeightQuantity;
use InvalidArgumentException;
use JsonSerializable;

final readonly class WeightBalanceFieldData implements JsonSerializable
{
    public function __construct(
        public ?WeightQuantity $plannedValue,
        public WeightBalanceSourceStatus $sourceStatus,
        public ?WeightQuantity $permittedLimit = null,
        public WeightBalanceSourceStatus $limitStatus = WeightBalanceSourceStatus::LimitUnavailable,
        public bool $derived = false,
    ) {
        if ($sourceStatus === WeightBalanceSourceStatus::Confirmed && $plannedValue === null) {
            throw new InvalidArgumentException('A confirmed weight and balance field requires a planned value.');
        }

        if ($sourceStatus !== WeightBalanceSourceStatus::Confirmed && $plannedValue !== null) {
            throw new InvalidArgumentException('An unconfirmed weight and balance field cannot expose a planned value.');
        }

        if ($derived && $sourceStatus !== WeightBalanceSourceStatus::Confirmed) {
            throw new InvalidArgumentException('Only a confirmed weight and balance field may be derived.');
        }

        if ($limitStatus === WeightBalanceSourceStatus::Confirmed && $permittedLimit === null) {
            throw new InvalidArgumentException('A confirmed limit requires a value.');
        }

        if ($limitStatus !== WeightBalanceSourceStatus::Confirmed && $permittedLimit !== null) {
            throw new InvalidArgumentException('An unavailable limit cannot expose a value.');
        }
    }

    /** @return array<string, array{amount: int, unit: 'lb'|'kg'}|bool|string|null> */
    public function toArray(): array
    {
        return [
            'plannedValue' => $this->plannedValue?->toArray(),
            'sourceStatus' => $this->sourceStatus->value,
            'permittedLimit' => $this->permittedLimit?->toArray(),
            'limitStatus' => $this->limitStatus->value,
            'derived' => $this->derived,
        ];
    }

    /** @return array<string, array{amount: int, unit: 'lb'|'kg'}|bool|string|null> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
