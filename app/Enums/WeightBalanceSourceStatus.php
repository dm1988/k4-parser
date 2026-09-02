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
}
