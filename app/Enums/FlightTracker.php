<?php

namespace App\Enums;

enum FlightTracker: string
{
    case FlightAware = 'flightaware';
    case Flightradar24 = 'flightradar24';

    public static function fromValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::FlightAware;
    }

    public function label(): string
    {
        return match ($this) {
            self::FlightAware => 'FlightAware',
            self::Flightradar24 => 'Flightradar24',
        };
    }
}
