<?php

namespace Database\Factories;

use App\Models\Aircraft;
use App\Models\FlightEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<FlightEvent>
 */
class FlightEventFactory extends Factory
{
    protected $model = FlightEvent::class;

    public function configure(): static
    {
        return $this->afterMaking(function (FlightEvent $flightEvent): void {
            $this->syncAircraftAttributes($flightEvent);
        })->afterCreating(function (FlightEvent $flightEvent): void {
            $this->syncAircraftAttributes($flightEvent);

            if ($flightEvent->isDirty('tail_number')) {
                $flightEvent->save();
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::instance(fake()->dateTimeBetween('+0 hours', '+2 weeks'))->startOfHour();
        $end = $start->copy()->addHours(fake()->numberBetween(2, 14));
        $origin = fake()->randomElement(['CVG', 'LAX', 'JFK', 'ORD', 'ANC', 'MIA']);
        $destination = fake()->randomElement(collect(['AMS', 'HKG', 'ICN', 'NRT', 'SDF', 'SEA'])
            ->reject(fn (string $airport): bool => $airport === $origin)
            ->all());
        $flightNumber = sprintf('K4%d', fake()->numberBetween(100, 999));
        $type = fake()->randomElement(['flight', 'deadhead', 'duty']);
        $isDeadhead = $type === 'deadhead';

        return [
            'title' => sprintf('%s %s-%s', $flightNumber, $origin, $destination),
            'type' => $type,
            'start' => $start,
            'end' => $end,
            'timezone' => 'UTC',
            'metadata' => [
                'source' => 'factory',
                'station' => $destination,
            ],
            'type_label' => strtoupper($type),
            'type_description' => fake()->sentence(4),
            'type_icon' => fake()->randomElement(['plane', 'clock', 'briefcase']),
            'schedule_label' => sprintf('%s-%s', $origin, $destination),
            'duration_label' => sprintf('%d:%02d', $start->diffInHours($end), $start->diffInMinutes($end) % 60),
            'tail_number' => null,
            'origin' => $origin,
            'destination' => $destination,
            'is_deadhead' => $isDeadhead,
            'badge_color' => fake()->randomElement(['blue', 'green', 'orange', 'red']),
            'download_url' => fake()->optional()->url(),
            'download_id' => fake()->optional()->uuid(),
            'trip_id' => (string) fake()->numberBetween(1000, 9999),
            'flight_number' => $flightNumber,
            'status' => fake()->randomElement(['scheduled', 'boarding', 'completed', 'delayed']),
            'aircraft_id' => Aircraft::factory(),
        ];
    }

    public function deadhead(): static
    {
        return $this->state(fn (): array => [
            'type' => 'deadhead',
            'type_label' => 'DEADHEAD',
            'is_deadhead' => true,
        ]);
    }

    public function withoutAircraft(): static
    {
        return $this->state(fn (): array => [
            'aircraft_id' => null,
            'tail_number' => null,
        ]);
    }

    public function forAircraft(Aircraft $aircraft): static
    {
        return $this->state(fn (): array => [
            'aircraft_id' => $aircraft->getKey(),
            'tail_number' => $aircraft->tail_number,
        ]);
    }

    private function syncAircraftAttributes(FlightEvent $flightEvent): void
    {
        if ($flightEvent->aircraft_id === null || ! empty($flightEvent->tail_number)) {
            return;
        }

        $aircraft = $flightEvent->aircraft;

        if ($aircraft !== null) {
            $flightEvent->tail_number = $aircraft->tail_number;
        }
    }
}
