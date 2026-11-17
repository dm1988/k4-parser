<?php

namespace App\Services\CrewBids;

use App\Exceptions\BidPortalException;
use App\Models\BidPackage;
use App\Models\BidSubmission;
use App\Models\BidWorkspace;
use App\Models\SubmittedBidSnapshot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use SensitiveParameter;

class StoreSubmittedBidSnapshot
{
    public function authorize(User $user, BidPackage $package, string $seat): void
    {
        Gate::forUser($user)->authorize('use-crew-bids');
        abort_unless(in_array($seat, ['CA', 'FO'], true) && $package->published_at !== null
            && ($package->archived_at === null || $package->workspaces()->where('user_id', $user->id)->where('seat', $seat)->exists()), 404);
    }

    public function packageHash(BidPackage $package): string
    {
        return hash('sha256', json_encode([$package->id, $package->period, $package->fleet, $package->source_revision, $package->fingerprint, $package->payload], JSON_THROW_ON_ERROR));
    }

    public function accountHash(#[SensitiveParameter] string $employee): string
    {
        return hash_hmac('sha256', trim($employee), (string) config('app.key'));
    }

    public function latest(User $user, BidPackage $package, string $seat): ?SubmittedBidSnapshot
    {
        return SubmittedBidSnapshot::query()->where('user_id', $user->id)->where('bid_package_id', $package->id)
            ->where('seat', $seat)->orderByDesc('started_at')->orderByDesc('id')->first();
    }

    public function attemptId(User $user, BidPackage $package, string $seat): ?string
    {
        return BidSubmission::query()->where('user_id', $user->id)
            ->whereIn('bid_workspace_id', BidWorkspace::query()->where('user_id', $user->id)->where('bid_package_id', $package->id)->where('seat', $seat)->select('id'))
            ->whereNotNull('attempted_at')->orderByDesc('attempted_at')->orderByDesc('id')->value('id');
    }

    /** @return array<string, mixed>|null */
    public function evidence(SubmittedBidSnapshot $snapshot, User $user, BidPackage $package, string $seat): ?array
    {
        try {
            /** @throws DecryptException */
            $evidence = $snapshot->getAttribute('evidence');
        } catch (DecryptException) {
            return null;
        }
        if (! is_array($evidence) || $snapshot->user_id !== $user->id || $snapshot->bid_package_id !== $package->id || $snapshot->seat !== $seat
            || ($evidence['owner_id'] ?? null) !== $user->id || ($evidence['package_id'] ?? null) !== $package->id || ($evidence['seat'] ?? null) !== $seat
            || $snapshot->checked_at < $snapshot->started_at || $snapshot->checked_at->isFuture()
            || ! is_string($evidence['account_hash'] ?? null) || preg_match('/\A[a-f0-9]{64}\z/', $evidence['account_hash']) !== 1
            || ! is_string($evidence['package_hash'] ?? null) || ! array_key_exists('attempt_id', $evidence)
            || ($evidence['attempt_id'] !== null && ! is_string($evidence['attempt_id']))) {
            return null;
        }
        $context = $evidence['context'] ?? null;
        $numbers = $evidence['numbers'] ?? null;
        if (! is_array($context) || ($context['account_matches_input'] ?? false) !== true
            || ($context['period'] ?? null) !== $package->period || ($context['fleet'] ?? null) !== $package->fleet || ($context['seat'] ?? null) !== $seat
            || ! is_int($context['package_id'] ?? null) || $context['package_id'] < 1
            || ! is_int($context['package_revision'] ?? null) || $context['package_revision'] < 0
            || ! is_array($numbers) || ! array_is_list($numbers) || $numbers === [] || count($numbers) > 315) {
            return null;
        }
        foreach ($numbers as $number) {
            if (! is_int($number) || $number < 1 || $number > 9999) {
                return null;
            }
        }
        if (count(array_unique($numbers)) !== count($numbers)
            || ($user->crew_bid_employee_verified_at !== null && $user->crew_bid_employee_number !== null
                && ! hash_equals($evidence['account_hash'], $this->accountHash($user->crew_bid_employee_number)))) {
            return null;
        }

        return $evidence;
    }

    /** @param array{context: array<string, mixed>, numbers: list<int>} $report */
    public function store(User $user, BidPackage $package, string $seat, #[SensitiveParameter] string $employee, array $report, string $id, CarbonImmutable $startedAt, CarbonImmutable $checkedAt, string $packageHash, string $source = 'personal_report', ?string $attemptId = null): SubmittedBidSnapshot
    {
        return DB::transaction(function () use ($user, $package, $seat, $employee, $report, $id, $startedAt, $checkedAt, $packageHash, $source, $attemptId): SubmittedBidSnapshot {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            $current = BidPackage::query()->lockForUpdate()->findOrFail($package->id);
            $this->authorize($owner, $current, $seat);
            app(CrewBidOperationGuard::class)->assertAuthorized();
            if (! hash_equals($packageHash, $this->packageHash($current))) {
                throw new BidPortalException('The package changed during the K4 check. Refresh again for this package.');
            }
            $context = $report['context'];
            if (($context['account_matches_input'] ?? false) !== true || ($context['period'] ?? null) !== $current->period
                || ($context['fleet'] ?? null) !== $current->fleet || ($context['seat'] ?? null) !== $seat
                || ! is_int($context['package_id'] ?? null) || $context['package_id'] < 1
                || ! is_int($context['package_revision'] ?? null) || $context['package_revision'] < 0) {
                throw new BidPortalException('Your authenticated K4 account, month, fleet or seat does not match this comparison.');
            }
            $numbers = $report['numbers'];
            if ($numbers === [] || count($numbers) > 315 || count(array_unique($numbers)) !== count($numbers)) {
                throw new BidPortalException('The submitted K4 report is missing, incomplete or duplicated. The last check is unchanged.');
            }
            foreach ($numbers as $number) {
                if ($number < 1 || $number > 9999) {
                    throw new BidPortalException('The submitted K4 report contains unsupported line numbers.');
                }
            }
            $account = $this->accountHash($employee);
            $previous = $this->latest($owner, $current, $seat);
            if ($owner->crew_bid_employee_verified_at !== null && $owner->crew_bid_employee_number !== null) {
                if (! hash_equals($account, $this->accountHash($owner->crew_bid_employee_number))) {
                    throw new BidPortalException('Use your verified K4 employee account for this check.');
                }
            } elseif ($previous !== null && ($previous->evidence['account_hash'] ?? null) !== $account) {
                throw new BidPortalException('Use the same K4 account as the last check, or verify your changed employee identity first.');
            }
            if ($previous !== null && ($previous->started_at > $startedAt || ($previous->started_at->equalTo($startedAt) && strcmp($previous->id, $id) >= 0))) {
                throw new BidPortalException('A newer K4 check already completed. Its result has been preserved.');
            }

            return SubmittedBidSnapshot::query()->create([
                'id' => $id, 'user_id' => $owner->id, 'bid_package_id' => $current->id, 'seat' => $seat,
                'started_at' => $startedAt, 'checked_at' => $checkedAt,
                'evidence' => ['owner_id' => $owner->id, 'package_id' => $current->id, 'seat' => $seat, 'account_hash' => $account, 'context' => $context, 'package_hash' => $packageHash,
                    'attempt_id' => $attemptId, 'numbers' => $numbers, 'rows' => $this->map($current, $seat, $numbers), 'source' => $source],
            ]);
        });
    }

    /**
     * @param  list<int>  $numbers
     * @return list<array{number: int, rank: int, line_id: ?string, annotation: string}>
     */
    public function map(BidPackage $package, string $seat, array $numbers): array
    {
        $byNumber = [];
        foreach ($package->payload['lines'] as $id => $line) {
            if (preg_match('/\A[FQR]([1-9]\d{0,3})\z/', $id, $match) === 1) {
                $byNumber[(int) $match[1]][] = $id;
            }
        }
        $rows = [];
        foreach ($numbers as $index => $number) {
            $matches = $byNumber[$number] ?? [];
            $id = count($matches) === 1 ? $matches[0] : null;
            $annotation = $matches === [] ? 'Missing from this package' : (count($matches) > 1 ? 'Ambiguous line mapping' : '');
            if ($id !== null && (int) ($package->payload['lines'][$id][$seat === 'CA' ? 'captains' : 'first_officers'] ?? 0) < 1) {
                $annotation = 'Not eligible for this seat';
            }
            $rows[] = ['number' => $number, 'rank' => $index + 1, 'line_id' => $id, 'annotation' => $annotation];
        }

        return $rows;
    }
}
