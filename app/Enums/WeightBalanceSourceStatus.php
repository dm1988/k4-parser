<?php

namespace App\Enums;

enum WeightBalanceSourceStatus: string
{
    case Confirmed = 'confirmed';
    case Conflict = 'conflict';
    case NotPresent = 'not_present';
    case LimitUnavailable = 'limit_unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmed',
            self::Conflict => 'Conflict',
            self::NotPresent => 'Not present',
            self::LimitUnavailable => 'Limit unavailable',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Confirmed => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-200',
            self::Conflict => 'bg-red-100 text-red-800 dark:bg-red-400/15 dark:text-red-200',
            self::NotPresent, self::LimitUnavailable => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
        };
    }
}
