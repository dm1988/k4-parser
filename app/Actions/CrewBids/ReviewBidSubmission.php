<?php

namespace App\Actions\CrewBids;

use App\Exceptions\BidPortalException;
use App\Models\BidPackage;
use App\Models\BidSubmission;
use App\Models\BidWorkspace;
use App\Models\User;
use App\Services\CrewBids\SubmitPortalBid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use SensitiveParameter;

class ReviewBidSubmission
{
    public function __construct(private SubmitPortalBid $portal) {}

    public function workspace(User $user, BidPackage $package, string $seat): BidWorkspace
    {
        Gate::forUser($user)->authorize('use-crew-bids');
        abort_unless(config('crew_bids.submission.enabled'), 404);
        abort_unless(in_array($seat, ['CA', 'FO'], true), 404);
        abort_unless($package->published_at !== null && $package->archived_at === null, 404);

        return BidWorkspace::query()->where('user_id', $user->id)->where('bid_package_id', $package->id)->where('seat', $seat)->firstOrFail();
    }

    public function handle(User $user, BidPackage $package, string $seat, int $revision, #[SensitiveParameter] string $employee, #[SensitiveParameter] string $password): BidSubmission
    {
        $workspace = $this->workspace($user, $package, $seat);
        $this->validateWorkspace($workspace, $package, $revision);
        if (BidSubmission::query()->where('user_id', $user->id)->where('status', 'unknown')->exists()) {
            throw new BidPortalException('A previous submission has an unknown outcome. Check its portal report before starting another submission.');
        }
        $review = $this->portal->review($employee, $password, $workspace->selections);
        $this->context($review, $package, $seat);
        $review += ['workspace_revision' => $workspace->revision, 'workspace_hash' => $this->workspaceHash($workspace, $package),
            'selections' => $workspace->selections, 'local_revision' => $package->source_revision];
        $package->refresh();
        $workspace = $this->workspace($user, $package, $seat);
        $this->validateWorkspace($workspace, $package, $revision);
        if (! hash_equals($review['workspace_hash'], $this->workspaceHash($workspace, $package))) {
            throw new BidPortalException('Your saved list changed during the portal comparison. Start a fresh review.');
        }

        return BidSubmission::query()->create(['user_id' => $user->id, 'bid_workspace_id' => $workspace->id,
            'review' => $review, 'status' => 'reviewed', 'expires_at' => now()->addMinutes(10)]);
    }

    public function submit(User $user, BidSubmission $submission, #[SensitiveParameter] string $employee, #[SensitiveParameter] string $password): BidSubmission
    {
        $this->authorize($user, $submission);
        if ($submission->status !== 'reviewed' || $submission->expires_at->isPast()) {
            throw new BidPortalException('This review expired or has already been used. Refresh the status or start a fresh review.');
        }
        $review = $submission->review;
        $workspace = BidWorkspace::query()->where('user_id', $user->id)->findOrFail($submission->bid_workspace_id);
        $package = BidPackage::query()->findOrFail($workspace->bid_package_id);
        $this->workspace($user, $package, $workspace->seat);
        $this->validateWorkspace($workspace, $package, $review['workspace_revision']);
        $this->assertBinding($workspace, $package, $review);

        $result = $this->portal->submit($employee, $password, $review, function () use ($user, $submission, $review): void {
            DB::transaction(function () use ($user, $submission, $review): void {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                $this->authorize($lockedUser, $submission);
                if (BidSubmission::query()->where('user_id', $user->id)->where('status', 'unknown')->exists()) {
                    throw new BidPortalException('Another submission requires a read-only status check first.');
                }
                $workspace = BidWorkspace::query()->lockForUpdate()->where('user_id', $user->id)->findOrFail($submission->bid_workspace_id);
                $package = BidPackage::query()->lockForUpdate()->findOrFail($workspace->bid_package_id);
                $this->workspace($lockedUser, $package, $workspace->seat);
                $this->validateWorkspace($workspace, $package, $review['workspace_revision']);
                $this->assertBinding($workspace, $package, $review);
                $claimed = BidSubmission::query()->whereKey($submission->id)->where('status', 'reviewed')
                    ->where('expires_at', '>', now())->update(['status' => 'unknown', 'attempted_at' => now()]);
                if ($claimed !== 1) {
                    throw new BidPortalException('This review was already used or expired. Check its status before trying again.');
                }
                BidSubmission::query()->where('user_id', $user->id)->whereKeyNot($submission->id)
                    ->where('status', 'reviewed')->update(['status' => 'stale']);
            });
        });
        $submission->refresh()->update(['status' => $result['status'], 'reconciled_at' => $result['status'] === 'matched' ? now() : null]);

        return $submission->refresh();
    }

    public function reconcile(User $user, BidSubmission $submission, #[SensitiveParameter] string $employee, #[SensitiveParameter] string $password): BidSubmission
    {
        $this->authorize($user, $submission);
        if ($submission->status !== 'unknown') {
            throw new BidPortalException('Only an unknown submission needs a fresh report check.');
        }
        $result = $this->portal->reconcile($employee, $password, $submission->review);
        $submission->update(['status' => $result['status'], 'reconciled_at' => now()]);

        return $submission->refresh();
    }

    public function authorize(User $user, BidSubmission $submission): void
    {
        Gate::forUser($user)->authorize('use-crew-bids');
        abort_unless(config('crew_bids.submission.enabled'), 404);
        abort_unless($submission->user_id === $user->id, 404);
    }

    private function validateWorkspace(BidWorkspace $workspace, BidPackage $package, int $revision): void
    {
        if ($workspace->revision !== $revision || $workspace->selections === []) {
            throw new BidPortalException('Save your bid list and reopen submission before continuing.');
        }
        if (BidPackage::query()->where('period', $package->period)->where('fleet', $package->fleet)
            ->whereNotNull('published_at')->whereNull('archived_at')->where('id', '>', $package->id)->exists()) {
            throw new BidPortalException('Review the newer published package revision before submitting.');
        }
        $lines = $package->payload['lines'];
        foreach ($workspace->selections as $id) {
            if (! isset($lines[$id]) || (int) ($lines[$id][$workspace->seat === 'CA' ? 'captains' : 'first_officers'] ?? 0) < 1) {
                throw new BidPortalException('A saved choice is unavailable for this package and seat. Review your list.');
            }
        }
    }

    /** @param array<string, mixed> $review */
    private function context(array $review, BidPackage $package, string $seat): void
    {
        if ($review['context']['period'] !== $package->period || $review['context']['fleet'] !== $package->fleet || $review['context']['seat'] !== $seat) {
            throw new BidPortalException('Your authenticated portal month, fleet or seat does not match this saved bid list.');
        }
    }

    /** @param array<string, mixed> $review */
    private function assertBinding(BidWorkspace $workspace, BidPackage $package, array $review): void
    {
        if (! hash_equals($review['workspace_hash'], $this->workspaceHash($workspace, $package))) {
            throw new BidPortalException('The saved bid list or package changed. Start a fresh review.');
        }
    }

    private function workspaceHash(BidWorkspace $workspace, BidPackage $package): string
    {
        return hash('sha256', json_encode([$workspace->id, $workspace->revision, $workspace->selections, $workspace->seat,
            $package->id, $package->fingerprint, $package->source_revision, $package->payload], JSON_THROW_ON_ERROR));
    }
}
