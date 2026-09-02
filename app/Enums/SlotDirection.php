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
}
