<?php

namespace App\Services\FlightPlan;

class FlightPlanTextNormalizer
{
    /** Page breaks retain section boundaries without exposing printed page labels. */
    public static function normalize(string $text): string
    {
        $text = str_replace("\x00", '', $text);

        return preg_replace('/(?:KALITTA\s+BRIEF\s+)?PAGE\s+\d+\s+OF\s+\d+/i', "\f", $text) ?? $text;
    }
}
