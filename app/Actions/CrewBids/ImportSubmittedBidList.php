<?php

namespace App\Actions\CrewBids;

use App\Exceptions\BidPortalException;
use App\Models\BidPackage;
use App\Models\BidWorkspace;
use App\Models\User;
use App\Services\CrewBids\ReadPersonalSubmittedBids;
use App\Services\CrewBids\StoreSubmittedBidSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use SensitiveParameter;

class ImportSubmittedBidList
{
    public function __construct(private ReadPersonalSubmittedBids $portal, private SaveBidWorkspace $save, private StoreSubmittedBidSnapshot $snapshots) {}

    public function workspace(User $user, BidPackage $package, string $seat): ?BidWorkspace
    {
        Gate::forUser($user)->authorize('use-crew-bids');
        abort_unless(in_array($seat, ['CA', 'FO'], true), 404);
        abort_unless($package->published_at !== null && $package->archived_at === null, 404);

        return BidWorkspace::query()->where('user_id', $user->id)->where('bid_package_id', $package->id)->where('seat', $seat)->first();
    }

    public function handle(User $user, BidPackage $package, string $seat, int $revision, bool $overwrite, #[SensitiveParameter] string $employee, #[SensitiveParameter] string $password): BidWorkspace
    {
        $workspace = $this->workspace($user, $package, $seat);
        if (($workspace->revision ?? 0) !== $revision) {
            throw new BidPortalException('Your bid list changed. Reopen Sync from K4 and review the saved list before retrying.');
        }
        if (($workspace->selections ?? []) !== [] && ! $overwrite) {
            throw new BidPortalException('Confirm replacement of your existing bid list before syncing from K4.');
        }
        $packageHash = $this->packageHash($package);
        $startedAt = CarbonImmutable::now();
        $checkId = (string) Str::ulid();
        $evidenceHash = $this->snapshots->packageHash($package);
        $attemptId = $this->snapshots->attemptId($user, $package, $seat);
        $report = $this->portal->read($employee, $password);
        $checkedAt = CarbonImmutable::now();
        if ($report['context']['period'] !== $package->period || $report['context']['fleet'] !== $package->fleet || $report['context']['seat'] !== $seat) {
            throw new BidPortalException('Your authenticated portal month, fleet or seat does not match this bid list.');
        }
        $byNumber = [];
        foreach ($package->payload['lines'] as $id => $line) {
            if ((int) ($line[$seat === 'CA' ? 'captains' : 'first_officers'] ?? 0) < 1
                || preg_match('/\A[FQR]([1-9]\d{0,3})\z/', $id, $match) !== 1) {
                continue;
            }
            $byNumber[(int) $match[1]][] = $id;
        }
        $selections = [];
        foreach ($report['numbers'] as $number) {
            if (count($byNumber[$number] ?? []) !== 1) {
                throw new BidPortalException('A submitted line is unavailable or ambiguous in this package and seat. Your bid list is unchanged.');
            }
            $selections[] = $byNumber[$number][0];
        }

        return DB::transaction(function () use ($user, $package, $seat, $revision, $workspace, $selections, $packageHash, $employee, $report, $checkId, $startedAt, $checkedAt, $evidenceHash, $attemptId): BidWorkspace {
            User::query()->lockForUpdate()->findOrFail($user->id);
            $current = BidPackage::query()->lockForUpdate()->findOrFail($package->id);
            $this->workspace($user, $current, $seat);
            if (! hash_equals($packageHash, $this->packageHash($current))) {
                throw new BidPortalException('The bid package changed during sync. Reopen Sync from K4 before retrying.');
            }

            $this->snapshots->store($user, $current, $seat, $employee, $report, $checkId, $startedAt, $checkedAt, $evidenceHash, 'sync', $attemptId);

            return $this->save->handle($user, $current, $seat, $revision, $workspace->preferences ?? [
                'goals' => [], 'days_off' => [], 'start_city' => '', 'initialized' => true,
            ], $selections);
        });
    }

    private function packageHash(BidPackage $package): string
    {
        return hash('sha256', json_encode([$package->period, $package->fleet, $package->source_revision, $package->fingerprint, $package->payload], JSON_THROW_ON_ERROR));
    }
}
