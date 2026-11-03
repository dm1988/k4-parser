<?php

namespace App\Actions\CrewBids;

use App\Models\BidPackage;
use App\Models\CrewBidPayProfile;
use App\Models\User;
use App\Services\CrewBids\CrewPayPeriod;
use App\Services\CrewBids\NormalizeCrewBidPay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveCrewBidPayProfile
{
    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $input
     */
    public function handle(User $user, BidPackage $package, string $seat, int $revision, array $settings, array $context, ?string $lineId, array $input): CrewBidPayProfile
    {
        Gate::forUser($user)->authorize('use-crew-bids');
        abort_unless($package->published_at !== null, 404);
        abort_if($package->archived_at !== null && ! $package->workspaces()->where('user_id', $user->id)->where('seat', $seat)->exists(), 404);
        $period = new CrewPayPeriod($package->period);
        Validator::make(compact('settings', 'context', 'input', 'seat'), [
            'seat' => ['required', Rule::in(['CA', 'FO'])],
            'settings' => ['required', 'array:show_compact'],
            'settings.show_compact' => ['required', 'boolean'],
            'context' => ['required', 'array:qualified,full_period,line_guarantee,unsupported'],
            'context.qualified' => ['required', 'boolean'], 'context.full_period' => ['required', 'boolean'],
            'context.line_guarantee' => ['required', 'boolean'],
            'context.unsupported' => ['required', 'boolean'],
            'input' => ['present', 'array:mode,reviewed,category,rows'],
            'input.mode' => ['required_with:input.category', Rule::in(['source', 'manual'])],
            'input.reviewed' => ['required_with:input.category', 'boolean'],
            'input.category' => ['sometimes', 'required', Rule::in(['hard', 'reserve', 'composite'])],
            'input.rows' => ['sometimes', 'array', 'list', 'max:200'],
            'input.rows.*' => ['array:assignment,type,date,duration,actual'],
            'input.rows.*.assignment' => ['required', 'string', 'max:80', 'regex:/\A[\pL\pN _.-]+\z/u'],
            'input.rows.*.type' => ['required', Rule::in(array_keys(NormalizeCrewBidPay::types()))],
            'input.rows.*.date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2024-01-01', 'before_or_equal:'.$period->end->format('Y-m-d')],
            'input.rows.*.duration' => ['required', 'regex:/\A\d{1,3}:[0-5]\d(?::[0-5]\d)?\z/'],
            'input.rows.*.actual' => ['present', 'nullable', 'regex:/\A\d{1,3}:[0-5]\d(?::[0-5]\d)?\z/'],
        ])->validate();
        if ($lineId !== null) {
            $line = $package->payload['lines'][$lineId] ?? null;
            abort_unless($line !== null && $line[$seat === 'CA' ? 'captains' : 'first_officers'] > 0, 404);
            if (! isset($input['category'], $input['mode'], $input['reviewed'], $input['rows'])) {
                throw ValidationException::withMessages(['pay' => 'Complete the line category, credit basis and review fields.']);
            }
            $seen = [];
            $ordinaryAssignments = [];
            $openTimeAssignments = [];
            foreach ($input['rows'] as $row) {
                $type = $row['type'];
                $assignment = strtolower(trim($row['assignment'])).':'.$row['date'];
                $ordinary = ! in_array($type, NormalizeCrewBidPay::extras(), true) && $type !== 'unsupported';
                $key = match ($type) {
                    'reserve', 'vacation', 'training' => 'daily:'.$row['date'],
                    'cq_cbt' => 'cq_cbt',
                    default => ($ordinary ? 'ordinary' : $type).':'.$assignment,
                };
                if (isset($seen[$key]) || ($ordinary && isset($ordinaryAssignments[$assignment])) || ($type === 'open_time' && isset($ordinaryAssignments[$assignment])) || ($ordinary && isset($openTimeAssignments[$assignment]))) {
                    throw ValidationException::withMessages(['pay' => 'Repeated ordinary assignment/day or entitlement. Combine daily credits and exclude Open Time flight from ordinary PCH. Separate additional-pay rules may share an assignment reference.']);
                }
                $seen[$key] = true;
                if ($ordinary) {
                    $ordinaryAssignments[$assignment] = true;
                }
                if ($type === 'open_time') {
                    $openTimeAssignments[$assignment] = true;
                }
                if ($input['mode'] === 'source' && (! in_array($type, NormalizeCrewBidPay::extras(), true) || $type === 'open_time')) {
                    throw ValidationException::withMessages(['pay' => 'Use private ordinary-credit inputs for this assignment. Open Time requires excluding that assignment from ordinary PCH so its whole off-day payment is counted once.']);
                }
                if ($type !== 'carry' && ! $period->contains($row['date'])) {
                    throw ValidationException::withMessages(['pay' => 'Only a transitioning-duty credit may be allocated from an earlier earning day.']);
                }
                if ($type !== 'carry' && ($row['actual'] ?? '') !== '') {
                    throw ValidationException::withMessages(['pay' => 'The actual PCH field applies only to transitioning-duty daily credits.']);
                }
            }
        } elseif ($input !== []) {
            throw ValidationException::withMessages(['pay' => 'Choose an eligible line before entering line-specific credits.']);
        }

        return DB::transaction(function () use ($user, $package, $seat, $revision, $settings, $context, $lineId, $input): CrewBidPayProfile {
            $profile = CrewBidPayProfile::query()->firstOrCreate(['user_id' => $user->id], ['settings' => [], 'scenarios' => [], 'revision' => 0]);
            $profile = CrewBidPayProfile::query()->lockForUpdate()->findOrFail($profile->id);
            $this->assertRevision($profile, $revision);
            $scenarios = $profile->scenarios;
            $key = $package->id.':'.$seat;
            $lines = $scenarios[$key]['lines'] ?? [];
            if ($lineId !== null) {
                $lines[$lineId] = $input;
            }
            $scenarios[$key] = $context + ['lines' => $lines];
            $profile->update(['settings' => $settings, 'scenarios' => $scenarios, 'revision' => $revision + 1]);

            return $profile;
        });
    }

    public function forget(User $user, int $revision): void
    {
        Gate::forUser($user)->authorize('use-crew-bids');
        DB::transaction(function () use ($user, $revision): void {
            $profile = CrewBidPayProfile::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if ($profile !== null) {
                $this->assertRevision($profile, $revision);
                $profile->update(['settings' => [], 'scenarios' => [], 'revision' => $revision + 1]);
            }
        });
    }

    private function assertRevision(CrewBidPayProfile $profile, int $revision): void
    {
        if ($profile->revision !== $revision) {
            throw ValidationException::withMessages(['pay' => 'Pay inputs changed in another tab. Your edits are retained. Reopen saved pay inputs before retrying.']);
        }
    }
}
