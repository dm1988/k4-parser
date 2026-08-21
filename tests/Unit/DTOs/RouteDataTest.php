<?php

namespace Tests\Unit\DTOs;

use App\DTOs\RouteData;
use App\ValueObjects\AirportCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RouteDataTest extends TestCase
{
    public function test_it_owns_typed_airports_and_route_details(): void
    {
        $route = new RouteData(
            departure: new AirportCode('KJFK'),
            destination: new AirportCode('KLAX'),
            alternate: new AirportCode('KONT'),
            route: 'DCT MERIT DCT',
            departureRunway: '31L',
            arrivalRunway: '25L',
            departureSid: 'DEEZZ5',
            arrivalStar: 'ANJLL4',
            distanceNauticalMiles: 2146,
        );

        $this->assertSame('KJFK', $route->departure->value);
        $this->assertSame('KLAX', $route->destination->value);
        $this->assertSame(2146, $route->distanceNauticalMiles);
        $this->assertSame('KONT', $route->toArray()['alternate']);
    }

    public function test_it_rejects_a_negative_route_distance(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Route distance must not be negative.');

        new RouteData(
            departure: new AirportCode('KJFK'),
            destination: new AirportCode('KLAX'),
            distanceNauticalMiles: -1,
        );
    }
}
