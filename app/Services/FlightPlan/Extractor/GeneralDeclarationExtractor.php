<?php

namespace App\Services\FlightPlan\Extractor;

use Illuminate\Support\Str;

class GeneralDeclarationExtractor
{
    private const SECTION_WINDOW_LENGTH = 2500;

    /**
     * @return array{
     *     data: array{section_present: bool},
     *     source_fragments: array<string, string>
     * }
     */
    public function extract(string $text): array
    {
        $headingMatches = [];

        if (preg_match_all('/\bGENERAL\s+DECLARATION\b/i', $text, $headingMatches, PREG_OFFSET_CAPTURE) === false) {
            return $this->missingResult();
        }

        foreach ($headingMatches[0] as $headingMatch) {
            $section = substr($text, $headingMatch[1], self::SECTION_WINDOW_LENGTH);

            if (! $this->hasRequiredStructure($section)) {
                continue;
            }

            return [
                'data' => ['section_present' => true],
                'source_fragments' => [
                    'general_declaration_signature' => $this->sourceFragment($section),
                ],
            ];
        }

        return $this->missingResult();
    }

    private function hasRequiredStructure(string $section): bool
    {
        $pattern = '#\(\s*OUTWARD\s*/\s*INWARD\s*\)'
            .'.*?\bOWNER\s+OR\s+OPERATOR\s*:'
            .'.*?\bMARKS\s+OF\s+NATIONALITY\s+AND\s+REGISTRATION\s*:'
            .'.*?\bDEPARTURE\s+FROM\s*:'
            .'.*?\bFLIGHT\s+NO\s*:'
            .'.*?DATE\s*:'
            .'.*?\bARRIVAL\s+AT\s*:#is';

        return preg_match($pattern, $section) === 1;
    }

    private function sourceFragment(string $section): string
    {
        $matches = [];

        if (preg_match('/\A.*?\bARRIVAL\s+AT\s*:/is', $section, $matches) !== 1) {
            return '';
        }

        return Str::limit(Str::squish($matches[0]), 1000, '');
    }

    /** @return array{data: array{section_present: false}, source_fragments: array<string, string>} */
    private function missingResult(): array
    {
        return [
            'data' => ['section_present' => false],
            'source_fragments' => [],
        ];
    }
}
