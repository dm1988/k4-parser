<?php

namespace Tests\Unit\DTOs;

use App\DTOs\FuelPlanData;
use App\ValueObjects\FuelQuantity;
use PHPUnit\Framework\TestCase;

class FuelPlanDataTest extends TestCase
{
    public function test_it_owns_release_level_fuel_quantities(): void
    {
        $fuel = new FuelPlanData(
            ramp: FuelQuantity::pounds(216800),
            taxi: FuelQuantity::pounds(1800),
            takeoff: FuelQuantity::pounds(215000),
            trip: FuelQuantity::pounds(150000),
            contingency: FuelQuantity::pounds(7500),
            alternate: FuelQuantity::pounds(9200),
            finalReserve: FuelQuantity::pounds(12000),
            estimatedLanding: FuelQuantity::pounds(65000),
        );

        $this->assertSame(216800.0, $fuel->ramp?->amount);
        $this->assertSame(150000.0, $fuel->trip?->amount);
        $this->assertSame('lb', $fuel->estimatedLanding?->unit);
        $this->assertSame(
            ['amount' => 1800.0, 'unit' => 'lb'],
            $fuel->toArray()['taxi'],
        );
    }

    public function test_it_allows_unavailable_fuel_figures_to_remain_null(): void
    {
        $fuel = new FuelPlanData(ramp: FuelQuantity::kilograms(1000));

        $this->assertNull($fuel->trip);
        $this->assertNull($fuel->toArray()['estimatedLanding']);
    }
}
