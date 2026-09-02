<?php

namespace App\Services\FlightPlan\Extractor\Etops;

use App\Enums\EtopsApplicability;
use Illuminate\Support\Str;

class EtopsQualificationExtractor
{
    /**
     * @return array{
     *     data: array{section_present: bool, applicability: string, rating_minutes: ?int},
     *     source_fragments: array<string, string>
     * }
     */
    public function extract(string $text): array
    {
        $matches = [];
        $hasRatedHeading = preg_match(
            '/(?<![A-Z])ETOPS\s+(?<rating>[1-9]\d{1,2})\s+ETOPS\s+ALTERNATE\s+AIRPORTS(?=\s|$|[A-Z]{3}\/[A-Z]{4}\b)/i',
            $text,
            $matches,
        ) === 1;
        $applicability = $this->applicability($text);

        if ($hasRatedHeading && $applicability === EtopsApplicability::Unknown) {
            $applicability = EtopsApplicability::ConfirmedEtops;
        }

        return [
            'data' => [
                'section_present' => $applicability !== EtopsApplicability::Unknown,
                'applicability' => $applicability->value,
                'rating_minutes' => $hasRatedHeading ? (int) $matches['rating'] : null,
            ],
            'source_fragments' => $hasRatedHeading
                ? ['etops_qualification' => Str::squish($matches[0])]
                : [],
        ];
    }

    public function applicability(string $text): EtopsApplicability
    {
        $explicit = [];

        if (preg_match('/\bETOPS(?:\h+FLIGHT)?\h*[:=-]\h*(YES|Y|NO|N)\b/i', $text, $explicit) === 1) {
            return in_array(Str::upper($explicit[1]), ['YES', 'Y'], true)
                ? EtopsApplicability::ConfirmedEtops
                : EtopsApplicability::ConfirmedNonEtops;
        }

        if (preg_match('/\bNO\h+ETOPS\b/i', $text) === 1) {
            return EtopsApplicability::ConfirmedNonEtops;
        }

        if (preg_match('/\bETOPS\h+(?:[1-9]\d{1,2}\h*\/\h*)?[1-9]\d{1,2}\b/i', $text) === 1) {
            return EtopsApplicability::ConfirmedEtops;
        }

        return EtopsApplicability::Unknown;
    }
}
