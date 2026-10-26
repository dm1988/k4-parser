<?php

namespace Database\Factories;

use App\Models\FlightDelay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FlightDelay>
 */
class FlightDelayFactory extends Factory
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
            'flight_plan_key' => (string) Str::ulid(),
            'flight_context' => [
                'flight_number' => 'CKS241',
                'flight_date' => '2026-10-25',
                'tail_number' => 'N774CK',
                'departure' => 'PANC',
                'destination' => 'KMIA',
                'scheduled_departure_utc' => '2026-10-25T23:30:00+00:00',
            ],
            'revision' => 1,
        ];
    }

    public function partial(): static
    {
        return $this->state(fn (): array => [
            'logbook_received_at' => '2026-10-25 23:45:00',
            'delay_cause' => 'Awaiting revised payload.',
        ]);
    }

    public function completed(): static
    {
        return $this->partial()->state(fn (): array => [
            'new_payload_received_at' => '2026-10-26 00:15:00',
            'delay_started_at' => '2026-10-25 23:30:00',
            'delay_ended_at' => '2026-10-26 00:15:00',
            'actions_taken' => 'Coordinated with flight following.',
        ]);
    }
}
