<?php

namespace App\Services\FlightPlan\Extractor;

use App\DTOs\ReleaseStatusData;
use App\Enums\ReleaseStatus;

class ReleaseStatusExtractor
{
    public function extract(string $text): ReleaseStatusData
    {
        $pattern = '/(?:\A|[\r\n]|\*+)\h*'
            .'(?<warning>PRELIMINARY\s+FLIGHT\s+PLAN\s*[-\x{2010}-\x{2015}\x{2212}]\s*'
            .'PAYLOAD\s+ESTIMATED\s*[-\x{2010}-\x{2015}\x{2212}]\s*'
            .'DO\s+NOT\s+FUEL\s+AIRCRAFT)\h*(?=\*|[\r\n]|\z)/iu';

        if (preg_match($pattern, $text, $matches) !== 1) {
            return new ReleaseStatusData(ReleaseStatus::NotMarkedPreliminary);
        }

        return new ReleaseStatusData(ReleaseStatus::Preliminary, $matches['warning']);
    }
}
