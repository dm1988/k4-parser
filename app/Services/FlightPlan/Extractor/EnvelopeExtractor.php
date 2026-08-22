<?php

namespace App\Services\FlightPlan\Extractor;

use App\Exceptions\FlightPlanDataConflictException;
use Illuminate\Support\Str;

class EnvelopeExtractor
{
    private const SOURCE_TYPE = 'takeoff_landing_report';

    private const WEIGHT_MULTIPLIER = 100;

    /**
     * @return array{
     *     data: array{section_present: bool, source_type: string, report_reference: ?string, airport: ?string, planned_runway: ?string, outside_air_temperature_celsius: ?float, wind: ?string, qnh_inches_mercury: ?float, maximum_runway_takeoff_weight: array{amount: int, unit: string}|null, flap_setting: ?string, anti_ice: ?bool, v1_knots: ?int, rotate_knots: ?int, v2_knots: ?int, planned_takeoff_weight: array{amount: int, unit: string}|null, maximum_field_takeoff_weight: array{amount: int, unit: string}|null, source_warnings: list<string>},
     *     source_fragments: array<string, string>
     * }
     */
    public function extract(string $text): array
    {
        $sections = $this->sections($text);
        $results = [];
        $sourceFragment = null;

        foreach ($sections as $section) {
            $result = $this->selectedResult($section);

            if ($result === null) {
                continue;
            }

            if ($results !== [] && $results[0] !== $result) {
                throw FlightPlanDataConflictException::forField('takeoff and landing report envelope result');
            }

            $results[] = $result;
            $sourceFragment ??= Str::squish($section);
        }

        $result = $results[0] ?? null;

        return [
            'data' => $result ?? $this->emptyData($sections !== []),
            'source_fragments' => $sourceFragment === null ? [] : [
                'envelope_takeoff_landing_report' => $sourceFragment,
            ],
        ];
    }

