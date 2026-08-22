<?php

namespace Tests\Unit;

use App\Services\FlightPlan\FlightInitFieldNormalizer;
use PHPUnit\Framework\TestCase;

class FlightInitFieldNormalizerTest extends TestCase
{
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
