<?php

namespace Tests\Unit\DTOs;

use App\DTOs\FlightIdentityData;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class FlightIdentityDataTest extends TestCase
{
    public function test_it_owns_normalized_release_identity(): void
    {
        $identity = new FlightIdentityData(
            flightNumber: 'K4198',
            tripNumber: '0042',
            aircraftType: 'B77W',
            tailNumber: 'N12345',
            flightDate: CarbonImmutable::parse('2026-08-21'),
            releaseRevision: '3',
        );

        $this->assertSame([
            'flightNumber' => 'K4198',
            'tripNumber' => '0042',
            'aircraftType' => 'B77W',
            'tailNumber' => 'N12345',
            'flightDate' => '2026-08-21',
            'releaseRevision' => '3',
        ], $identity->toArray());
        $this->assertTrue((new \ReflectionClass($identity))->isReadOnly());
    }
}
