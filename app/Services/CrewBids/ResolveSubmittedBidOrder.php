<?php

namespace App\Services\CrewBids;

use App\DTOs\SubmittedBidOrder;
use App\Models\BidPackage;
use App\Models\BidSubmission;
use App\Models\BidWorkspace;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;

class ResolveSubmittedBidOrder
{
    public function resolve(User $user, ?BidPackage $package, string $seat): SubmittedBidOrder
    {
        if ($package === null) {
            return new SubmittedBidOrder;
        }
        $workspace = BidWorkspace::query()->where('user_id', $user->id)->where('bid_package_id', $package->id)->where('seat', $seat)->first();
        if ($workspace === null) {
            return new SubmittedBidOrder;
        }
        $submission = BidSubmission::query()->where('user_id', $user->id)->where('bid_workspace_id', $workspace->id)
            ->where(fn ($query) => $query->whereNotNull('attempted_at')->orWhereIn('status', ['matched', 'unknown', 'not_matched']))
            ->orderByDesc('attempted_at')->orderByDesc('id')->first();
        if ($submission === null || $submission->status !== 'matched' || $submission->attempted_at === null
            || $submission->reconciled_at === null || $submission->reconciled_at < $submission->attempted_at) {
            return new SubmittedBidOrder;
        }
        try {
            $review = $submission->review;
        } catch (DecryptException) {
            return new SubmittedBidOrder;
        }
        $context = $review['context'] ?? [];
        if (($context['account_matches_input'] ?? false) !== true
            || ($context['period'] ?? null) !== $package->period
            || ($context['fleet'] ?? null) !== $package->fleet
            || ($context['seat'] ?? null) !== $seat
            || ! is_int($context['package_id'] ?? null) || $context['package_id'] < 1
            || ! is_int($context['package_revision'] ?? null) || $context['package_revision'] < 0
            || ($review['local_revision'] ?? null) !== $package->source_revision
            || (isset($review['local_package_fingerprint']) && $review['local_package_fingerprint'] !== $package->fingerprint)
            || (isset($review['local_payload_hash']) && $review['local_payload_hash'] !== hash('sha256', json_encode($package->payload, JSON_THROW_ON_ERROR)))) {
            return new SubmittedBidOrder;
        }
        $selections = $review['selections'] ?? null;
        $numbers = $review['numbers'] ?? null;
        if (! is_array($selections) || ! array_is_list($selections) || $selections === [] || ! is_array($numbers) || ! array_is_list($numbers)) {
            return new SubmittedBidOrder;
        }
        $ranks = [];
        $expectedNumbers = [];
        foreach ($selections as $index => $id) {
            if (! is_string($id) || preg_match('/\A[FQR]([1-9]\d{0,3})\z/', $id, $match) !== 1
                || isset($ranks[$id]) || in_array((int) $match[1], $expectedNumbers, true)) {
                return new SubmittedBidOrder;
            }
            $ranks[$id] = $index + 1;
            $expectedNumbers[] = (int) $match[1];
        }
        if ($numbers !== $expectedNumbers) {
            return new SubmittedBidOrder;
        }

        return new SubmittedBidOrder($ranks, $submission->reconciled_at);
    }
}
