<?php

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\FuelQuantity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FuelQuantityTest extends TestCase
{
    public function test_it_normalizes_and_formats_supported_units(): void
    {
        $quantity = new FuelQuantity(216800, ' LBS ');

        $this->assertSame(216800.0, $quantity->amount);
        $this->assertSame('lb', $quantity->unit);
        $this->assertSame('216,800 LB', $quantity->format());
        $this->assertSame(['amount' => 216800.0, 'unit' => 'lb'], $quantity->toArray());
    }

    public function test_it_converts_between_pounds_and_kilograms_without_mutating(): void
    {
        $pounds = FuelQuantity::pounds(100);
        $kilograms = $pounds->toKilograms();

        $this->assertSame(100.0, $pounds->amount);
        $this->assertSame('lb', $pounds->unit);
        $this->assertEqualsWithDelta(45.359237, $kilograms->amount, 0.000001);
        $this->assertSame('kg', $kilograms->unit);
        $this->assertTrue($pounds->equals($kilograms));
    }

    public function test_it_rejects_negative_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Fuel amount must be a finite, non-negative number.');

        FuelQuantity::kilograms(-1);
    }

    public function test_it_rejects_unsupported_units(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Fuel unit must be pounds or kilograms.');

        new FuelQuantity(100, 'gallons');
    }
}
