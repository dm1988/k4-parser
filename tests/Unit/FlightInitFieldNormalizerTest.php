<?php

namespace Tests\Unit;

use App\Enums\AltitudeUnit;
use App\Services\FlightPlan\FlightInitFieldNormalizer;
use PHPUnit\Framework\TestCase;

class FlightInitFieldNormalizerTest extends TestCase
{
    public function test_it_normalizes_feet_and_metric_initial_altitudes_with_source_evidence(): void
    {
        $normalizer = new FlightInitFieldNormalizer;

        $feet = $normalizer->initialAltitude('F330');
        $meters = $normalizer->initialAltitude('S0890');

        $this->assertSame(33000, $feet?->value);
        $this->assertSame(AltitudeUnit::Feet, $feet?->unit);
        $this->assertTrue($feet?->isFlightLevel);
        $this->assertSame(8900, $meters?->value);
        $this->assertSame(AltitudeUnit::Meters, $meters?->unit);
        $this->assertTrue($meters?->isFlightLevel);
    }

    public function test_it_rejects_malformed_or_missing_initial_altitudes(): void
    {
        $normalizer = new FlightInitFieldNormalizer;

        $this->assertNull($normalizer->initialAltitude(null));
        $this->assertNull($normalizer->initialAltitude(''));
        $this->assertNull($normalizer->initialAltitude('FL330'));
        $this->assertNull($normalizer->initialAltitude('S89O'));
        $this->assertNull($normalizer->initialAltitude('X330'));
    }

    public function test_it_normalizes_supported_acars_init_dates(): void
    {
        $normalizer = new FlightInitFieldNormalizer;

        $this->assertSame('01', $normalizer->acarsInitDate(' 01 '));
        $this->assertSame('31', $normalizer->acarsInitDate('31'));
        $this->assertNull($normalizer->acarsInitDate('1'));
        $this->assertNull($normalizer->acarsInitDate('00'));
        $this->assertNull($normalizer->acarsInitDate('32'));
        $this->assertNull($normalizer->acarsInitDate(11));
    }

    public function test_it_normalizes_supported_employee_numbers(): void
    {
        $normalizer = new FlightInitFieldNormalizer;

        $this->assertSame('4827', $normalizer->employeeNumber(' 4827 '));
        $this->assertSame('72860', $normalizer->employeeNumber('72860'));
        $this->assertSame('123456', $normalizer->employeeNumber('123456'));
        $this->assertNull($normalizer->employeeNumber('123'));
        $this->assertNull($normalizer->employeeNumber('1234567'));
        $this->assertNull($normalizer->employeeNumber('48A7'));
        $this->assertNull($normalizer->employeeNumber(4827));
    }
}
