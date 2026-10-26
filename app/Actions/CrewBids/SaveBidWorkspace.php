<?php

namespace App\Actions\CrewBids;

use App\Models\BidPackage;
use App\Models\BidWorkspace;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveBidWorkspace
{
    /** @param array<string, mixed> $preferences
     * @param  list<string>  $selections
     */
    public function handle(User $user, BidPackage $package, string $seat, int $revision, array $preferences, array $selections): BidWorkspace
    {
        Gate::forUser($user)->authorize('use-crew-bids');
        abort_unless($package->published_at !== null, 404);
        Validator::make(compact('seat', 'preferences', 'selections'), [
            'seat' => ['required', Rule::in(['CA', 'FO'])],
            'preferences' => ['required', 'array:goals,days_off,start_city,initialized'],
            'preferences.goals' => ['present', 'array', 'list', 'max:3'],
            'preferences.goals.*' => ['string', 'distinct', Rule::in(['credit', 'days_off', 'blocks'])],
            'preferences.days_off' => ['present', 'array', 'list', 'max:31'],
            'preferences.days_off.*' => ['date_format:Y-m-d', 'distinct', 'after_or_equal:'.$package->period.'-01', 'before:'.CarbonImmutable::parse($package->period.'-01')->addMonth()->format('Y-m-d')],
            'preferences.start_city' => ['present', 'nullable', 'string', 'regex:/^(?:[A-Z]{3})?$/'],
            'preferences.initialized' => ['required', 'boolean'],
            'selections' => ['present', 'array', 'list', 'max:1000'],
            'selections.*' => ['string', 'distinct', 'regex:/^[FQR]\d{1,4}$/'],
        ])->validate();
        $lines = $package->payload['lines'];
        foreach ($selections as $id) {
            if (! isset($lines[$id]) || (int) $lines[$id][$seat === 'CA' ? 'captains' : 'first_officers'] === 0) {
                throw ValidationException::withMessages(['workspace' => 'A selected line is unavailable for this package and seat.']);
            }
        }

        return DB::transaction(function () use ($user, $package, $seat, $revision, $preferences, $selections): BidWorkspace {
            $workspace = BidWorkspace::query()->firstOrCreate(
                ['user_id' => $user->id, 'bid_package_id' => $package->id, 'seat' => $seat],
                ['preferences' => ['goals' => [], 'days_off' => [], 'start_city' => '', 'initialized' => false], 'selections' => [], 'revision' => 0],
            );
            $workspace = BidWorkspace::query()->lockForUpdate()->findOrFail($workspace->id);
            if ($workspace->revision !== $revision) {
                throw ValidationException::withMessages(['workspace' => 'This list changed in another tab. Your edits are retained. Reload saved choices before retrying.']);
            }
            $workspace->update(['preferences' => $preferences, 'selections' => $selections, 'revision' => $revision + 1]);

            return $workspace;
        });
    }
}
