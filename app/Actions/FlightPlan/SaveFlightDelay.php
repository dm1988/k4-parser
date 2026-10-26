<?php

namespace App\Actions\FlightPlan;

use App\Models\FlightDelay;
use App\Models\FlightPlanResult;
use App\Models\User;
use App\Services\FlightPlan\FlightDelayFields;
use App\Validation\FlightDelayValidationRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveFlightDelay
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function handle(User $user, string $flightPlanKey, int $revision, array $changes): ?FlightDelay
    {
        $normalized = FlightDelayFields::normalize(FlightDelayValidationRules::validate($changes));

        return DB::transaction(function () use ($user, $flightPlanKey, $revision, $normalized): ?FlightDelay {
            $result = FlightPlanResult::query()
                ->whereBelongsTo($user)
                ->where('result_key', $flightPlanKey)
                ->lockForUpdate()
                ->first();

            if ($result === null) {
                throw ValidationException::withMessages([
                    'save' => 'This brief was replaced or cleared. Copy your unsaved input before reopening the current brief.',
                ]);
            }

            $delay = FlightDelay::query()
                ->whereBelongsTo($user)
                ->where('flight_plan_key', $flightPlanKey)
                ->lockForUpdate()
                ->first();

            if (($delay->revision ?? 0) !== $revision) {
                throw ValidationException::withMessages([
                    'save' => 'This report changed in another tab. Copy your unsaved input, then reload to continue.',
                ]);
            }

            if ($normalized === [] || ($delay === null && array_filter($normalized, static fn (mixed $value): bool => $value !== null) === [])) {
                return $delay;
            }

            $delay ??= new FlightDelay([
                'user_id' => $user->getKey(),
                'flight_plan_key' => $flightPlanKey,
                'flight_context' => FlightDelayFields::flightContext($result->getAttribute('payload')),
            ]);
            $delay->fill($normalized);

            if ($delay->delay_started_at !== null && $delay->delay_ended_at !== null
                && $delay->delay_ended_at->lessThan($delay->delay_started_at)) {
                $field = array_key_exists('delay_ended_at', $normalized) ? 'delay_ended_at' : 'delay_started_at';

                throw ValidationException::withMessages([
                    'inputs.'.$field => 'Delay ended must be at or after delay started (UTC).',
                ]);
            }

            $delay->revision = $revision + 1;
            $delay->save();

            return $delay;
        }, 3);
    }
}
