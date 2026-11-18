<?php

namespace Database\Factories;

use App\Models\BidAwardReport;
use App\Models\BidPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BidAwardReport>
 */
class BidAwardReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bid_package_id' => BidPackage::factory(), 'period' => '2026-11', 'fleet' => '777',
            'fingerprint' => fake()->unique()->sha256(), 'source' => 'upload',
            'awards' => [
                '910001' => ['crew_id' => '910001', 'astro_id' => '1001', 'line' => '1', 'seat' => 'FO', 'name' => 'Example, Avery', 'choice' => 2],
                '910002' => ['crew_id' => '910002', 'astro_id' => null, 'line' => '1', 'seat' => 'CA', 'name' => 'Sample, Morgan', 'choice' => 1],
                '910003' => ['crew_id' => '910003', 'astro_id' => '1003', 'line' => '2', 'seat' => 'FO', 'name' => 'Unrelated, Taylor', 'choice' => 1],
            ],
        ];
    }
}
