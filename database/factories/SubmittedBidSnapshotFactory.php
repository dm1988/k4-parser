<?php

namespace Database\Factories;

use App\Models\BidPackage;
use App\Models\SubmittedBidSnapshot;
use App\Models\User;
use App\Services\CrewBids\StoreSubmittedBidSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SubmittedBidSnapshot> */
class SubmittedBidSnapshotFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->crewBidsAccess(), 'bid_package_id' => BidPackage::factory(), 'seat' => 'FO',
            'started_at' => now()->subMinutes(2), 'checked_at' => now()->subMinute(),
            'evidence' => function (array $attributes): array {
                $package = BidPackage::query()->findOrFail($attributes['bid_package_id']);
                $snapshots = app(StoreSubmittedBidSnapshot::class);

                return ['owner_id' => $attributes['user_id'], 'package_id' => $package->id, 'seat' => $attributes['seat'],
                    'account_hash' => $snapshots->accountHash('900000'), 'package_hash' => $snapshots->packageHash($package),
                    'context' => ['account_matches_input' => true, 'period' => $package->period, 'fleet' => $package->fleet,
                        'seat' => $attributes['seat'], 'package_id' => 4000, 'package_revision' => 0],
                    'attempt_id' => null, 'numbers' => [1, 2], 'rows' => $snapshots->map($package, $attributes['seat'], [1, 2]), 'source' => 'personal_report'];
            },
        ];
    }
}
