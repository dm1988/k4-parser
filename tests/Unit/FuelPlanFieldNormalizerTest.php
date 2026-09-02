<?php

namespace Tests\Unit;

use App\Services\FlightPlan\FuelPlanFieldNormalizer;
use PHPUnit\Framework\TestCase;

class FuelPlanFieldNormalizerTest extends TestCase
{
    public function test_it_normalizes_cost_indexes_within_the_supported_range(): void
    {
        $normalizer = new FuelPlanFieldNormalizer;

        $this->assertSame(0, $normalizer->costIndex(0));
        $this->assertSame(200, $normalizer->costIndex(200));
        $this->assertSame(999, $normalizer->costIndex(999));
    }

    public function test_it_rejects_invalid_cost_indexes(): void
    {
        $normalizer = new FuelPlanFieldNormalizer;

        foreach ([-1, 1000, '200', 200.0, null, true] as $value) {
            $this->assertNull($normalizer->costIndex($value));
        }
    }
}
