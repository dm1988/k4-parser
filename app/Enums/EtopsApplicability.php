<?php

namespace App\Enums;

enum EtopsApplicability: string
{
    case ConfirmedEtops = 'confirmed_etops';
    case ConfirmedNonEtops = 'confirmed_non_etops';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::ConfirmedEtops => 'Yes',
            self::ConfirmedNonEtops => 'No',
            self::Unknown => 'Not confirmed',
        };
    }
}
