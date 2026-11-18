<?php

namespace Database\Factories;

use App\Models\PilotSeniorityList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PilotSeniorityList>
 */
class PilotSeniorityListFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 1,
            'source_url' => 'https://crewbids.kalittaair.com/hyperlinks/Synthetic%20Seniority.pdf',
            'effective_date' => '2026-07-15',
            'fingerprint' => hash('sha256', 'synthetic-seniority'),
            'imported_by' => null,
            'imported_at' => now(),
            'pilots' => [
                '910001' => ['name' => 'Example, Avery', 'seniority' => 10],
                '910002' => ['name' => 'Sample, Morgan', 'seniority' => 20],
                '910003' => ['name' => 'Demo, Casey', 'seniority' => 30],
                '900000' => ['name' => 'Viewer, Taylor', 'seniority' => 40],
            ],
        ];
    }
}
