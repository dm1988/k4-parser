<?php

namespace App\View\Presenters\FlightRelease;

use App\DTOs\AirportData;
use App\Enums\AltitudeUnit;
use App\Enums\RouteTokenType;
use App\ValueObjects\InitialAltitude;
use App\View\Models\FlightPlanPageData;
use Illuminate\Support\Number;
use Locale;

final readonly class RoutePresenter
{
    public function __construct(private ?FlightPlanPageData $pageData) {}

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

    /** @return array{name: string, location: string, iata: string, icao: string}|null */
    public function departureAirport(): ?array
    {
        return $this->airportDetails($this->pageData?->departureAirport);
    }

    /** @return array{name: string, location: string, iata: string, icao: string}|null */
    public function destinationAirport(): ?array
    {
        return $this->airportDetails($this->pageData?->destinationAirport);
    }

    /** @return array{name: string, location: string, iata: string, icao: string}|null */
    public function alternateAirport(): ?array
    {
        return $this->airportDetails($this->pageData?->alternateAirport);
    }

    public function alternateAirportFallback(): string
    {
        return $this->alternate() !== null ? 'Airport details unavailable.' : 'No alternate airport listed.';
    }

    public function filedInitialAltitude(): string
    {
        return $this->formatInitialAltitude($this->pageData?->flightPlan->flightInit?->filedInitialAltitude);
    }

    public function fmsInitialAltitude(): string
    {
        return $this->formatInitialAltitude($this->pageData?->flightPlan->flightInit?->fmsInitialAltitude);
    }

    public function overviewInitialAltitude(): ?string
    {
        $altitude = $this->filedInitialAltitude();

        return $altitude === '' ? null : $altitude;
    }

    public function overviewDistance(): ?string
    {
        $distance = $this->pageData?->flightPlan->route->distanceNauticalMiles;

        return $distance === null ? null : Number::format($distance).' NM';
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

    public function route(): string
    {
        return $this->pageData?->flightPlan->route->route ?? '';
    }

    /** @return list<array{value: string, type: RouteTokenType, class: string}> */
    public function tokens(): array
    {
        $tokens = preg_split('/\s+/', trim($this->route()));

        if ($tokens === false) {
            return [];
        }

        return array_values(array_map(static function (string $token): array {
            $type = match (true) {
                str_contains($token, '/') => RouteTokenType::SPEED,
                preg_match('/^(?:[A-Z]\d+|Q\d+)$/', $token) === 1 => RouteTokenType::AIRWAY,
                $token === 'DCT' => RouteTokenType::DIRECT,
                default => RouteTokenType::FIX,
            };

            return ['value' => $token, 'type' => $type, 'class' => $type->cssClass()];
        }, array_filter($tokens, static fn (string $token): bool => $token !== '')));
    }

    private function formatInitialAltitude(?InitialAltitude $altitude): string
    {
        if ($altitude === null) {
            return '';
        }

        if ($altitude->isFlightLevel) {
            $wholeHundreds = intdiv($altitude->value, 100);
            $remainder = $altitude->value % 100;
            $level = str_pad((string) $wholeHundreds, 3, '0', STR_PAD_LEFT);

            if ($remainder !== 0) {
                $level .= '.'.rtrim(str_pad((string) $remainder, 2, '0', STR_PAD_LEFT), '0');
            }

            return 'FL'.$level.($altitude->unit === AltitudeUnit::Meters ? 'M' : '');
        }

        return Number::format($altitude->value).' '.$altitude->unit->abbreviation();
    }

    /** @return array{name: string, location: string, iata: string, icao: string}|null */
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
        ])->filter(static fn (?string $value): bool => ! empty($value))->implode(', ');
    }

    private function normalizedAirportState(?string $state): ?string
    {
        return $state === null || ctype_digit(trim($state)) ? null : $state;
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
