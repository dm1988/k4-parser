<?php

namespace App\Enums;

enum RouteTokenType: string
{
    case AIRWAY = 'airway';
    case SPEED = 'speed';
    case DIRECT = 'direct';
    case FIX = 'fix';

    /**
     * Get the Tailwind CSS classes associated with this token type.
     */
    public function cssClass(): string
    {
        return match ($this) {
            self::SPEED => 'text-amber-700 dark:text-amber-300',
            self::AIRWAY => 'font-bold text-[#1B365D] dark:text-sky-300',
            self::DIRECT => 'text-[#4A5568]/50 dark:text-slate-400',
            self::FIX => 'text-[#0B0E14] dark:text-slate-100',
        };
    }
}
