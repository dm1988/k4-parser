<?php

namespace App\Enums;

enum AltitudeUnit: string
{
    case Feet = 'feet';
    case Meters = 'meters';

    public function abbreviation(): string
    {
        return match ($this) {
            self::Feet => 'ft',
            self::Meters => 'm',
        };
    }
}
