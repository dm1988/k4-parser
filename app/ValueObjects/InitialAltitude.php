<?php

namespace App\ValueObjects;

use App\Enums\AltitudeUnit;
use InvalidArgumentException;
use JsonSerializable;

final readonly class InitialAltitude implements JsonSerializable
{
    public function __construct(
        public int $value,
        public AltitudeUnit $unit,
        public bool $isFlightLevel,
    ) {
        if ($this->value < 0) {
            throw new InvalidArgumentException('Initial altitude values must be non-negative.');
        }
    }

    /** @return array{value: int, unit: string, isFlightLevel: bool} */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'unit' => $this->unit->value,
            'isFlightLevel' => $this->isFlightLevel,
        ];
    }

    /** @return array{value: int, unit: string, isFlightLevel: bool} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
