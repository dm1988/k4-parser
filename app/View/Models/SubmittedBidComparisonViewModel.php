<?php

namespace App\View\Models;

use App\DTOs\SubmittedBidOrder;
use App\Models\BidPackage;
use App\Models\SubmittedBidSnapshot;
use App\Models\User;
use App\Services\CrewBids\StoreSubmittedBidSnapshot;
use Carbon\CarbonImmutable;

final readonly class SubmittedBidComparisonViewModel
{
    /** @param list<array{label: string, local_rank: ?int, submitted_rank: ?int, status: string, annotation: string}> $rows */
    public function __construct(
        public array $rows,
        public ?CarbonImmutable $checkedAt,
        public string $reason,
        public int $added,
        public int $removed,
        public bool $reordered,
        public bool $hasCheck,
    ) {}

    /** @param list<string> $selections */
    public static function make(User $user, BidPackage $package, string $seat, array $selections, SubmittedBidOrder $order, StoreSubmittedBidSnapshot $snapshots): self
    {
        $snapshot = $snapshots->latest($user, $package, $seat);
        $historicalPackage = null;
        if ($snapshot === null && ! $order->available()) {
            $snapshot = SubmittedBidSnapshot::query()->where('user_id', $user->id)->where('seat', $seat)
                ->whereIn('bid_package_id', BidPackage::query()->where('period', $package->period)->where('fleet', $package->fleet)->select('id'))
                ->orderByDesc('started_at')->orderByDesc('id')->first();
            if ($snapshot !== null && $snapshot->bid_package_id !== $package->id) {
                $historicalPackage = BidPackage::query()->find($snapshot->bid_package_id);
            }
        }
        $evidence = $snapshot === null ? null : $snapshots->evidence($snapshot, $user, $historicalPackage ?? $package, $seat);
        $submitted = [];
        if ($evidence !== null) {
            $originalRows = $snapshots->map($historicalPackage ?? $package, $seat, $evidence['numbers']);
            foreach ($snapshots->map($package, $seat, $evidence['numbers']) as $index => $row) {
                if ($row['annotation'] === 'Missing from this package') {
                    $row['line_id'] = $originalRows[$index]['line_id'];
                }
                $key = $row['line_id'] ?? 'K4:'.$row['number'];
                $submitted[$key] = ['label' => $row['line_id'] ?? 'K4 line '.$row['number'], 'rank' => $row['rank'], 'annotation' => $row['annotation']];
            }
        } elseif ($snapshot === null && $order->available()) {
            foreach ($order->ranks as $id => $rank) {
                $submitted[$id] = ['label' => $id, 'rank' => $rank, 'annotation' => 'Legacy confirmed report'];
            }
        }
        $hasCheck = $submitted !== [];
        $localRanks = array_flip($selections);
        $rows = [];
        foreach (array_unique([...$selections, ...array_keys($submitted)]) as $id) {
            $localRank = isset($localRanks[$id]) ? $localRanks[$id] + 1 : null;
            $submittedRank = $submitted[$id]['rank'] ?? null;
            $annotation = $submitted[$id]['annotation'] ?? '';
            if (isset($localRanks[$id])) {
                $line = $package->payload['lines'][$id] ?? null;
                if ($line === null) {
                    $annotation = 'Missing from this package';
                } elseif ((int) ($line[$seat === 'CA' ? 'captains' : 'first_officers'] ?? 0) < 1) {
                    $annotation = 'Not eligible for this seat';
                }
            }
            $status = ! $hasCheck ? 'Not checked' : ($localRank === null ? 'Only in K4 submitted bids'
                : ($submittedRank === null ? 'Only in My bid list' : ($localRank === $submittedRank ? 'Same rank' : 'Moved')));
            $rows[] = ['label' => $submitted[$id]['label'] ?? $id, 'local_rank' => $localRank, 'submitted_rank' => $submittedRank, 'status' => $status, 'annotation' => $annotation];
        }
        $submittedIds = array_keys($submitted);
        $retainedBefore = array_values(array_intersect($submittedIds, $selections));
        $retainedAfter = array_values(array_intersect($selections, $submittedIds));

        return new self($rows, $evidence !== null ? $snapshot->checked_at : $order->checkedAt,
            $historicalPackage !== null ? 'The last K4 check belongs to a different package revision' : ($order->available() ? '' : $order->unavailableReason),
            $hasCheck ? count(array_diff($selections, $submittedIds)) : 0,
            $hasCheck ? count(array_diff($submittedIds, $selections)) : 0,
            $hasCheck && $retainedBefore !== $retainedAfter, $hasCheck);
    }
}
