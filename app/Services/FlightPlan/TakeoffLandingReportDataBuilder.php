<?php

namespace App\Services\FlightPlan;

use App\DTOs\TakeoffLandingReportData;
use App\ValueObjects\WeightQuantity;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TakeoffLandingReportDataBuilder
{
    /** @param array<string, mixed> $source */
    public function fromExtracted(array $source): ?TakeoffLandingReportData
    {
        return $this->build($source, true);
    }

    public function fromSerialized(mixed $source): ?TakeoffLandingReportData
    {
        return is_array($source) ? $this->build($source, false) : null;
    }

    /** @param array<string, mixed> $source */
    private function build(array $source, bool $extracted): ?TakeoffLandingReportData
    {
        $key = static fn (string $name): string => $extracted ? Str::snake($name) : $name;

        if (($source[$key('sectionPresent')] ?? false) !== true) {
            return null;
        }

        return new TakeoffLandingReportData(
            sectionPresent: true,
            sourceType: $this->nullableString($source[$key('sourceType')] ?? null) ?? 'takeoff_landing_report',
            reportReference: $this->nullableString($source[$key('reportReference')] ?? null),
            airport: $this->nullableString($source[$key('airport')] ?? null),
            plannedRunway: $this->nullableString($source[$key('plannedRunway')] ?? null),
            outsideAirTemperatureCelsius: $this->nullableFloat($source[$key('outsideAirTemperatureCelsius')] ?? null),
            wind: $this->nullableString($source[$key('wind')] ?? null),
            qnhInchesMercury: $this->nullableFloat($source[$key('qnhInchesMercury')] ?? null),
            qnhHectopascals: $this->nullableInteger($source[$key('qnhHectopascals')] ?? null),
            maximumRunwayTakeoffWeight: $this->weightQuantity($source[$key('maximumRunwayTakeoffWeight')] ?? null),
            flapSetting: $this->nullableString($source[$key('flapSetting')] ?? null),
            antiIce: is_bool($source[$key('antiIce')] ?? null) ? $source[$key('antiIce')] : null,
            v1Knots: $this->nullableInteger($source[$key('v1Knots')] ?? null),
            rotateKnots: $this->nullableInteger($source[$key('rotateKnots')] ?? null),
            v2Knots: $this->nullableInteger($source[$key('v2Knots')] ?? null),
            plannedTakeoffWeight: $this->weightQuantity($source[$key('plannedTakeoffWeight')] ?? null),
            maximumFieldTakeoffWeight: $this->weightQuantity($source[$key('maximumFieldTakeoffWeight')] ?? null),
            sourceWarnings: $this->strings($source[$key('sourceWarnings')] ?? null),
        );
    }

    private function weightQuantity(mixed $value): ?WeightQuantity
    {
        if (! is_array($value) || ! is_int($value['amount'] ?? null) || ! is_string($value['unit'] ?? null)) {
            return null;
        }

        try {
            return new WeightQuantity($value['amount'], $value['unit']);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_float($value) || is_int($value) ? (float) $value : null;
    }

    private function nullableInteger(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }

    /** @return list<string> */
    private function strings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $value): ?string => $this->nullableString($value),
            $values,
        )));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value !== '' ? $value : null;
    }
}
