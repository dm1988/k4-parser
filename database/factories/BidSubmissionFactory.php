<?php

namespace Database\Factories;

use App\Models\BidSubmission;
use App\Models\BidWorkspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BidSubmission>
 */
class BidSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bid_workspace_id' => BidWorkspace::factory(),
            'user_id' => fn (array $attributes): int => BidWorkspace::query()->findOrFail($attributes['bid_workspace_id'])->user_id,
            'review' => [
                'account_hash' => fake()->sha256(), 'portal_hash' => fake()->sha256(), 'workspace_hash' => fake()->sha256(),
                'workspace_revision' => 0, 'local_revision' => 'Synthetic release',
                'context' => ['account_matches_input' => true, 'period' => '2026-12', 'fleet' => '777', 'seat' => 'FO', 'package_id' => 4000, 'package_revision' => 0],
                'before' => [5, 6, 7], 'numbers' => [], 'selections' => [], 'added' => [], 'removed' => [5, 6, 7], 'reordered' => false,
            ],
            'status' => 'reviewed',
            'expires_at' => now()->addMinutes(10),
        ];
    }
}
