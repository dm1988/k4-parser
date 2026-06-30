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
        $airports = [
            'KCVG' => ['code' => 'CVG', 'name' => 'Cincinnati/Northern Kentucky International', 'city' => 'Cincinnati', 'timezone' => 'America/New_York'],
            'PANC' => ['code' => 'ANC', 'name' => 'Ted Stevens Anchorage International', 'city' => 'Anchorage', 'timezone' => 'America/Anchorage'],
            'KLAX' => ['code' => 'LAX', 'name' => 'Los Angeles International', 'city' => 'Los Angeles', 'timezone' => 'America/Los_Angeles'],
            'KJFK' => ['code' => 'JFK', 'name' => 'John F Kennedy International', 'city' => 'New York', 'timezone' => 'America/New_York'],
            'EHAM' => ['code' => 'AMS', 'name' => 'Amsterdam Airport Schiphol', 'city' => 'Amsterdam', 'timezone' => 'Europe/Amsterdam'],
            'VHHH' => ['code' => 'HKG', 'name' => 'Hong Kong International', 'city' => 'Hong Kong', 'timezone' => 'Asia/Hong_Kong'],
            'RKSI' => ['code' => 'ICN', 'name' => 'Incheon International', 'city' => 'Seoul', 'timezone' => 'Asia/Seoul'],
            'RJAA' => ['code' => 'NRT', 'name' => 'Narita International', 'city' => 'Tokyo', 'timezone' => 'Asia/Tokyo'],
        ];
        $originIcao = fake()->randomElement(array_keys($airports));
        $destinationIcao = fake()->randomElement(array_values(array_diff(array_keys($airports), [$originIcao])));
        $origin = $airports[$originIcao];
        $destination = $airports[$destinationIcao];
        $status = fake()->randomElement(['Scheduled', 'En Route', 'Arrived', 'Cancelled']);
        $start = (match ($status) {
            'Scheduled', 'Cancelled' => Carbon::instance(fake()->dateTimeBetween('+1 hour', '+2 weeks')),
            'En Route' => Carbon::instance(fake()->dateTimeBetween('-8 hours', '-30 minutes')),
            default => Carbon::instance(fake()->dateTimeBetween('-2 weeks', '-3 hours')),
        })->startOfMinute();
        $end = $start->copy()->addMinutes(fake()->numberBetween(120, 840));
        $number = fake()->numberBetween(100, 999);
        $flightNumber = sprintf('K4%d', $number);
        $ident = sprintf('CKS%d', $number);
        $scheduledOut = $start->copy()->subMinutes(20);
        $scheduledIn = $end->copy()->addMinutes(15);
        $isOperating = in_array($status, ['En Route', 'Arrived'], true);
        $isArrived = $status === 'Arrived';
        $distance = fake()->numberBetween(900, 6800);

        return [
            'title' => sprintf('%s %s-%s', $flightNumber, $origin['code'], $destination['code']),
            'type' => 'flight',
            'start' => $start,
            'end' => $end,
            'timezone' => 'UTC',
            'metadata' => [
                'source' => 'flightaware',
                'fa_flight_id' => sprintf('%s-%s-airline-%04x', $ident, $start->format('YmdHis'), fake()->numberBetween(0, 65535)),
                'ident' => $ident,
                'ident_icao' => $ident,
                'ident_iata' => $flightNumber,
                'operator' => 'CKS',
                'operator_icao' => 'CKS',
                'operator_iata' => 'K4',
                'registration' => null,
                'aircraft_type' => 'B77L',
                'origin' => array_merge(['code_icao' => $originIcao], $origin),
                'destination' => array_merge(['code_icao' => $destinationIcao], $destination),
                'scheduled_out' => $scheduledOut->toIso8601String(),
                'estimated_out' => $scheduledOut->copy()->addMinutes(fake()->numberBetween(0, 45))->toIso8601String(),
                'actual_out' => $isOperating ? $scheduledOut->copy()->addMinutes(fake()->numberBetween(0, 30))->toIso8601String() : null,
                'scheduled_off' => $start->toIso8601String(),
                'estimated_off' => $start->copy()->addMinutes(fake()->numberBetween(0, 45))->toIso8601String(),
                'actual_off' => $isOperating ? $start->copy()->addMinutes(fake()->numberBetween(0, 30))->toIso8601String() : null,
                'scheduled_on' => $end->toIso8601String(),
                'estimated_on' => $end->copy()->addMinutes(fake()->numberBetween(-20, 60))->toIso8601String(),
                'actual_on' => $isArrived ? $end->copy()->addMinutes(fake()->numberBetween(-20, 60))->toIso8601String() : null,
                'scheduled_in' => $scheduledIn->toIso8601String(),
                'route' => fake()->randomElement(['DCT', 'DCT MERIT DCT', 'PACOTS TRACK 2', 'NAT TRACK B']),
                'filed_altitude' => fake()->randomElement([310, 330, 350, 370, 390]),
                'filed_airspeed' => fake()->numberBetween(470, 510),
                'route_distance' => $distance,
                'progress_percent' => match ($status) {
                    'Arrived' => 100,
                    'En Route' => fake()->numberBetween(10, 90),
                    default => 0,
                },
                'cancelled' => $status === 'Cancelled',
                'diverted' => false,
            ],
            'type_label' => 'FLIGHT',
            'type_description' => sprintf('%s cargo flight', $ident),
            'type_icon' => 'plane',
            'schedule_label' => sprintf('%s-%s', $origin['code'], $destination['code']),
            'duration_label' => sprintf('%d:%02d', $start->diffInHours($end), $start->diffInMinutes($end) % 60),
            'tail_number' => null,
            'origin' => $origin['code'],
            'destination' => $destination['code'],
            'is_deadhead' => false,
            'badge_color' => match ($status) {
                'Arrived' => 'green',
                'Cancelled' => 'red',
                'En Route' => 'blue',
                default => 'gray',
            },
            'download_url' => null,
            'download_id' => fake()->uuid(),
            'trip_id' => sprintf('%s-%s', $ident, $start->format('Ymd')),
            'flight_number' => $flightNumber,
            'status' => $status,
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
        if ($flightEvent->aircraft_id === null) {
            return;
        }

        $aircraft = $flightEvent->aircraft;

        if ($aircraft !== null) {
            if (empty($flightEvent->tail_number)) {
                $flightEvent->tail_number = $aircraft->tail_number;
            }

            $metadata = $flightEvent->metadata ?? [];
            $metadata['registration'] ??= $aircraft->tail_number;
            $flightEvent->metadata = $metadata;
        }
    }
}
