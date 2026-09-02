<?php

namespace Tests\Unit\DTOs;

use App\DTOs\WeightBalance\WeightBalanceData;
use App\DTOs\WeightBalance\WeightBalanceFieldData;
use App\Enums\WeightBalanceSourceStatus;
use App\ValueObjects\WeightQuantity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WeightBalanceDataTest extends TestCase
{
    public function test_it_serializes_planned_values_limits_and_statuses_separately(): void
    {
        $confirmed = new WeightBalanceFieldData(
            plannedValue: new WeightQuantity(335858, 'LB'),
            sourceStatus: WeightBalanceSourceStatus::Confirmed,
        );
        $missing = new WeightBalanceFieldData(null, WeightBalanceSourceStatus::NotPresent);
        $data = new WeightBalanceData(
            basicOperatingWeight: $confirmed,
            plannedPayload: $missing,
            plannedTakeoffFuel: $missing,
            plannedZeroFuelWeight: $missing,
            plannedRampWeight: $missing,
            plannedTakeoffGrossWeight: $missing,
            plannedEstimatedLandingWeight: $missing,
        );

        $this->assertSame(335858, $data->toArray()['basicOperatingWeight']['plannedValue']['amount']);
        $this->assertSame('confirmed', $data->toArray()['basicOperatingWeight']['sourceStatus']);
        $this->assertSame('limit_unavailable', $data->toArray()['basicOperatingWeight']['limitStatus']);
        $this->assertNull($data->toArray()['plannedPayload']['plannedValue']);
        $this->assertTrue($data->hasSourceData());
    }

    public function test_it_rejects_a_confirmed_field_without_a_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WeightBalanceFieldData(null, WeightBalanceSourceStatus::Confirmed);
    }
}
