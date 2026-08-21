<?php

namespace App\View\Models;

use App\DTOs\AirportData;
use App\Enums\FlightPlanTask;
use App\Enums\FlightPlanTaskAvailability;
use App\Enums\RouteTokenType;
use Locale;

readonly class FlightReleasePageViewModel
{
    public function __construct(
        public ?FlightPlanPageData $pageData,
    ) {}

    public function hasFlightPlan(): bool
    {
        return $this->pageData !== null;
    }

    public function departure(): string
    {
        return $this->pageData?->flightPlan->route->departure->value ?? '';
    }

    public function destination(): string
    {
        return $this->pageData?->flightPlan->route->destination->value ?? '';
    }

    public function alternate(): ?string
    {
        return $this->pageData?->flightPlan->route->alternate?->value;
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
        return $this->airportDetails($this->pageData?->departureAirport);
    }

    /**
     * @return array{name: string, location: string, iata: string, icao: string}|null
     */
    public function destinationAirport(): ?array
    {
        return $this->airportDetails($this->pageData?->destinationAirport);
    }

    /**
     * @return array{name: string, location: string, iata: string, icao: string}|null
     */
    public function alternateAirport(): ?array
    {
        return $this->airportDetails($this->pageData?->alternateAirport);
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
        return $this->pageData->initialAltitude ?? '';
    }

    public function departureRunway(): ?string
    {
        return $this->pageData?->flightPlan->route->departureRunway;
    }

    public function arrivalRunway(): ?string
    {
        return $this->pageData?->flightPlan->route->arrivalRunway;
    }

    public function departureSid(): ?string
    {
        return $this->pageData?->flightPlan->route->departureSid;
    }

    public function arrivalStar(): ?string
    {
        return $this->pageData?->flightPlan->route->arrivalStar;
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
        return $this->pageData->etps ?? [];
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
        return $this->pageData?->eentCoordinates;
    }

    public function eexpCoordinates(): ?string
    {
        return $this->pageData?->eexpCoordinates;
    }

    public function hasEtopsData(): bool
    {
        return $this->pageData?->hasEtopsData() ?? false;
    }

    public function duration(): string
    {
        return $this->pageData->duration ?? '';
    }

    public function route(): string
    {
        return $this->pageData?->flightPlan->route->route ?? '';
    }

    public function availabilityFor(FlightPlanTask $task): FlightPlanTaskAvailability
    {
        return $this->pageData?->availabilityFor($task) ?? FlightPlanTaskAvailability::NotPresent;
    }

    /** @return array<string, FlightPlanTaskAvailability> */
    public function taskAvailability(): array
    {
        return $this->pageData?->taskAvailability() ?? [];
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
    private function airportDetails(?AirportData $airport): ?array
    {
        if ($airport === null || $airport->name === '') {
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
}
