<?php

namespace Database\Factories;

use App\Models\CrewBidPayProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrewBidPayProfile>
 */
class CrewBidPayProfileFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'settings' => ['show_compact' => false],
            'scenarios' => [],
            'revision' => 0,
        ];
    }
}
