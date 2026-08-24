<?php

namespace App\DTOs;

use App\ValueObjects\InitialAltitude;
use JsonSerializable;

final readonly class FlightInitData implements JsonSerializable
{
    public function __construct(
        public bool $sectionPresent,
        public ?string $acarsInitDate = null,
        public ?InitialAltitude $initialAltitude = null,
    ) {}

    /** @return array{sectionPresent: bool, acarsInitDate: ?string, initialAltitude: array{value: int, unit: string, isFlightLevel: bool}|null} */
    public function toArray(): array
    {
        return [
            'sectionPresent' => $this->sectionPresent,
            'acarsInitDate' => $this->acarsInitDate,
            'initialAltitude' => $this->initialAltitude?->toArray(),
        ];
    }

    /** @return array{sectionPresent: bool, acarsInitDate: ?string, initialAltitude: array{value: int, unit: string, isFlightLevel: bool}|null} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
