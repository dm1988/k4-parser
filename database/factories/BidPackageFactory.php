<?php

namespace Database\Factories;

use App\Models\BidPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BidPackage> */
class BidPackageFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $calendar = [];
        for ($day = 1; $day <= 30; $day++) {
            $calendar[sprintf('2026-11-%02d', $day)] = ['status' => $day <= 15 ? 'occupied' : 'off', 'activity' => $day <= 15 ? 'Trip / occupied' : 'Off'];
        }
        $line = ['id' => 'F1', 'type' => 'flying', 'credit' => 3900, 'captains' => 1, 'first_officers' => 2, 'crew' => '1 CA 2 FO', 'calendar' => $calendar, 'pairings' => [], 'start_city' => 'ANC', 'duty_days' => 15, 'carry_in' => 0];

        return [
            'imported_by' => User::factory()->admin(), 'period' => '2026-11', 'fleet' => '777',
            'source_revision' => 'LINES_4 / 94.PUBLISH', 'fingerprint' => fake()->unique()->sha256(),
            'published_at' => now(), 'payload' => ['lines' => ['F1' => $line, 'R2' => array_replace($line, ['id' => 'R2', 'type' => 'reserve', 'credit' => 3840])], 'trips' => [], 'coverage' => ['overview' => true, 'lines' => true, 'trips' => false]],
        ];
    }
}
