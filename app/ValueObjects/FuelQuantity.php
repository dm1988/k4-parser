<?php

namespace App\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

final readonly class FuelQuantity implements JsonSerializable
{
    private const KILOGRAMS_PER_POUND = 0.45359237;

    private const UNIT_ALIASES = [
        'kg' => 'kg',
        'kgs' => 'kg',
        'kilogram' => 'kg',
        'kilograms' => 'kg',
        'lb' => 'lb',
        'lbs' => 'lb',
        'pound' => 'lb',
        'pounds' => 'lb',
    ];

    public float $amount;

    public string $unit;

    public function __construct(int|float $amount, string $unit)
    {
        $normalizedUnit = strtolower(trim($unit));

        if (! is_finite((float) $amount) || $amount < 0) {
            throw new InvalidArgumentException('Fuel amount must be a finite, non-negative number.');
        }

        if (! array_key_exists($normalizedUnit, self::UNIT_ALIASES)) {
            throw new InvalidArgumentException('Fuel unit must be pounds or kilograms.');
        }

        $this->amount = (float) $amount;
        $this->unit = self::UNIT_ALIASES[$normalizedUnit];
    }

    public static function pounds(int|float $amount): self
    {
        return new self($amount, 'lb');
    }

    public static function kilograms(int|float $amount): self
    {
        return new self($amount, 'kg');
    }

    public function toPounds(): self
    {
        return $this->unit === 'lb'
            ? $this
            : new self($this->amount / self::KILOGRAMS_PER_POUND, 'lb');
    }

    public function toKilograms(): self
    {
        return $this->unit === 'kg'
            ? $this
            : new self($this->amount * self::KILOGRAMS_PER_POUND, 'kg');
    }

    public function equals(self $other, float $tolerance = 0.000001): bool
    {
        if ($tolerance < 0) {
            throw new InvalidArgumentException('Fuel comparison tolerance must not be negative.');
        }

        return abs($this->toKilograms()->amount - $other->toKilograms()->amount) <= $tolerance;
    }

    public function format(int $decimalPlaces = 0): string
    {
        if ($decimalPlaces < 0) {
            throw new InvalidArgumentException('Fuel decimal places must not be negative.');
        }

        return number_format($this->amount, $decimalPlaces).' '.strtoupper($this->unit);
    }

    /** @return array{amount: float, unit: 'kg'|'lb'} */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'unit' => $this->unit,
        ];
    }

    /** @return array{amount: float, unit: 'kg'|'lb'} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
