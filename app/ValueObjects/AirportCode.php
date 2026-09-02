<?php

namespace App\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

final readonly class AirportCode implements JsonSerializable, Stringable
{
    public string $value;

    public function __construct(string $value)
    {
        $normalizedValue = strtoupper(trim($value));

        if (preg_match('/\A[A-Z]{3,4}\z/', $normalizedValue) !== 1) {
            throw new InvalidArgumentException(
                'Airport code must be a three-letter IATA code or four-letter ICAO code.',
            );
        }

        $this->value = $normalizedValue;
    }

    public static function from(string $value): self
    {
        return new self($value);
    }

    public function isIata(): bool
    {
        return strlen($this->value) === 3;
    }

    public function isIcao(): bool
    {
        return strlen($this->value) === 4;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
