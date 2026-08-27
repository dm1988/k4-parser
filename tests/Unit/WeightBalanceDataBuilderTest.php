<?php

namespace Tests\Unit;

use App\DTOs\FuelPlanData;
use App\Enums\WeightBalanceSourceStatus;
use App\Services\FlightPlan\WeightBalanceDataBuilder;
use App\ValueObjects\FuelQuantity;
use PHPUnit\Framework\TestCase;

class WeightBalanceDataBuilderTest extends TestCase
{
    public function test_it_reuses_fuel_and_derives_ramp_weight_server_side(): void
    {
        $data = (new WeightBalanceDataBuilder)->build(
            $this->source(['planned_zero_fuel_weight' => 353858]),
            new FuelPlanData(
                ramp: FuelQuantity::pounds(225500),
                takeoff: FuelQuantity::pounds(223489),
            ),
        );

        $this->assertSame(223489, $data->plannedTakeoffFuel->plannedValue?->amount);
        $this->assertSame(579358, $data->plannedRampWeight->plannedValue?->amount);
        $this->assertSame('lb', $data->plannedRampWeight->plannedValue?->unit);
        $this->assertTrue($data->plannedRampWeight->derived);
        $this->assertSame(WeightBalanceSourceStatus::Confirmed, $data->plannedRampWeight->sourceStatus);
        $this->assertSame(WeightBalanceSourceStatus::LimitUnavailable, $data->plannedRampWeight->limitStatus);
    }

    public function test_it_does_not_convert_units_when_deriving_ramp_weight(): void
    {
        $data = (new WeightBalanceDataBuilder)->build(
            $this->source(['planned_zero_fuel_weight' => 353858]),
            new FuelPlanData(ramp: FuelQuantity::kilograms(100000)),
        );

        $this->assertNull($data->plannedRampWeight->plannedValue);
        $this->assertSame(WeightBalanceSourceStatus::Conflict, $data->plannedRampWeight->sourceStatus);
    }

    public function test_it_preserves_independent_conflict_and_not_present_statuses(): void
    {
        $source = $this->source();
        $source['planned_payload'] = ['amount' => null, 'unit' => 'lb', 'status' => 'conflict'];

        $data = (new WeightBalanceDataBuilder)->build($source, null);

        $this->assertSame(WeightBalanceSourceStatus::Conflict, $data->plannedPayload->sourceStatus);
        $this->assertNull($data->plannedPayload->plannedValue);
        $this->assertSame(WeightBalanceSourceStatus::NotPresent, $data->plannedTakeoffFuel->sourceStatus);
        $this->assertSame(WeightBalanceSourceStatus::NotPresent, $data->plannedRampWeight->sourceStatus);
    }

    public function test_it_propagates_fuel_source_conflicts_to_each_dependent_field(): void
    {
        $data = (new WeightBalanceDataBuilder)->build(
            $this->source(['planned_zero_fuel_weight' => 353858]),
            new FuelPlanData(
                ramp: FuelQuantity::pounds(225500),
                takeoff: FuelQuantity::pounds(223489),
            ),
            ['ramp_status' => 'conflict', 'takeoff_status' => 'conflict'],
        );

        $this->assertSame(WeightBalanceSourceStatus::Conflict, $data->plannedTakeoffFuel->sourceStatus);
        $this->assertSame(WeightBalanceSourceStatus::Conflict, $data->plannedRampWeight->sourceStatus);
        $this->assertNull($data->plannedRampWeight->plannedValue);
    }

    /**
     * @param  array<string, int>  $confirmed
     * @return array<string, array{amount: ?int, unit: string, status: string}>
     */
    private function source(array $confirmed = []): array
    {
        $fields = [
            'basic_operating_weight',
            'planned_payload',
            'planned_zero_fuel_weight',
            'planned_takeoff_gross_weight',
            'planned_estimated_landing_weight',
        ];
        $source = [];

        foreach ($fields as $field) {
            $source[$field] = [
                'amount' => $confirmed[$field] ?? null,
                'unit' => 'lb',
                'status' => array_key_exists($field, $confirmed) ? 'confirmed' : 'not_present',
            ];
        }

        return $source;
    }
}
