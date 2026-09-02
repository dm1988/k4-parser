<?php

namespace App\Services\FlightPlan\Extractor;

use App\Enums\WeightBalanceSourceStatus;
use Illuminate\Support\Str;

class WeightBalanceExtractor
{
    /**
     * @return array{
     *     data: array<string, array{amount: ?int, unit: 'lb', status: string}>,
     *     source_fragments: array<string, string>
     * }
     */
    public function extract(string $text): array
    {
        $fields = [
            'basic_operating_weight' => 'BASIC\s+OPTG\s+WEIGHT',
            'planned_payload' => 'PAYLOAD',
            'planned_zero_fuel_weight' => 'ZERO\s+FUEL\s+WEIGHT',
            'planned_takeoff_gross_weight' => 'TAKEOFF\s+GROSS\s+WT',
            'planned_estimated_landing_weight' => 'EST\s+LANDING\s+WEIGHT',
        ];
        $data = [];
        $sourceFragments = [];

        foreach ($fields as $field => $labelPattern) {
            $result = $this->field($text, $labelPattern);
            $data[$field] = $result['data'];

            if ($result['source'] !== null) {
                $sourceFragments['weight_balance_'.$field] = $result['source'];
            }
        }

        return [
            'data' => $data,
            'source_fragments' => $sourceFragments,
        ];
    }

    /** @return array{data: array{amount: ?int, unit: 'lb', status: string}, source: ?string} */
    private function field(string $text, string $labelPattern): array
    {
        $matches = [];
        preg_match_all('/\b'.$labelPattern.'\h+([\d,]+)(?![\d,.])/i', $text, $matches, PREG_SET_ORDER);

        if ($matches === []) {
            return [
                'data' => $this->result(null, WeightBalanceSourceStatus::NotPresent),
                'source' => null,
            ];
        }

        $values = array_values(array_unique(array_map(
            static fn (array $match): int => (int) str_replace(',', '', $match[1]),
            $matches,
        )));
        $status = count($values) === 1
            ? WeightBalanceSourceStatus::Confirmed
            : WeightBalanceSourceStatus::Conflict;

        return [
            'data' => $this->result($status === WeightBalanceSourceStatus::Confirmed ? $values[0] : null, $status),
            'source' => implode(' | ', array_map(
                static fn (array $match): string => Str::squish($match[0]),
                $matches,
            )),
        ];
    }

    /** @return array{amount: ?int, unit: 'lb', status: string} */
    private function result(?int $amount, WeightBalanceSourceStatus $status): array
    {
        return [
            'amount' => $amount,
            'unit' => 'lb',
            'status' => $status->value,
        ];
    }
}
