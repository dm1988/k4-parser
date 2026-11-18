<?php

namespace Database\Factories;

use App\Models\BidPackage;
use App\Models\BidWorkspace;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BidWorkspace> */
class BidWorkspaceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'bid_package_id' => BidPackage::factory(), 'seat' => 'FO', 'revision' => 0, 'preferences' => ['goals' => [], 'days_off' => [], 'start_city' => '', 'initialized' => true], 'selections' => []];
    }
}
