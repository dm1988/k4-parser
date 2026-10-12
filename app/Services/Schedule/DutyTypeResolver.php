<?php

namespace App\Services\Schedule;

use App\Enums\DutyEventType;

class DutyTypeResolver
{
    public function resolve(string $text): DutyEventType
    {
        if (preg_match('/\b(R1|R2|R4)\b/i', $text, $matches) === 1) {
            return DutyEventType::fromValue($matches[1]) ?? DutyEventType::GeneralDuty;
        }

        if (preg_match('/\b(?:SIM|SIMULATOR|RECURRING|RECURRENT|GROUND|CLASS|TRNG|TRAINING)\b/i', $text) === 1) {
            return DutyEventType::Training;
        }

        if (preg_match('/\b(?:DH|DEADHEAD)\b/i', $text) === 1) {
            return DutyEventType::Deadhead;
        }

        if (preg_match('/\b(?:Flight Duty|Flight Info|Duty LT)\b/i', $text) === 1) {
            return DutyEventType::FlightDuty;
        }

        return DutyEventType::GeneralDuty;
    }
}
