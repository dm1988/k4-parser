<?php

namespace App\Services\FlightPlan;

use App\DTOs\Weather\AirportWeatherData;
use App\DTOs\Weather\WeatherData;
use App\ValueObjects\AirportCode;
use InvalidArgumentException;

class WeatherDataBuilder
{
    /** @param array<string, mixed> $source */
    public function fromExtracted(array $source): ?WeatherData
    {
        return $this->build($source);
    }

    public function fromSerialized(mixed $source): ?WeatherData
    {
        return is_array($source) ? $this->build($source) : null;
    }

    /** @param array<string, mixed> $source */
    private function build(array $source): ?WeatherData
    {
        $weather = new WeatherData(
            departure: $this->airportWeather($source['departure'] ?? null),
            destination: $this->airportWeather($source['destination'] ?? null),
            alternate: $this->airportWeather($source['alternate'] ?? null),
            raim: $this->nullableString($source['raim'] ?? null),
        );

        return $weather->hasReports() || $weather->raim !== null ? $weather : null;
    }

    private function airportWeather(mixed $source): ?AirportWeatherData
    {
        if (! is_array($source) || ! is_string($source['airport'] ?? null)) {
            return null;
        }

        try {
            return new AirportWeatherData(
                airport: new AirportCode($source['airport']),
                metars: $this->strings($source['metars'] ?? null),
                tafs: $this->strings($source['tafs'] ?? null),
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** @return list<string> */
    private function strings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $value): ?string => $this->nullableString($value),
            $values,
        )));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value !== '' ? $value : null;
    }
}
