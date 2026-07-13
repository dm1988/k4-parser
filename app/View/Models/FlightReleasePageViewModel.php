<?php

namespace App\View\Models;

use App\DTOs\AirportData;

readonly class FlightReleasePageViewModel
{
    private const AIRPORT_KEYS = [
        'departure_airport',
        'destination_airport',
        'alternate_airport',
    ];

    /**
     * @param  array{
     *     departure?: string,
     *     destination?: string,
     *     alternate?: ?string,
     *     departure_airport?: AirportData|null,
     *     destination_airport?: AirportData|null,
     *     alternate_airport?: AirportData|null,
     *     initial_altitude?: string,
     *     duration?: string,
     *     route?: string
     * }|null  $flightPlan
     */
    private ?array $flightPlan;

    public function __construct(?array $flightPlan)
    {
        $this->flightPlan = $this->normalizeFlightPlan($flightPlan);
    }

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

    /**
     * @return list<array{
     *     value: string,
     *     is_airway: bool,
     *     is_speed: bool,
     *     is_direct: bool,
     *     class: string
     * }>
     */
    public function routeTokens(): array
    {
        $tokens = preg_split('/\s+/', trim($this->route()));

        if ($tokens === false) {
            return [];
        }

        return array_values(array_map(function (string $token): array {
            $isAirway = preg_match('/^(?:[A-Z]\d+|Q\d+)$/', $token) === 1;
            $isSpeed = str_contains($token, '/');
            $isDirect = $token === 'DCT';

            return [
                'value' => $token,
                'is_airway' => $isAirway,
                'is_speed' => $isSpeed,
                'is_direct' => $isDirect,
                'class' => match (true) {
                    $isSpeed => 'text-amber-700',
                    $isAirway => 'font-bold text-[#1B365D]',
                    $isDirect => 'text-[#4A5568]/50',
                    default => 'text-[#0B0E14]',
                },
            ];
        }, array_filter($tokens, static fn (string $token): bool => $token !== '')));
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

        if (! $airport instanceof AirportData) {
            return null;
        }

        if ($airport->name === '') {
            return null;
        }

        return [
            'name' => $airport->name,
            'location' => $this->airportLocation($airport),
            'identifiers' => sprintf(
                'IATA %s · ICAO %s',
                $airport->iata !== '' ? $airport->iata : 'N/A',
                $airport->icao !== '' ? $airport->icao : 'N/A',
            ),
        ];
    }

    private function airportLocation(AirportData $airport): string
    {
        return collect([$airport->city, $airport->state, $airport->country])
            ->filter(static fn (?string $value): bool => ! empty($value))
            ->implode(', ');
    }

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
    private function normalizeFlightPlan(?array $flightPlan): ?array
    {
        if ($flightPlan === null) {
            return null;
        }

        foreach (self::AIRPORT_KEYS as $key) {
            $airport = $flightPlan[$key] ?? null;

            if (is_array($airport)) {
                $flightPlan[$key] = AirportData::fromApi($airport);
            }
        }

        return $flightPlan;
    }
}
