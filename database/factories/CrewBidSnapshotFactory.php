<?php

namespace Database\Factories;

use App\Models\CrewBidSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrewBidSnapshot> */
class CrewBidSnapshotFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['period' => '2026-11', 'fleet' => '777', 'seat' => 'FO', 'portal_package_id' => 4000, 'portal_revision' => 0, 'retrieved_at' => now(),
            'bidders' => [
                '910001' => ['name' => 'Example, Avery', 'seniority' => 10, 'numbers' => [1, 2]],
                '910002' => ['name' => 'Sample, Morgan', 'seniority' => 20, 'numbers' => [1, 2]],
                '910003' => ['name' => 'Demo, Casey', 'seniority' => 30, 'numbers' => [1, 2]],
            ]];
    }
}
