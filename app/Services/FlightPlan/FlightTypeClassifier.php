<?php

namespace App\Services\FlightPlan;

use App\DTOs\AirportData;
use App\DTOs\RouteData;
use App\Enums\FlightType;
use Illuminate\Support\Str;
use ResourceBundle;

final class FlightTypeClassifier
{
    /** @param list<AirportData|null> $intermediateStops Explicit landing stops, excluding alternates and overflight waypoints. */
    public function classify(RouteData $route, array $intermediateStops = []): FlightType
    {
        $countries = [];
        $hasUnknownCountry = false;

        foreach ([$route->departureAirport, ...$intermediateStops, $route->destinationAirport] as $airport) {
            $country = $this->countryCode($airport?->country);

            if ($country === null) {
                $hasUnknownCountry = true;
            } else {
                $countries[$country] = true;
            }
        }

        if (count($countries) > 1) {
            return FlightType::International;
        }

        return $hasUnknownCountry ? FlightType::Unknown : FlightType::Domestic;
    }

    private function countryCode(?string $country): ?string
    {
        $country = Str::upper(trim($country ?? ''));

        if (in_array($country, ['USA', 'UNITED STATES OF AMERICA'], true)) {
            return 'US';
        }

        $regions = ResourceBundle::create('en', 'ICUDATA-region')?->get('Countries');

        if (! $regions instanceof ResourceBundle || $country === '') {
            return null;
        }

        foreach ($regions as $code => $name) {
            if (is_string($code) && strlen($code) === 2 && $code !== 'ZZ'
                && is_string($name) && ($country === $code || $country === Str::upper($name))) {
                return $code;
            }
        }

        return null;
    }
}
