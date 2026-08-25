<?php

namespace App\DTOs;

use App\ValueObjects\InitialAltitude;
use JsonSerializable;

final readonly class FlightInitData implements JsonSerializable
{
    public function __construct(
        public bool $sectionPresent,
        public ?string $acarsInitDate = null,
        public ?InitialAltitude $filedInitialAltitude = null,
        public ?InitialAltitude $fmsInitialAltitude = null,
    ) {}

    /** @return array{sectionPresent: bool, acarsInitDate: ?string, filedInitialAltitude: array{value: int, unit: string, isFlightLevel: bool}|null, fmsInitialAltitude: array{value: int, unit: string, isFlightLevel: bool}|null} */
    public function toArray(): array
    {
        return [
            'sectionPresent' => $this->sectionPresent,
            'acarsInitDate' => $this->acarsInitDate,
            'filedInitialAltitude' => $this->filedInitialAltitude?->toArray(),
            'fmsInitialAltitude' => $this->fmsInitialAltitude?->toArray(),
        ];
    }

    /** @return array{sectionPresent: bool, acarsInitDate: ?string, filedInitialAltitude: array{value: int, unit: string, isFlightLevel: bool}|null, fmsInitialAltitude: array{value: int, unit: string, isFlightLevel: bool}|null} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
