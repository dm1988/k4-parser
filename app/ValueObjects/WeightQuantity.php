<?php

namespace App\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

final readonly class WeightQuantity implements JsonSerializable
{
    public string $unit;

    public function __construct(
        public int $amount,
        string $unit,
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Weight amount cannot be negative.');
        }

        $unit = strtolower($unit);

        if (! in_array($unit, ['lb', 'kg'], true)) {
            throw new InvalidArgumentException('Weight unit must be lb or kg.');
        }

        $this->unit = $unit;
    }

    public static function pounds(int $amount): self
    {
        return new self($amount, 'lb');
    }

    public static function kilograms(int $amount): self
    {
        return new self($amount, 'kg');
    }

    /** @return array{amount: int, unit: 'lb'|'kg'} */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'unit' => $this->unit,
        ];
    }

    /** @return array{amount: int, unit: 'lb'|'kg'} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
