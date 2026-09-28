<?php

namespace Tests\Unit\Services\FlightPlan;

use App\DTOs\AirportData;
use App\DTOs\RouteData;
use App\Enums\FlightType;
use App\Services\FlightPlan\FlightTypeClassifier;
use App\ValueObjects\AirportCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FlightTypeClassifierTest extends TestCase
{
    #[DataProvider('routes')]
    public function test_it_classifies_resolved_endpoint_countries(string $departure, string $destination, ?string $originCountry, ?string $destinationCountry, FlightType $expected): void
    {
        $route = new RouteData(
            departure: new AirportCode($departure),
            destination: new AirportCode($destination),
            departureAirport: $this->airport($departure, $originCountry),
            destinationAirport: $this->airport($destination, $destinationCountry),
            alternate: new AirportCode('CYVR'),
            alternateAirport: $this->airport('CYVR', 'CA'),
            route: 'DCT CYVR DCT',
        );

        $this->assertSame($expected, (new FlightTypeClassifier)->classify($route));
    }

    public static function routes(): iterable
    {
        yield 'Alaska to CONUS' => ['PANC', 'KMIA', 'US', 'United States', FlightType::Domestic];
        yield 'Honolulu to CONUS' => ['PHNL', 'KLAX', 'USA', 'US', FlightType::Domestic];
        yield 'Maui to CONUS' => ['PHOG', 'KJFK', 'United States of America', 'US', FlightType::Domestic];
        yield 'CONUS to Alaska' => ['KJFK', 'PANC', ' us ', 'United States', FlightType::Domestic];
        yield 'Alaska to Japan' => ['PANC', 'RJAA', 'US', 'JP', FlightType::International];
        yield 'Japan to CONUS' => ['RJAA', 'KLAX', 'Japan', 'US', FlightType::International];
        yield 'CONUS to Canada' => ['KLAX', 'CYVR', 'US', 'CA', FlightType::International];
        yield 'Canadian domestic' => ['CYVR', 'CYYZ', 'ca', 'Canada', FlightType::Domestic];
        yield 'distinct territory' => ['PGUM', 'KLAX', 'GU', 'US', FlightType::International];
        yield 'missing origin' => ['PANC', 'KLAX', null, 'US', FlightType::Unknown];
        yield 'missing destination' => ['PANC', 'KLAX', 'US', null, FlightType::Unknown];
        yield 'both missing' => ['PANC', 'KLAX', null, null, FlightType::Unknown];
        yield 'blank country' => ['PANC', 'KLAX', ' ', 'US', FlightType::Unknown];
        yield 'unknown code' => ['PANC', 'KLAX', 'ZZ', 'US', FlightType::Unknown];
        yield 'invalid country' => ['PANC', 'KLAX', 'Nowhere', 'Nowhere', FlightType::Unknown];
    }

    public function test_it_accounts_for_explicit_landing_stops(): void
    {
        $route = new RouteData(
            departure: new AirportCode('PANC'),
            destination: new AirportCode('KLAX'),
            departureAirport: $this->airport('PANC', 'US'),
            destinationAirport: $this->airport('KLAX', 'US'),
        );
        $classifier = new FlightTypeClassifier;

        $this->assertSame(FlightType::Domestic, $classifier->classify($route, [$this->airport('KSEA', 'US')]));
        $this->assertSame(FlightType::International, $classifier->classify($route, [$this->airport('CYVR', 'CA')]));
        $this->assertSame(FlightType::Unknown, $classifier->classify($route, [null]));
        $this->assertSame(FlightType::International, $classifier->classify($route, [null, $this->airport('CYVR', 'CA')]));
    }

    private function airport(string $icao, ?string $country): ?AirportData
    {
        return $country === null ? null : new AirportData($icao, '', '', '', null, $country);
    }
}
