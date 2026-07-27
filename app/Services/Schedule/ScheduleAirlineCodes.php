<?php

namespace App\Services\Schedule;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ScheduleAirlineCodes
{
    public function iata(): string
    {
        return $this->preferredCode(
            userValue: $this->user()?->airline_iata_code,
            configKey: 'schedule.airline_codes.iata',
        );
    }

    public function icao(): string
    {
        return $this->preferredCode(
            userValue: $this->user()?->airline_icao_code,
            configKey: 'schedule.airline_codes.icao',
        );
    }

    private function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    private function preferredCode(?string $userValue, string $configKey): string
    {
        $value = filled($userValue) ? $userValue : config($configKey);

        return strtoupper(trim((string) $value));
    }
}
