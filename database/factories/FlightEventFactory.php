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
    private const AIRPORTS = [
        'CVG' => ['code_icao' => 'KCVG', 'name' => 'Cincinnati/Northern Kentucky International', 'city' => 'Cincinnati', 'timezone' => 'America/New_York'],
        'ANC' => ['code_icao' => 'PANC', 'name' => 'Ted Stevens Anchorage International', 'city' => 'Anchorage', 'timezone' => 'America/Anchorage'],
        'LAX' => ['code_icao' => 'KLAX', 'name' => 'Los Angeles International', 'city' => 'Los Angeles', 'timezone' => 'America/Los_Angeles'],
        'JFK' => ['code_icao' => 'KJFK', 'name' => 'John F Kennedy International', 'city' => 'New York', 'timezone' => 'America/New_York'],
        'AMS' => ['code_icao' => 'EHAM', 'name' => 'Amsterdam Airport Schiphol', 'city' => 'Amsterdam', 'timezone' => 'Europe/Amsterdam'],
        'HKG' => ['code_icao' => 'VHHH', 'name' => 'Hong Kong International', 'city' => 'Hong Kong', 'timezone' => 'Asia/Hong_Kong'],
        'ICN' => ['code_icao' => 'RKSI', 'name' => 'Incheon International', 'city' => 'Seoul', 'timezone' => 'Asia/Seoul'],
        'NRT' => ['code_icao' => 'RJAA', 'name' => 'Narita International', 'city' => 'Tokyo', 'timezone' => 'Asia/Tokyo'],
    ];

    protected $model = FlightEvent::class;

    public function configure(): static
    {
        return $this->afterMaking(function (FlightEvent $flightEvent): void {
            $this->syncAircraftAttributes($flightEvent);
        })->afterCreating(function (FlightEvent $flightEvent): void {
            $this->syncAircraftAttributes($flightEvent);

            if ($flightEvent->aircraft_id !== null) {
                $this->placeAfterPreviousFlight($flightEvent);
            }

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
        $originCode = fake()->randomElement(array_keys(self::AIRPORTS));
        $destinationCode = fake()->randomElement(array_values(array_diff(array_keys(self::AIRPORTS), [$originCode])));
        $origin = self::AIRPORTS[$originCode];
        $destination = self::AIRPORTS[$destinationCode];
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
            'title' => sprintf('%s %s-%s', $flightNumber, $originCode, $destinationCode),
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
                'origin' => array_merge(['code' => $originCode], $origin),
                'destination' => array_merge(['code' => $destinationCode], $destination),
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
            'schedule_label' => sprintf('%s-%s', $originCode, $destinationCode),
            'duration_label' => sprintf('%d:%02d', $start->diffInHours($end), $start->diffInMinutes($end) % 60),
            'tail_number' => null,
            'origin' => $originCode,
            'destination' => $destinationCode,
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

    private function placeAfterPreviousFlight(FlightEvent $flightEvent): void
    {
        $previous = FlightEvent::query()
            ->where('aircraft_id', $flightEvent->aircraft_id)
            ->where('id', '<', $flightEvent->getKey())
            ->orderByDesc('end')
            ->first();

        $originCode = $previous?->destination ?? $flightEvent->origin;

        if (! isset(self::AIRPORTS[$originCode])) {
            $originCode = fake()->randomElement(array_keys(self::AIRPORTS));
        }

        $destinationCode = $previous === null && isset(self::AIRPORTS[$flightEvent->destination]) && $flightEvent->destination !== $originCode
            ? $flightEvent->destination
            : fake()->randomElement(array_values(array_diff(array_keys(self::AIRPORTS), [$originCode])));
        $start = $previous === null
            ? Carbon::now('UTC')->subHours(fake()->numberBetween(24, 48))->startOfMinute()
            : Carbon::instance($previous->end)->addMinutes(fake()->numberBetween(120, 480))->startOfMinute();
        $end = $start->copy()->addMinutes(fake()->numberBetween(120, 840));
        $status = match (true) {
            $end->isPast() => 'Arrived',
            $start->isPast() => 'En Route',
            default => 'Scheduled',
        };
        $origin = self::AIRPORTS[$originCode];
        $destination = self::AIRPORTS[$destinationCode];
        $metadata = $flightEvent->metadata ?? [];
        $scheduledOut = $start->copy()->subMinutes(20);
        $scheduledIn = $end->copy()->addMinutes(15);

        $metadata['origin'] = array_merge(['code' => $originCode], $origin);
        $metadata['destination'] = array_merge(['code' => $destinationCode], $destination);
        $metadata['scheduled_out'] = $scheduledOut->toIso8601String();
        $metadata['estimated_out'] = $scheduledOut->toIso8601String();
        $metadata['actual_out'] = $status !== 'Scheduled' ? $scheduledOut->toIso8601String() : null;
        $metadata['scheduled_off'] = $start->toIso8601String();
        $metadata['estimated_off'] = $start->toIso8601String();
        $metadata['actual_off'] = $status !== 'Scheduled' ? $start->toIso8601String() : null;
        $metadata['scheduled_on'] = $end->toIso8601String();
        $metadata['estimated_on'] = $end->toIso8601String();
        $metadata['actual_on'] = $status === 'Arrived' ? $end->toIso8601String() : null;
        $metadata['scheduled_in'] = $scheduledIn->toIso8601String();
        $metadata['progress_percent'] = match ($status) {
            'Arrived' => 100,
            'En Route' => 50,
            default => 0,
        };
        $metadata['cancelled'] = false;

        $flightEvent->forceFill([
            'title' => sprintf('%s %s-%s', $flightEvent->flight_number, $originCode, $destinationCode),
            'start' => $start,
            'end' => $end,
            'origin' => $originCode,
            'destination' => $destinationCode,
            'schedule_label' => sprintf('%s-%s', $originCode, $destinationCode),
            'duration_label' => sprintf('%d:%02d', $start->diffInHours($end), $start->diffInMinutes($end) % 60),
            'status' => $status,
            'badge_color' => match ($status) {
                'Arrived' => 'green',
                'En Route' => 'blue',
                default => 'gray',
            },
            'metadata' => $metadata,
            'trip_id' => sprintf('%s-%s', $metadata['ident'] ?? $flightEvent->flight_number, $start->format('Ymd')),
        ])->save();
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
