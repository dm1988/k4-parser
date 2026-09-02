<?php

namespace App\Services\FlightPlan;

use App\DTOs\WaypointData;
use App\ValueObjects\FuelQuantity;
use InvalidArgumentException;

class WaypointDataBuilder
{
    /**
     * @param  list<array<string, mixed>>  $source
     * @param  array<string, mixed>  $fuelSource
     * @return list<WaypointData>
     */
    public function fromExtracted(array $source, array $fuelSource): array
    {
        $fuelUnit = $this->confirmedFuelUnit($fuelSource);
        $waypoints = [];

        foreach ($source as $waypoint) {
            $identifier = $this->nullableString($waypoint['identifier'] ?? null);
            $coordinate = $this->nullableString($waypoint['coordinate'] ?? null);

            if ($identifier === null || $coordinate === null) {
                continue;
            }

            $waypoints[] = new WaypointData(
                identifier: $identifier,
                coordinate: $coordinate,
                legDurationMinutes: $this->legDurationMinutes($waypoint['time'] ?? null),
                cumulativeDurationMinutes: $this->cumulativeDurationMinutes($waypoint['total_time'] ?? null),
                remainingFuel: $this->extractedFuel($waypoint['remaining_fuel'] ?? null, $fuelUnit),
            );
        }

        return $waypoints;
    }

    /** @return list<WaypointData> */
    public function fromSerialized(mixed $source): array
    {
        if (! is_array($source)) {
            return [];
        }

        $waypoints = [];

        foreach ($source as $waypoint) {
            if (! is_array($waypoint)) {
                continue;
            }

            $identifier = $this->nullableString($waypoint['identifier'] ?? null);
            $coordinate = $this->nullableString($waypoint['coordinate'] ?? null);

            if ($identifier === null || $coordinate === null) {
                continue;
            }

            $waypoints[] = new WaypointData(
                identifier: $identifier,
                coordinate: $coordinate,
                legDurationMinutes: $this->nonNegativeInteger($waypoint['legDurationMinutes'] ?? null),
                cumulativeDurationMinutes: $this->nonNegativeInteger($waypoint['cumulativeDurationMinutes'] ?? null),
                remainingFuel: $this->serializedFuel($waypoint['remainingFuel'] ?? null),
            );
        }

        return $waypoints;
    }

    /** @param array<string, mixed> $fuelSource */
    private function confirmedFuelUnit(array $fuelSource): ?string
    {
        $units = [];

        foreach ($fuelSource as $quantity) {
            if (is_array($quantity) && is_string($quantity['unit'] ?? null)) {
                $units[] = strtolower($quantity['unit']);
            }
        }

        $units = array_values(array_unique($units));

        return count($units) === 1 && in_array($units[0], ['lb', 'kg'], true) ? $units[0] : null;
    }

    private function legDurationMinutes(mixed $value): ?int
    {
        return is_string($value) && preg_match('/^\d{3}$/', $value) === 1 ? (int) $value : null;
    }

    private function cumulativeDurationMinutes(mixed $value): ?int
    {
        if (! is_string($value) || preg_match('/^(?<hours>\d{2})\.(?<minutes>[0-5]\d)$/', $value, $matches) !== 1) {
            return null;
        }

        return ((int) $matches['hours'] * 60) + (int) $matches['minutes'];
    }

    private function extractedFuel(mixed $value, ?string $unit): ?FuelQuantity
    {
        if ($unit === null || ! is_string($value) || preg_match('/^\d{4}$/', $value) !== 1) {
            return null;
        }

        return new FuelQuantity((int) $value * 100, $unit);
    }

    private function serializedFuel(mixed $value): ?FuelQuantity
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

    private function nonNegativeInteger(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value !== '' ? $value : null;
    }
}
