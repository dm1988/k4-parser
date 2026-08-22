<?php

namespace App\Services\FlightPlan\Extractor;

use App\Exceptions\FlightPlanDataConflictException;
use Illuminate\Support\Str;

class FlightInitExtractor
{
    /**
     * @return array{
     *     data: array{section_present: bool, acars_init_date: ?string},
     *     source_fragments: array<string, string>
     * }
     */
    public function extract(string $text): array
    {
        $sections = $this->sections($text);
        $dates = [];
        $sourceFragment = null;

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
                'section_present' => $sections !== [],
                'acars_init_date' => $dates[0] ?? null,
            ],
            'source_fragments' => $sourceFragment === null ? [] : [
                'flight_init_takeoff_landing_report' => $sourceFragment,
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

    private function acarsInitDate(string $section): ?string
    {
        $matches = [];

        if (preg_match('/ACARS\s+INIT\s+DATE\s+(?<day>0?[1-9]|[12]\d|3[01])\b/i', $section, $matches) !== 1) {
            return null;
        }

        return str_pad($matches['day'], 2, '0', STR_PAD_LEFT);
    }
}
