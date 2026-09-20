<?php

namespace App\Enums;

enum SlotDirection: string
{
    case Departure = 'departure';
    case Arrival = 'arrival';
    case Unspecified = 'unspecified';

    public function label(): string
    {
        return match ($this) {
            self::Departure => 'Departure',
            self::Arrival => 'Arrival',
            self::Unspecified => 'Slot',
        };
    }

    public function comparisonHeading(): ?string
    {
        return match ($this) {
            self::Departure => 'Planned departure comparison',
            self::Arrival => 'Planned arrival comparison',
            self::Unspecified => null,
        };
    }

    public function plannedTimeLabel(): ?string
    {
        return match ($this) {
            self::Departure => 'ETD',
            self::Arrival => 'ETA',
            self::Unspecified => null,
        };
    }
}
