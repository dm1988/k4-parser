<?php

namespace App\Services\FlightPlan\Extractor;

use App\Exceptions\FlightPlanDataConflictException;
use Illuminate\Support\Str;

class FlightInitExtractor
{
    /**
     * @return array{
     *     data: array{section_present: bool, acars_init_date: ?string, fms_initial_altitude: ?string},
     *     source_fragments: array<string, string>
     * }
     */
    public function extract(string $text): array
    {
        $sections = $this->sections($text);
        $dates = [];
        $sourceFragment = null;
        $fmsInitialAltitude = $this->fmsInitialAltitude($text);

        foreach ($sections as $section) {
            $date = $this->acarsInitDate($section);

            if ($date === null) {
                continue;
            }

            if ($dates !== [] && $dates[0] !== $date) {
                throw FlightPlanDataConflictException::forField('ACARS init date');
            }

            $dates[] = $date;
            $sourceFragment ??= Str::squish($section);
        }

        return [
            'data' => [
                'section_present' => $sections !== [] || $fmsInitialAltitude['value'] !== null,
                'acars_init_date' => $dates[0] ?? null,
                'fms_initial_altitude' => $fmsInitialAltitude['value'],
            ],
            'source_fragments' => array_filter([
                'flight_init_takeoff_landing_report' => $sourceFragment,
                'flight_init_fms_initial_altitude' => $fmsInitialAltitude['source'],
            ]),
        ];
    }

    /** @return array{value: ?string, source: ?string} */
    private function fmsInitialAltitude(string $text): array
    {
        $matches = [];
        preg_match_all(
            '/DEST\s+[A-Z]{4}\s+[\d.]+\s+[\d.]+\s+(?<level>\d{2,3})\s+\d{1,5}\s+[PM]\d{3}\b/i',
            $text,
            $matches,
            PREG_SET_ORDER,
        );

        $levels = array_map(static fn (array $match): int => (int) $match['level'], $matches);

        if (count(array_unique($levels)) > 1) {
            throw FlightPlanDataConflictException::forField('FMS initial altitude');
        }

        $level = $levels[0] ?? null;

        return [
            'value' => $level === null ? null : 'F'.str_pad((string) $level, 3, '0', STR_PAD_LEFT),
            'source' => isset($matches[0][0]) ? Str::squish($matches[0][0]) : null,
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

    private function acarsInitDate(string $section): ?string
    {
        $matches = [];

        if (preg_match('/ACARS\s+INIT\s+DATE\s+(?<day>0?[1-9]|[12]\d|3[01])\b/i', $section, $matches) !== 1) {
            return null;
        }

        return str_pad($matches['day'], 2, '0', STR_PAD_LEFT);
    }
}
