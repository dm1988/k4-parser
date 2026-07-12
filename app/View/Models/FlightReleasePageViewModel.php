<?php

namespace App\View\Models;

use App\DTOs\AirportData;

readonly class FlightReleasePageViewModel
{
    /**
     * @param  array{
     *     departure?: string,
     *     destination?: string,
     *     alternate?: ?string,
     *     departure_airport?: AirportData|array<string, mixed>|null,
     *     destination_airport?: AirportData|array<string, mixed>|null,
     *     alternate_airport?: AirportData|array<string, mixed>|null,
     *     initial_altitude?: string,
     *     duration?: string,
     *     route?: string
     * }|null  $flightPlan
     */
    public function __construct(
        private ?array $flightPlan,
    ) {}

    public static function fromCurrentSession(): self
    {
        $flightPlan = session('flight_plan');

        return new self(is_array($flightPlan) ? $flightPlan : null);
    }

    public function hasFlightPlan(): bool
    {
        return $this->flightPlan !== null;
    }

    public function departure(): string
    {
        return $this->stringValue('departure');
    }

    public function destination(): string
    {
        return $this->stringValue('destination');
    }

    public function alternate(): ?string
    {
        return $this->nullableStringValue('alternate');
    }

    public function alternateLabel(): string
    {
        return $this->alternate() ?? 'None listed';
    }

    /**
     * @return array{name: string, location: string, identifiers: string}|null
     */
    public function departureAirport(): ?array
    {
        return $this->airportDetails('departure_airport');
    }

    /**
     * @return array{name: string, location: string, identifiers: string}|null
     */
    public function destinationAirport(): ?array
    {
        return $this->airportDetails('destination_airport');
    }

    /**
     * @return array{name: string, location: string, identifiers: string}|null
     */
    public function alternateAirport(): ?array
    {
        return $this->airportDetails('alternate_airport');
    }

    public function alternateAirportFallback(): string
    {
        if ($this->alternate() !== null) {
            return 'Airport details unavailable.';
        }

        return 'No alternate airport listed.';
    }

    public function initialAltitude(): string
    {
        return $this->stringValue('initial_altitude');
    }

    public function duration(): string
    {
        return $this->stringValue('duration');
    }

    public function route(): string
    {
        return $this->stringValue('route');
    }

    private function stringValue(string $key): string
    {
        $value = $this->flightPlan[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    private function nullableStringValue(string $key): ?string
    {
        $value = $this->flightPlan[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array{name: string, location: string, identifiers: string}|null
     */
    private function airportDetails(string $key): ?array
    {
        $airport = $this->flightPlan[$key] ?? null;

        if (! is_array($airport) && ! $airport instanceof AirportData) {
            return null;
        }

        $name = $this->airportField($airport, 'name');

        if ($name === '') {
            return null;
        }

        return [
            'name' => $name,
            'location' => $this->airportLocation($airport),
            'identifiers' => sprintf(
                'IATA %s · ICAO %s',
                $this->airportField($airport, 'iata', 'N/A'),
                $this->airportField($airport, 'icao', 'N/A'),
            ),
        ];
    }

    /**
     * @param  AirportData|array<string, mixed>  $airport
     */
    private function airportLocation(AirportData|array $airport): string
    {
        $city = $this->airportField($airport, 'city');
        $state = $this->airportField($airport, 'state');
        $country = $this->airportField($airport, 'country');

        return collect([$city, $state, $country])
            ->filter(static fn (string $value): bool => $value !== '')
            ->implode(', ');
    }

    /**
     * @param  AirportData|array<string, mixed>  $airport
     */
    private function airportField(AirportData|array $airport, string $field, string $default = ''): string
    {
        $value = data_get($airport, $field);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
