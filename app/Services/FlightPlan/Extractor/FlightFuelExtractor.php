<?php

namespace App\Services\FlightPlan\Extractor;

use Illuminate\Support\Str;

class FlightFuelExtractor
{
    /**
     * @return array{
     *     data: array{cost_index: ?int, ramp: array{amount: float, unit: string}|null, ramp_status: string, taxi: array{amount: float, unit: string}|null, takeoff: array{amount: float, unit: string}|null, takeoff_status: string, trip: array{amount: float, unit: string}|null, contingency: null, alternate: array{amount: float, unit: string}|null, final_reserve: array{amount: float, unit: string}|null, estimated_landing: array{amount: float, unit: string}|null},
     *     source_fragments: array<string, string>
     * }
     */
    public function extract(string $text): array
    {
        $unit = $this->detectUnit($text);
        $summary = $this->summaryFragment($text);

        return [
            'data' => [
                'cost_index' => $this->costIndex($text),
                'ramp' => $this->scaledQuantity($text, '/TTL\s+RMP\s+([\d,.]+)/i', $unit),
                'ramp_status' => $this->quantityStatus($text, '/TTL\s+RMP\s+([\d,.]+)/i', $unit, 1000),
                'taxi' => $this->scaledQuantity($text, '/TAXI\s+([\d,.]+)/i', $unit),
                'takeoff' => $this->exactQuantity($text, '/TAKEOFF\s+FUEL\s+([\d,]+)/i', $unit),
                'takeoff_status' => $this->quantityStatus($text, '/TAKEOFF\s+FUEL\s+([\d,]+)/i', $unit, 1),
                'trip' => $this->exactQuantity($text, '/EST\s+FUEL\s+BURN\s+([\d,]+)/i', $unit),
                'contingency' => null,
                'alternate' => $this->scaledQuantity($text, '/ALTN\s+[A-Z]{4}\s+([\d,.]+)/i', $unit),
                'final_reserve' => $this->scaledQuantity($text, '/RESERVE\s+([\d,.]+)/i', $unit),
                'estimated_landing' => $this->exactQuantity($text, '/EST\s+LANDING\s+FUEL:\s*([\d,]+)/i', $unit),
            ],
            'source_fragments' => array_filter([
                'fuel_cost_index' => $this->costIndexFragment($text),
                'fuel_summary' => $summary,
                'fuel_unit' => $unit,
            ], static fn (?string $value): bool => $value !== null),
        ];
    }

    private function costIndex(string $text): ?int
    {
        $matches = [];

        if (preg_match('/\bFUEL\s+BURN\s+BASED\s+ON:\s*CI\s*(\d{1,3})(?!\d)/i', $text, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function costIndexFragment(string $text): ?string
    {
        $matches = [];

        if (preg_match('/\bFUEL\s+BURN\s+BASED\s+ON:\s*CI\s*\d{1,3}(?!\d)/i', $text, $matches) !== 1) {
            return null;
        }

        return Str::squish($matches[0]);
    }

    private function detectUnit(string $text): ?string
    {
        if (preg_match('/\/\s*1000\s+LBS?\b/i', $text) === 1) {
            return 'lb';
        }

        if (preg_match('/\/\s*1000\s+KG(?:S)?\b/i', $text) === 1) {
            return 'kg';
        }

        return null;
    }

    /** @return array{amount: float, unit: string}|null */
    private function scaledQuantity(string $text, string $pattern, ?string $unit): ?array
    {
        return $this->quantity($text, $pattern, $unit, 1000);
    }

    /** @return array{amount: float, unit: string}|null */
    private function exactQuantity(string $text, string $pattern, ?string $unit): ?array
    {
        return $this->quantity($text, $pattern, $unit, 1);
    }

    /** @return array{amount: float, unit: string}|null */
    private function quantity(string $text, string $pattern, ?string $unit, int $scale): ?array
    {
        if ($unit === null) {
            return null;
        }

        $amounts = $this->quantityAmounts($text, $pattern, $unit, $scale);

        if (count($amounts) !== 1) {
            return null;
        }

        return [
            'amount' => $amounts[0],
            'unit' => $unit,
        ];
    }

    private function quantityStatus(string $text, string $pattern, ?string $unit, int $scale): string
    {
        $amounts = $this->quantityAmounts($text, $pattern, $unit, $scale);

        return match (count($amounts)) {
            0 => 'not_present',
            1 => 'confirmed',
            default => 'conflict',
        };
    }

    /** @return list<float> */
    private function quantityAmounts(string $text, string $pattern, ?string $unit, int $scale): array
    {
        if ($unit === null) {
            return [];
        }

        $matches = [];
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);

        return array_values(array_unique(array_map(
            static fn (array $match): float => (float) str_replace(',', '', $match[1]) * $scale,
            $matches,
        ), SORT_REGULAR));
    }

    private function summaryFragment(string $text): ?string
    {
        $matches = [];

        if (preg_match('/\bDEST\s+[A-Z]{4}\s+.+?\bEST\s+LANDING\s+FUEL:\s*[\d,]+/is', $text, $matches) !== 1) {
            return null;
        }

        return Str::squish($matches[0]);
    }
}
