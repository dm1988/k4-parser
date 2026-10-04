<?php

namespace App\Services\FlightPlan\Extractor;

class TakeoffLandingReportSections
{
    /** @return list<string> */
    public function extract(string $text): array
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
}
