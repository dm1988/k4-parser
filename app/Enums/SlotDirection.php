<?php

namespace App\Enums;

enum SlotDirection: string
{
    case Departure = 'departure';
    case Arrival = 'arrival';

    public function label(): string
    {
        return match ($this) {
            self::Departure => 'Departure',
            self::Arrival => 'Arrival',
        };
    }
}