    /** @return list<string> */
    private function sections(string $text): array
    {
        $matches = [];

        if (preg_match_all('/\bTAKEOFF\h+AND\h+LANDING\h+REPORT\b/i', $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $sections = [];

        foreach ($matches[0] as $index => $match) {
            $start = $match[1];
            $end = $matches[0][$index + 1][1] ?? strlen($text);
            $sections[] = substr($text, $start, $end - $start);
        }

        return $sections;
    }

    /**
     * @return array{section_present: true, source_type: string, report_reference: ?string, airport: string, planned_runway: string, outside_air_temperature_celsius: float, wind: string, qnh_inches_mercury: float, maximum_runway_takeoff_weight: array{amount: int, unit: string}|null, flap_setting: string, anti_ice: bool, v1_knots: int, rotate_knots: int, v2_knots: int, planned_takeoff_weight: array{amount: int, unit: string}, maximum_field_takeoff_weight: array{amount: int, unit: string}|null, source_warnings: list<string>}|null
     */
    private function selectedResult(string $section): ?array
    {
        $source = Str::squish($section);
        $matches = [];
        $pattern = '/APT\h+PRWY\h+POAT\h+PWIND\h+PQNH\h+PMRTW\h+FLP\h+IC\h+V1\h+VR\h+V2\h+PTOW\h+MFPTW\h*'
            .'(?<airport>[A-Z]{4})\h+(?<runway>\d{2}[LRC]?(?:[\/-][A-Z0-9]+)*)\h+(?<oat>[MP-]?\d{1,2}(?:\.\d)?)\h+'
            .'(?<wind>\d{3}[MP]\d{2,3})\h+(?<qnh>\d{2}\.\d{2})\h+(?<pmrtw>\d{1,5}|-)\h+'
            .'(?<flap>[A-Z0-9.]+)\h+(?<ice>[YN])\h+(?<v1>\d{1,3})\h+(?<vr>\d{1,3})\h+(?<v2>\d{1,3})\h+'
            .'(?<ptow>\d{1,5})\h+(?<mfptw>\d{1,5}|-)/i';

        if (preg_match($pattern, $source, $matches) !== 1) {
            return null;
        }

        return [
            'section_present' => true,
            'source_type' => self::SOURCE_TYPE,
            'report_reference' => $this->reportReference($source),
            'airport' => Str::upper($matches['airport']),
            'planned_runway' => Str::upper($matches['runway']),
            'outside_air_temperature_celsius' => $this->signedDecimal($matches['oat']),
            'wind' => Str::upper($matches['wind']),
            'qnh_inches_mercury' => (float) $matches['qnh'],
            'maximum_runway_takeoff_weight' => $this->weight($matches['pmrtw']),
            'flap_setting' => Str::upper($matches['flap']),
            'anti_ice' => Str::upper($matches['ice']) === 'Y',
            'v1_knots' => (int) $matches['v1'],
            'rotate_knots' => (int) $matches['vr'],
            'v2_knots' => (int) $matches['v2'],
            'planned_takeoff_weight' => $this->weight($matches['ptow']),
            'maximum_field_takeoff_weight' => $this->weight($matches['mfptw']),
            'source_warnings' => $this->warnings($source),
        ];
    }

    private function reportReference(string $source): ?string
    {
        $matches = [];

        if (preg_match('/\b(TLR-\d+\h+SEQ-[A-Z0-9]+\h+\d{2}[A-Z]{3}\d{2}\h+\d{4}Z)\b/i', $source, $matches) !== 1) {
            return null;
        }

        return Str::upper($matches[1]);
    }

    private function signedDecimal(string $value): float
    {
        $normalized = Str::upper($value);

        if (str_starts_with($normalized, 'M')) {
            $normalized = '-'.substr($normalized, 1);
        } elseif (str_starts_with($normalized, 'P')) {
            $normalized = substr($normalized, 1);
        }

        return (float) $normalized;
    }

    /** @return array{amount: int, unit: string}|null */
    private function weight(string $value): ?array
    {
        if ($value === '-') {
            return null;
        }

        return [
            'amount' => (int) $value * self::WEIGHT_MULTIPLIER,
            'unit' => 'lb',
        ];
    }

    /** @return list<string> */
    private function warnings(string $source): array
    {
        $matches = [];

        if (preg_match('/\bRMKS\h+(?<warnings>.*?)(?=-{4,}|RWY\h+OAT\h+WIND|\z)/i', $source, $matches) !== 1) {
            return [];
        }

        $warnings = preg_split('/\h+(?=\d{2}-\d{2}-\d{2}\h*-)/', trim($matches['warnings']));

        if (count($warnings ?: []) === 1 && Str::upper(trim($warnings[0])) === 'NONE') {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $warning): string => Str::squish($warning), $warnings ?: []),
            static fn (string $warning): bool => $warning !== '',
        ));
    }

    /**
     * @return array{section_present: bool, source_type: string, report_reference: null, airport: null, planned_runway: null, outside_air_temperature_celsius: null, wind: null, qnh_inches_mercury: null, maximum_runway_takeoff_weight: null, flap_setting: null, anti_ice: null, v1_knots: null, rotate_knots: null, v2_knots: null, planned_takeoff_weight: null, maximum_field_takeoff_weight: null, source_warnings: list<string>}
     */
    private function emptyData(bool $sectionPresent): array
    {
        return [
            'section_present' => $sectionPresent,
            'source_type' => self::SOURCE_TYPE,
            'report_reference' => null,
            'airport' => null,
            'planned_runway' => null,
            'outside_air_temperature_celsius' => null,
            'wind' => null,
            'qnh_inches_mercury' => null,
            'maximum_runway_takeoff_weight' => null,
            'flap_setting' => null,
            'anti_ice' => null,
            'v1_knots' => null,
            'rotate_knots' => null,
            'v2_knots' => null,
            'planned_takeoff_weight' => null,
            'maximum_field_takeoff_weight' => null,
            'source_warnings' => [],
        ];
    }
}
