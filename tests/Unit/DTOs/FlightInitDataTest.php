<?php

namespace Tests\Unit\DTOs;

use App\DTOs\FlightInitData;
use App\Enums\AltitudeUnit;
use App\ValueObjects\InitialAltitude;
use PHPUnit\Framework\TestCase;

class FlightInitDataTest extends TestCase
{
    public function test_it_serializes_the_explicit_acars_init_date(): void
    {
        $data = new FlightInitData(
            sectionPresent: true,
            acarsInitDate: '11',
            initialAltitude: new InitialAltitude(33000, AltitudeUnit::Feet, true),
        );

        $this->assertSame([
            'sectionPresent' => true,
            'acarsInitDate' => '11',
            'initialAltitude' => [
                'value' => 33000,
                'unit' => 'feet',
                'isFlightLevel' => true,
            ],
        ], $data->toArray());
    }
}
