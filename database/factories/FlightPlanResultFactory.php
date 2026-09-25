<?php

namespace Database\Factories;

use App\Models\FlightPlanResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FlightPlanResult>
 */
class FlightPlanResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'result_key' => (string) Str::ulid(),
            'payload' => [
                'flight_plan_data' => [
                    'route' => [
                        'departure' => fake()->lexify('????'),
                        'destination' => fake()->lexify('????'),
                    ],
                ],
            ],
        ];
    }
}
