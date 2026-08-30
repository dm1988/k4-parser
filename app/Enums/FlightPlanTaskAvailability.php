<?php

namespace App\Enums;

enum FlightPlanTaskAvailability: string
{
    case Available = 'available';
    case NotPresent = 'not_present';
    case NotSupported = 'not_supported';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::NotPresent => 'Not present',
            self::NotSupported => 'Not supported',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Available => 'bg-[#1B365D]/10 text-[#1B365D] dark:bg-blue-400/15 dark:text-blue-200',
            self::NotPresent => 'bg-red-100 text-red-900 dark:bg-red-400/15 dark:text-red-200',
            self::NotSupported => 'bg-amber-100 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200',
        };
    }

    public function dotColor(): string
    {
        return match ($this) {
            self::Available => 'bg-[#1B365D] dark:bg-blue-300',
            self::NotPresent => 'bg-red-500 dark:bg-red-400',
            self::NotSupported => 'bg-amber-400 dark:bg-amber-300',
        };
    }
}
