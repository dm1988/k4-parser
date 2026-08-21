<?php

namespace App\Actions;

use App\DTOs\AirportData;
use App\DTOs\FlightIdentityData;
use App\DTOs\FlightPlanData;
use App\DTOs\FuelPlanData;
use App\DTOs\RouteData;
use App\DTOs\ScheduleData;
use App\ValueObjects\AirportCode;
use App\ValueObjects\FuelQuantity;
use App\View\Models\FlightPlanPageData;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use InvalidArgumentException;

class BuildFlightPlanPageData
{
    /** @param array<string, mixed>|null $result */
    public function handle(?array $result): ?FlightPlanPageData
    {
        $normalized = $result['flight_plan_data'] ?? null;

        if (! is_array($normalized)) {
            return null;
        }

        try {
            $flightPlan = $this->flightPlan($normalized);
        } catch (InvalidArgumentException) {
            return null;
        }

        return new FlightPlanPageData(
            flightPlan: $flightPlan,
            departureAirport: $this->airport($result['departure_airport'] ?? null),
            destinationAirport: $this->airport($result['destination_airport'] ?? null),
            alternateAirport: $this->airport($result['alternate_airport'] ?? null),
            initialAltitude: $this->nullableString($result['initial_altitude'] ?? null),
            duration: $this->nullableString($result['duration'] ?? null),
            etps: $this->etps($result['etps'] ?? null),
            eentCoordinates: $this->nullableString($result['eent_coordinates'] ?? null),
            eexpCoordinates: $this->nullableString($result['eexp_coordinates'] ?? null),
        );
    }

    /** @param array<string, mixed> $data */
    private function flightPlan(array $data): FlightPlanData
    {
        $identity = $this->requiredArray($data, 'identity');
        $schedule = $this->requiredArray($data, 'schedule');
        $route = $this->requiredArray($data, 'route');
        $fuelPlan = $data['fuelPlan'] ?? null;

        return new FlightPlanData(
            identity: new FlightIdentityData(
                flightNumber: $this->nullableString($identity['flightNumber'] ?? null),
                tripNumber: $this->nullableString($identity['tripNumber'] ?? null),
                recallNumber: $this->nullableString($identity['recallNumber'] ?? null),
                aircraftType: $this->nullableString($identity['aircraftType'] ?? null),
                tailNumber: $this->nullableString($identity['tailNumber'] ?? null),
                flightDate: $this->flightDate($identity['flightDate'] ?? null),
                releaseRevision: $this->nullableString($identity['releaseRevision'] ?? null),
            ),
            schedule: ScheduleData::fromArray($schedule),
            route: new RouteData(
                departure: new AirportCode($this->requiredString($route, 'departure')),
                destination: new AirportCode($this->requiredString($route, 'destination')),
                alternate: $this->airportCode($route['alternate'] ?? null),
                route: $this->nullableString($route['route'] ?? null),
                departureRunway: $this->nullableString($route['departureRunway'] ?? null),
                arrivalRunway: $this->nullableString($route['arrivalRunway'] ?? null),
                departureSid: $this->nullableString($route['departureSid'] ?? null),
                arrivalStar: $this->nullableString($route['arrivalStar'] ?? null),
                distanceNauticalMiles: is_int($route['distanceNauticalMiles'] ?? null)
                    ? $route['distanceNauticalMiles']
                    : null,
            ),
            fuelPlan: is_array($fuelPlan) ? $this->fuelPlan($fuelPlan) : null,
        );
    }

    private function flightDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (InvalidFormatException) {
            return null;
        }

        return $date !== null && $date->toDateString() === $value ? $date : null;
    }

    /** @param array<string, mixed> $data */
    private function fuelPlan(array $data): ?FuelPlanData
    {
        $quantities = [
            'ramp' => $this->fuelQuantity($data['ramp'] ?? null),
            'taxi' => $this->fuelQuantity($data['taxi'] ?? null),
            'takeoff' => $this->fuelQuantity($data['takeoff'] ?? null),
            'trip' => $this->fuelQuantity($data['trip'] ?? null),
            'contingency' => $this->fuelQuantity($data['contingency'] ?? null),
            'alternate' => $this->fuelQuantity($data['alternate'] ?? null),
            'finalReserve' => $this->fuelQuantity($data['finalReserve'] ?? null),
            'estimatedLanding' => $this->fuelQuantity($data['estimatedLanding'] ?? null),
        ];

        if (array_filter($quantities, static fn (?FuelQuantity $quantity): bool => $quantity !== null) === []) {
            return null;
        }

        return new FuelPlanData(...$quantities);
    }

    private function fuelQuantity(mixed $value): ?FuelQuantity
    {
        if (! is_array($value)) {
            return null;
        }

        $amount = $value['amount'] ?? null;
        $unit = $value['unit'] ?? null;

        if ((! is_int($amount) && ! is_float($amount)) || ! is_string($unit)) {
            return null;
        }

        try {
            return new FuelQuantity($amount, $unit);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function airportCode(mixed $value): ?AirportCode
    {
        return is_string($value) && $value !== '' ? new AirportCode($value) : null;
    }

    private function airport(mixed $value): ?AirportData
    {
        return is_array($value) ? AirportData::fromArray($value) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function requiredArray(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            throw new InvalidArgumentException("Flight plan {$key} data is required.");
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $key): string
    {
        $value = $this->nullableString($data[$key] ?? null);

        if ($value === null) {
            throw new InvalidArgumentException("Flight plan {$key} is required.");
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * @return list<array{label: string, airports: string, coordinates: string, scenario: string}>
     */
    private function etps(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $etps = [];

        foreach ($value as $etp) {
            if (! is_array($etp)) {
                continue;
            }

            $label = $this->nullableString($etp['label'] ?? null);
            $airports = $this->nullableString($etp['airports'] ?? null);
            $coordinates = $this->nullableString($etp['coordinates'] ?? null);
            $scenario = $this->nullableString($etp['scenario'] ?? null);

            if ($label === null || $airports === null || $coordinates === null || $scenario === null) {
                continue;
            }

            $etps[] = compact('label', 'airports', 'coordinates', 'scenario');
        }

        return $etps;
    }
}
