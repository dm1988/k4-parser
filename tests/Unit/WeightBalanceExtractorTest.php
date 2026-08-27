<?php

namespace Tests\Unit;

use App\Services\FlightPlan\Extractor\WeightBalanceExtractor;
use PHPUnit\Framework\TestCase;

class WeightBalanceExtractorTest extends TestCase
{
    public function test_it_extracts_confirmed_planned_weights_and_private_evidence(): void
    {
        $text = file_get_contents(__DIR__.'/../Fixtures/FlightPlan/weight-balance/planned-weights.txt');

        $result = (new WeightBalanceExtractor)->extract($text);

        $this->assertSame([
            'amount' => 335858,
            'unit' => 'lb',
            'status' => 'confirmed',
        ], $result['data']['basic_operating_weight']);
        $this->assertSame(18000, $result['data']['planned_payload']['amount']);
        $this->assertSame(353858, $result['data']['planned_zero_fuel_weight']['amount']);
        $this->assertSame(577347, $result['data']['planned_takeoff_gross_weight']['amount']);
        $this->assertSame(371893, $result['data']['planned_estimated_landing_weight']['amount']);
        $this->assertSame(
            'BASIC OPTG WEIGHT 335858',
            $result['source_fragments']['weight_balance_basic_operating_weight'],
        );
        $this->assertArrayNotHasKey('planned_ramp_weight', $result['data']);
    }

    public function test_it_confirms_matching_duplicates_and_rejects_conflicting_duplicates(): void
    {
        $result = (new WeightBalanceExtractor)->extract(<<<'TEXT'
            BASIC OPTG WEIGHT 100,000
            BASIC OPTG WEIGHT 100000
            PAYLOAD 18000
            PAYLOAD 19000
            ZERO FUEL WEIGHT 0
            TEXT);

        $this->assertSame('confirmed', $result['data']['basic_operating_weight']['status']);
        $this->assertSame(100000, $result['data']['basic_operating_weight']['amount']);
        $this->assertSame('conflict', $result['data']['planned_payload']['status']);
        $this->assertNull($result['data']['planned_payload']['amount']);
        $this->assertSame(0, $result['data']['planned_zero_fuel_weight']['amount']);
        $this->assertSame('not_present', $result['data']['planned_takeoff_gross_weight']['status']);
    }

    public function test_it_handles_incomplete_and_malformed_sections_without_inference(): void
    {
        $result = (new WeightBalanceExtractor)->extract('PAYLOAD unavailable ZERO FUEL WEIGHT -1');

        foreach ($result['data'] as $field) {
            $this->assertNull($field['amount']);
            $this->assertSame('not_present', $field['status']);
        }
    }
}
