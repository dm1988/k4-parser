<?php

namespace App\Services\Infrastructure;

use App\Models\FlightPlanResult;
use App\Models\User;
use Illuminate\Support\Str;

class FlightPlanResultStore
{
    /** @param array<string, mixed> $flightPlan */
    public function save(User $user, array $flightPlan): string
    {
        $resultKey = (string) Str::ulid();

        FlightPlanResult::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            [
                'result_key' => $resultKey,
                'payload' => $flightPlan,
            ],
        );

        return $resultKey;
    }

    /** @return array<string, mixed>|null */
    public function get(User $user, string $resultKey): ?array
    {
        if (! Str::isUlid($resultKey)) {
            return null;
        }

        $payload = FlightPlanResult::query()
            ->whereBelongsTo($user)
            ->where('result_key', $resultKey)
            ->value('payload');

        return is_array($payload) ? $payload : null;
    }

    public function latest(User $user): ?FlightPlanResult
    {
        return FlightPlanResult::query()
            ->whereBelongsTo($user)
            ->latest('updated_at')
            ->first();
    }

    public function delete(User $user, string $resultKey): void
    {
        if (! Str::isUlid($resultKey)) {
            return;
        }

        FlightPlanResult::query()
            ->whereBelongsTo($user)
            ->where('result_key', $resultKey)
            ->delete();
    }
}
