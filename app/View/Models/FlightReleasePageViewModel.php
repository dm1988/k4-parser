<?php

namespace App\View\Models;

use App\DTOs\AirportData;
use App\Enums\RouteTokenType;
use App\ValueObjects\FlightPlan;

readonly class FlightReleasePageViewModel
{
    private const AIRPORT_KEYS = [
        'departure_airport',
        'destination_airport',
        'alternate_airport',
    ];

    public function __construct(
        public ?FlightPlan $flightPlan,
    ) {}

    public static function fromCurrentSession(): self
    {
        $flightPlan = session('flight_plan');

        return new self(is_array($flightPlan) ? self::flightPlanFromArray($flightPlan) : null);
    }

    public function hasFlightPlan(): bool
    {
        return $this->flightPlan !== null;
    }

    public function departure(): string
    {
        return $this->flightPlan?->departure ?? '';
    }

    public function destination(): string
    {
        return $this->flightPlan?->destination ?? '';
    }

    public function alternate(): ?string
    {
        return $this->flightPlan?->alternate;
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
        return $this->flightPlan?->initialAltitude ?? '';
    }

    public function duration(): string
    {
        return $this->flightPlan?->duration ?? '';
    }

    public function route(): string
    {
        return $this->flightPlan?->route ?? '';
    }

    /**
     * @return list<array{
     *     value: string,
     *     type: RouteTokenType,
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
            $type = match (true) {
                $isSpeed => RouteTokenType::SPEED,
                $isAirway => RouteTokenType::AIRWAY,
                $isDirect => RouteTokenType::DIRECT,
                default => RouteTokenType::FIX,
            };

            return [
                'value' => $token,
                'type' => $type,
                'class' => $type->cssClass(),
            ];
        }, array_filter($tokens, static fn (string $token): bool => $token !== '')));
    }

    /**
     * @return array{name: string, location: string, identifiers: string}|null
     */
    private function airportDetails(string $key): ?array
    {
        $airport = match ($key) {
            'departure_airport' => $this->flightPlan?->departureAirport,
            'destination_airport' => $this->flightPlan?->destinationAirport,
            'alternate_airport' => $this->flightPlan?->alternateAirport,
            default => null,
        };

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
     * @param  array<string, mixed>  $flightPlan
     */
    private static function flightPlanFromArray(array $flightPlan): FlightPlan
    {
        foreach (self::AIRPORT_KEYS as $key) {
            $airport = $flightPlan[$key] ?? null;

            if (is_array($airport)) {
                $flightPlan[$key] = AirportData::fromApi($airport);
            }
        }

        return new FlightPlan(
            departure: is_string($flightPlan['departure'] ?? null) ? $flightPlan['departure'] : '',
            destination: is_string($flightPlan['destination'] ?? null) ? $flightPlan['destination'] : '',
            alternate: is_string($flightPlan['alternate'] ?? null) && $flightPlan['alternate'] !== '' ? $flightPlan['alternate'] : null,
            departureAirport: ($flightPlan['departure_airport'] ?? null) instanceof AirportData ? $flightPlan['departure_airport'] : null,
            destinationAirport: ($flightPlan['destination_airport'] ?? null) instanceof AirportData ? $flightPlan['destination_airport'] : null,
            alternateAirport: ($flightPlan['alternate_airport'] ?? null) instanceof AirportData ? $flightPlan['alternate_airport'] : null,
            initialAltitude: is_string($flightPlan['initial_altitude'] ?? null) ? $flightPlan['initial_altitude'] : '',
            duration: is_string($flightPlan['duration'] ?? null) ? $flightPlan['duration'] : '',
            route: is_string($flightPlan['route'] ?? null) ? $flightPlan['route'] : '',
        );
    }
}
