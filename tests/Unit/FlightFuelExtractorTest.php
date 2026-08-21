<?php

namespace Tests\Unit;

use App\Services\FlightPlan\Extractor\FlightFuelExtractor;
use PHPUnit\Framework\TestCase;

class FlightFuelExtractorTest extends TestCase
{
    public function test_it_extracts_scaled_and_exact_pound_quantities(): void
    {
        $result = (new FlightFuelExtractor)->extract(<<<'TEXT'
DEST RKSI 195.1 12.10 340 5549 M025
ALTN RKTU 005.6 00.21 150 0070 P002
HOLDING 006.2 00.30
RESERVE 006.9 00.29 TAKEOFF FUEL 214829
ADDNL 000.0 BALLAST 000.0 EST FUEL BURN 195116
INC BURN/1000 LBS: 0314 R/R PAD 001.0
TAXI 002.0 TTL RMP 216.8 EST LANDING FUEL: 019713
TEXT);

        $this->assertSame([
            'ramp' => ['amount' => 216800.0, 'unit' => 'lb'],
            'taxi' => ['amount' => 2000.0, 'unit' => 'lb'],
            'takeoff' => ['amount' => 214829.0, 'unit' => 'lb'],
            'trip' => ['amount' => 195116.0, 'unit' => 'lb'],
            'contingency' => null,
            'alternate' => ['amount' => 5600.0, 'unit' => 'lb'],
            'final_reserve' => ['amount' => 6900.0, 'unit' => 'lb'],
            'estimated_landing' => ['amount' => 19713.0, 'unit' => 'lb'],
        ], $result['data']);
    }

    public function test_it_keeps_quantities_null_when_the_unit_is_ambiguous(): void
    {
        $result = (new FlightFuelExtractor)->extract('TAXI 002.0 TTL RMP 216.8 TAKEOFF FUEL 214829');

        $this->assertNull($result['data']['ramp']);
        $this->assertNull($result['data']['taxi']);
        $this->assertNull($result['data']['takeoff']);
    }

    public function test_it_normalizes_kilogram_fuel_tables(): void
    {
        $result = (new FlightFuelExtractor)->extract(
            'INC BURN/1000 KGS: 0142 TAXI 001.5 TTL RMP 098.3 TAKEOFF FUEL 96800',
        );

        $this->assertSame(['amount' => 98300.0, 'unit' => 'kg'], $result['data']['ramp']);
        $this->assertSame(['amount' => 1500.0, 'unit' => 'kg'], $result['data']['taxi']);
        $this->assertSame(['amount' => 96800.0, 'unit' => 'kg'], $result['data']['takeoff']);
    }
}
