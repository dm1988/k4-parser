<?php

namespace App\Services\Infrastructure;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FlightPlanResultCache
{
    /** @param array<string, mixed> $flightPlan */
    public function put(User $user, array $flightPlan): string
    {
        $resultKey = (string) Str::ulid();
        $ttlMinutes = (int) config('cache.extracted_results_ttl', 60);

        Cache::memo()->put(
            $this->cacheKey($user, $resultKey),
            $flightPlan,
            now()->addMinutes($ttlMinutes),
        );

        return $resultKey;
    }

    /** @return array<string, mixed>|null */
    public function get(User $user, string $resultKey): ?array
    {
        if (! Str::isUlid($resultKey)) {
            return null;
        }

        $flightPlan = Cache::memo()->get($this->cacheKey($user, $resultKey));

        return is_array($flightPlan) ? $flightPlan : null;
    }

    public function forget(User $user, string $resultKey): void
    {
        if (! Str::isUlid($resultKey)) {
            return;
        }

        Cache::memo()->forget($this->cacheKey($user, $resultKey));
    }

    private function cacheKey(User $user, string $resultKey): string
    {
        return "flight_plan_results:{$user->getKey()}:{$resultKey}";
    }
}
