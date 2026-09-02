<?php

namespace Tests\Unit\DTOs\Weather;

use App\DTOs\Weather\AirportWeatherData;
use App\DTOs\Weather\WeatherData;
use App\ValueObjects\AirportCode;
use PHPUnit\Framework\TestCase;

class WeatherDataTest extends TestCase
{
    public function test_it_serializes_raw_reports_without_interpreting_them(): void
    {
        $weather = new WeatherData(
            departure: new AirportWeatherData(
                airport: new AirportCode('KAAA'),
                metars: ['METAR KAAA 250553Z 22006KT 10SM CLR 14/06 A2991'],
                tafs: ['TAF KAAA 250521Z 2506/2612 VRB05KT P6SM SKC'],
            ),
            raim: 'PASSED RAIM REQUIREMENTS FOR PRIMARY NAVIGATION VALID FROM 1020Z TO 1240Z',
        );

        $this->assertTrue($weather->hasReports());
        $this->assertSame([
            'departure' => [
                'airport' => 'KAAA',
                'metars' => ['METAR KAAA 250553Z 22006KT 10SM CLR 14/06 A2991'],
                'tafs' => ['TAF KAAA 250521Z 2506/2612 VRB05KT P6SM SKC'],
            ],
            'destination' => null,
            'alternate' => null,
            'raim' => 'PASSED RAIM REQUIREMENTS FOR PRIMARY NAVIGATION VALID FROM 1020Z TO 1240Z',
        ], $weather->toArray());
    }

    public function test_raim_alone_does_not_claim_weather_reports_are_present(): void
    {
        $weather = new WeatherData(raim: 'PASSED RAIM REQUIREMENTS');

        $this->assertFalse($weather->hasReports());
    }
}
