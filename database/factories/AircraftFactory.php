<?php

namespace Database\Factories;

use App\Models\Aircraft;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Aircraft>
 */
class AircraftFactory extends Factory
{
    protected $model = Aircraft::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tailNumber = fake()->unique()->numerify('N###CK');

        return [
            'tail_number' => $tailNumber,
            'manufacturer' => 'Boeing',
            'type' => fake()->randomElement(['Boeing 777-F', 'Boeing 777-300ERSF']),
            'model' => fake()->randomElement(['777-F', '777-300ERSF']),
            'is_active' => true,
            'airline' => 'Kalitta Air, LLC',
        ];
    }
}
