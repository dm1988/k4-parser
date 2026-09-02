<?php

namespace App\DTOs\Weather;

use JsonSerializable;

final readonly class WeatherData implements JsonSerializable
{
    public function __construct(
        public ?AirportWeatherData $departure = null,
        public ?AirportWeatherData $destination = null,
        public ?AirportWeatherData $alternate = null,
        public ?string $raim = null,
    ) {}

    public function hasReports(): bool
    {
        foreach ([$this->departure, $this->destination, $this->alternate] as $airportWeather) {
            if ($airportWeather !== null && ($airportWeather->metars !== [] || $airportWeather->tafs !== [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{
     *     departure: array{airport: string, metars: list<string>, tafs: list<string>}|null,
     *     destination: array{airport: string, metars: list<string>, tafs: list<string>}|null,
     *     alternate: array{airport: string, metars: list<string>, tafs: list<string>}|null,
     *     raim: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'departure' => $this->departure?->toArray(),
            'destination' => $this->destination?->toArray(),
            'alternate' => $this->alternate?->toArray(),
            'raim' => $this->raim,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
