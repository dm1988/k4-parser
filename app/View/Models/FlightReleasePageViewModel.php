<?php

namespace App\View\Models;

use App\DTOs\AirportData;
use App\Enums\RouteTokenType;
use App\ValueObjects\FlightPlan;
use Locale;

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

    /** @param array<string, mixed>|null $flightPlan */
    public static function fromArray(?array $flightPlan): self
    {
        return new self($flightPlan === null ? null : self::flightPlanFromArray($flightPlan));
    }

    public function hasFlightPlan(): bool
    {
        return $this->flightPlan !== null;
    }

    public function departure(): string
    {
        return $this->flightPlan === null ? '' : $this->flightPlan->departure;
    }

    public function destination(): string
    {
        return $this->flightPlan === null ? '' : $this->flightPlan->destination;
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
     * @return array{name: string, location: string, iata: string, icao: string}|null
     */
    public function departureAirport(): ?array
    {
        return $this->airportDetails('departure_airport');
    }

    /**
     * @return array{name: string, location: string, iata: string, icao: string}|null
     */
    public function destinationAirport(): ?array
    {
        return $this->airportDetails('destination_airport');
    }

    /**
     * @return array{name: string, location: string, iata: string, icao: string}|null
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
        return $this->flightPlan === null ? '' : $this->flightPlan->initialAltitude;
    }

    public function departureRunway(): ?string
    {
        return $this->flightPlan?->departureRunway;
    }

    public function arrivalRunway(): ?string
    {
        return $this->flightPlan?->arrivalRunway;
    }

    public function departureSid(): ?string
    {
        return $this->flightPlan?->departureSid;
    }

    public function arrivalStar(): ?string
    {
        return $this->flightPlan?->arrivalStar;
    }

    public function hasPlannedRunways(): bool
    {
        return $this->departureRunway() !== null || $this->arrivalRunway() !== null;
    }

    /**
     * @return list<array{label: string, airports: string, coordinates: string, scenario: string}>
     */
    public function etps(): array
    {
        return $this->flightPlan === null ? [] : $this->flightPlan->etps;
    }

    /**
     * @param  array{label: string, airports: string, coordinates: string, scenario: string}  $etp
     * @return list<string>
     */
    public function etpAirports(array $etp): array
    {
        return array_values(array_filter(
            explode('-', $etp['airports']),
            static fn (string $airport): bool => $airport !== '',
        ));
    }

    public function eentCoordinates(): ?string
    {
        return $this->flightPlan?->eentCoordinates;
    }

    public function eexpCoordinates(): ?string
    {
        return $this->flightPlan?->eexpCoordinates;
    }

    public function hasEtopsData(): bool
    {
        return $this->etps() !== []
            || $this->eentCoordinates() !== null
            || $this->eexpCoordinates() !== null;
    }

    public function duration(): string
    {
        return $this->flightPlan === null ? '' : $this->flightPlan->duration;
    }

    public function route(): string
    {
        return $this->flightPlan === null ? '' : $this->flightPlan->route;
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
     * @return array{name: string, location: string, iata: string, icao: string}|null
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
            'iata' => $airport->iata !== '' ? $airport->iata : 'N/A',
            'icao' => $airport->icao !== '' ? $airport->icao : 'N/A',
        ];
    }

    private function airportLocation(AirportData $airport): string
    {
        return collect([
            $airport->city,
            $this->normalizedAirportState($airport->state),
            $this->normalizedAirportCountry($airport->country),
        ])
            ->filter(static fn (?string $value): bool => ! empty($value))
            ->implode(', ');
    }

    private function normalizedAirportState(?string $state): ?string
    {
        if ($state === null || ctype_digit(trim($state))) {
            return null;
        }

        return $state;
    }

    private function normalizedAirportCountry(string $country): string
    {
        $countryCode = strtoupper(trim($country));

        if (strlen($countryCode) !== 2 || ! ctype_alpha($countryCode)) {
            return $country;
        }

        $countryName = Locale::getDisplayRegion('-'.$countryCode, 'en');

        return $countryName !== '' ? $countryName : $country;
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
            departureRunway: self::nullableString($flightPlan, 'departure_runway'),
            arrivalRunway: self::nullableString($flightPlan, 'arrival_runway'),
            departureSid: self::nullableString($flightPlan, 'departure_sid'),
            arrivalStar: self::nullableString($flightPlan, 'arrival_star'),
            etps: self::etpsFromArray($flightPlan['etps'] ?? null),
            eentCoordinates: self::nullableString($flightPlan, 'eent_coordinates'),
            eexpCoordinates: self::nullableString($flightPlan, 'eexp_coordinates'),
            initialAltitude: is_string($flightPlan['initial_altitude'] ?? null) ? $flightPlan['initial_altitude'] : '',
            duration: is_string($flightPlan['duration'] ?? null) ? $flightPlan['duration'] : '',
            route: is_string($flightPlan['route'] ?? null) ? $flightPlan['route'] : '',
        );
    }

    /**
     * @param  array<string, mixed>  $flightPlan
     */
    private static function nullableString(array $flightPlan, string $key): ?string
    {
        $value = $flightPlan[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return list<array{label: string, airports: string, coordinates: string, scenario: string}>
     */
    private static function etpsFromArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $etps = [];

        foreach ($value as $etp) {
            if (! is_array($etp)) {
                continue;
            }

            $label = $etp['label'] ?? null;
            $airports = $etp['airports'] ?? null;
            $coordinates = $etp['coordinates'] ?? null;
            $scenario = $etp['scenario'] ?? null;

            if (! is_string($label)
                || ! is_string($airports)
                || ! is_string($coordinates)
                || ! is_string($scenario)) {
                continue;
            }

            $etps[] = compact('label', 'airports', 'coordinates', 'scenario');
        }

        return $etps;
    }
}
