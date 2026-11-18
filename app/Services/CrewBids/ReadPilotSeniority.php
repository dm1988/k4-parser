<?php

namespace App\Services\CrewBids;

use Carbon\CarbonImmutable;
use RuntimeException;
use SensitiveParameter;
use Smalot\PdfParser\Parser;
use Throwable;

/** @phpstan-import-type Pilot from \App\Models\PilotSeniorityList */
class ReadPilotSeniority
{
    /** @return array{effective_date: string, pilots: array<string, Pilot>} */
    public function read(#[SensitiveParameter] string $pdf): array
    {
        try {
            $pages = (new Parser)->parseContent($pdf)->getPages();
            if (count($pages) < 1 || count($pages) > 100) {
                throw new RuntimeException;
            }

            return $this->readPages(array_map(static fn ($page): string => $page->getText(), $pages));
        } catch (Throwable) {
            throw new RuntimeException('The seniority PDF is incomplete or uses an unsupported layout. The previous list is unchanged.');
        }
    }

    /**
     * Only employee number, name and seniority cross this boundary.
     *
     * @param  list<string>  $pages
     * @return array{effective_date: string, pilots: array<string, Pilot>}
     */
    public function readPages(#[SensitiveParameter] array $pages): array
    {
        $pilots = [];
        $date = null;
        foreach ($pages as $index => $text) {
            if ($index === 0 && str_contains($text, 'SYSTEM SENIORITY LIST') && ! str_contains($text, "PILOT'S SENIORITY LIST")) {
                continue;
            }
            if (! preg_match("/\\APILOT'S SENIORITY LIST\\s+([A-Za-z]+ \\d{1,2}, \\d{4})\\R/u", trim($text), $header)
                || ! preg_match('/Emp\. No\.\s*Sen No\.\s*Name\s+Type\s+Class\s*Base\s+121 DOH\s+Long Date\s+DOB\s+Mgt\. Lv\s+Remarks/u', $text)) {
                throw new RuntimeException('The seniority PDF header or columns are unsupported.');
            }
            $effective = CarbonImmutable::createFromFormat('!F j, Y', $header[1], 'UTC');
            if ($effective === null || $effective->format('F j, Y') !== $header[1] || $effective->isAfter(now('UTC'))
                || ($date !== null && $date !== $effective->format('Y-m-d'))) {
                throw new RuntimeException('The seniority PDF dates are invalid or inconsistent.');
            }
            $date = $effective->format('Y-m-d');
            $rows = preg_split('/\R/u', trim($text));
            $count = 0;
            foreach ($rows as $row) {
                $row = trim($row);
                if ($row === '' || $row === "PILOT'S SENIORITY LIST" || $row === $header[1] || str_starts_with($row, 'Emp. No.')) {
                    continue;
                }
                if (! preg_match('/\A([1-9]\d{0,19})\s+([1-9]\d{0,4})\s*([^\d].*?)\s+\d{3}\s+(?:CA|FO|FE)\s+[A-Z]{3}\s+\d{1,2}\/\d{1,2}\/\d{4}\s+\d{1,2}\/\d{1,2}\/\d{4}\s+\d{1,2}\/\d{1,2}\/\d{4}(?:\s+.*)?\z/u', $row, $match)
                    || isset($pilots[$match[1]]) || (int) $match[2] !== count($pilots) + 1) {
                    throw new RuntimeException('The seniority PDF has an unsupported, duplicate or missing employee row.');
                }
                $name = preg_replace('/\s+/u', ' ', trim($match[3]));
                if ($name === null || mb_strlen($name) > 200 || ! str_contains($name, ',')) {
                    throw new RuntimeException('The seniority PDF has an unsupported employee name.');
                }
                $pilots[$match[1]] = ['name' => $name, 'seniority' => (int) $match[2]];
                $count++;
            }
            if ($count === 0 || ! str_ends_with(trim($text), $header[1])) {
                throw new RuntimeException('The seniority PDF has an incomplete roster page.');
            }
        }
        if ($date === null || $pilots === []) {
            throw new RuntimeException('No supported seniority roster was found.');
        }

        return ['effective_date' => $date, 'pilots' => $pilots];
    }
}
