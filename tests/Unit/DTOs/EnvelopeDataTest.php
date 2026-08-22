<?php

namespace Tests\Unit\DTOs;

use App\DTOs\EnvelopeData;
use App\ValueObjects\WeightQuantity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EnvelopeDataTest extends TestCase
{
    public function test_it_serializes_typed_envelope_values_with_units(): void
    {
        $data = new EnvelopeData(
            sectionPresent: true,
            sourceType: 'takeoff_landing_report',
            plannedTakeoffWeight: new WeightQuantity(612400, 'LB'),
            maximumFieldTakeoffWeight: new WeightQuantity(347000, 'kg'),
            sourceWarnings: ['Source warning'],
        );

        $this->assertSame(['amount' => 612400, 'unit' => 'lb'], $data->toArray()['plannedTakeoffWeight']);
        $this->assertSame(['amount' => 347000, 'unit' => 'kg'], $data->toArray()['maximumFieldTakeoffWeight']);
        $this->assertSame(['Source warning'], $data->jsonSerialize()['sourceWarnings']);
    }

    public function test_weight_quantity_preserves_zero_and_rejects_invalid_values(): void
    {
        $this->assertSame(0, (new WeightQuantity(0, 'lb'))->amount);

        $this->expectException(InvalidArgumentException::class);

        new WeightQuantity(-1, 'lb');
    }
}
