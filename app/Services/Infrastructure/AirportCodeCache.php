<?php

namespace App\Services\Infrastructure;

use App\DTOs\AirportResolution;
use App\Enums\AirportResolutionStatus;
use Illuminate\Support\Facades\Cache;

final class AirportCodeCache
{
    public function get(string $code): ?AirportResolution
    {
        $payload = Cache::get($this->key($code));

        return is_array($payload) ? AirportResolution::fromArray($payload) : null;
    }

    public function put(AirportResolution $resolution): void
    {
        $ttl = match ($resolution->status) {
            AirportResolutionStatus::Found => now()->addDays(30),
            AirportResolutionStatus::Missing => now()->addHours(12),
            AirportResolutionStatus::Unavailable => now()->addMinutes(5),
        };

        Cache::put(
            $this->key($resolution->requestedCode),
            $resolution->toArray(),
            $ttl,
        );
    }

    private function key(string $code): string
    {
        return 'airport:v1:'.(strlen($code) === 4 ? 'icao:' : 'iata:').strtoupper(trim($code));
    }
}
