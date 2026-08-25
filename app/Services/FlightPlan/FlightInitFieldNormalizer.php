<?php

namespace App\Services\FlightPlan;

use App\Enums\AltitudeUnit;
use App\ValueObjects\InitialAltitude;

class FlightInitFieldNormalizer
{
    public function filedInitialAltitude(mixed $value): ?InitialAltitude
    {
        $value = is_string($value) ? strtoupper(trim($value)) : null;

        if ($value === null || preg_match('/^(?<prefix>[FASM])(?<level>\d{3,4})$/', $value, $matches) !== 1) {
            return null;
        }

        $unit = match ($matches['prefix']) {
            'F', 'A' => AltitudeUnit::Feet,
            'S', 'M' => AltitudeUnit::Meters,
        };

        return new InitialAltitude(
            value: (int) $matches['level'] * ($unit === AltitudeUnit::Feet ? 100 : 10),
            unit: $unit,
            isFlightLevel: in_array($matches['prefix'], ['F', 'S'], true),
        );
    }

    public function fmsInitialAltitude(mixed $value): ?InitialAltitude
    {
        $value = is_string($value) ? strtoupper(trim($value)) : null;

        if ($value === null || preg_match('/^F(?<level>\d{3})$/', $value, $matches) !== 1) {
            return null;
        }

        return new InitialAltitude(
            value: (int) $matches['level'] * 100,
            unit: AltitudeUnit::Feet,
            isFlightLevel: true,
        );
    }

    public function acarsInitDate(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value !== null && preg_match('/^(?:0[1-9]|[12]\d|3[01])$/', $value) === 1
            ? $value
            : null;
    }

    public function employeeNumber(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value !== null && preg_match('/^\d{4,6}$/', $value) === 1
            ? $value
            : null;
    }
}
