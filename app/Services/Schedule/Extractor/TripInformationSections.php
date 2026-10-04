<?php

namespace App\Services\Schedule\Extractor;

class TripInformationSections
{
    private const string MONTH_ABBREVIATION_PATTERN = '(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)';

    public function __construct(private readonly CrewListParser $crewListParser) {}

    /** @return list<string> */
    public function normaliseLines(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $lines = [];

        foreach (explode("\n", $text) as $line) {
            $line = str_replace(['—', '–'], '-', $line);
            $line = preg_replace('/\b([A-Z][a-z]{2}\s+\d{1,2})(\d{2}:\d{2})\b/', '$1 $2', $line) ?? $line;
            $line = trim(preg_replace('/\s+/', ' ', $line));

            if ($line === '') {
                continue;
            }

            if (preg_match('/('.self::MONTH_ABBREVIATION_PATTERN.'\s+\d{1,2}\s+\d{2}:\d{2}\s+-\s+'.self::MONTH_ABBREVIATION_PATTERN.'\s+\d{1,2}\s+\d{2}:\d{2})/', $line, $matches)) {
                $before = trim(substr($line, 0, strpos($line, $matches[1])));
                $after = trim(substr($line, strpos($line, $matches[1]) + strlen($matches[1])));

                if ($before !== '') {
                    $lines[] = $before;
                }

                $lines[] = $matches[1];

                if ($after !== '') {
                    $lines[] = $after;
                }

                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /** @param list<string> $lines */
    public function detectRosterYear(array $lines): int
    {
        foreach ($lines as $line) {
            if (preg_match('/\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2}\s+\d{2}:\d{2}\b/', $line)) {
                continue;
            }

            if (preg_match('/\b(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})\b/', $line, $matches)) {
                return (int) $matches[1];
            }
        }

        return (int) now()->year;
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, int>
     */
    public function detectMonthYears(array $lines, int $defaultYear): array
    {
        $monthYears = [];

        foreach ($lines as $line) {
            if (preg_match('/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})\b/', $line, $matches)) {
                $monthYears[substr($matches[1], 0, 3)] = (int) $matches[2];
                $monthYears[strtolower(substr($matches[1], 0, 3))] = (int) $matches[2];
            }
        }

        return $monthYears ?: ['Jan' => $defaultYear];
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    public function detailSectionLines(array $lines): array
    {
        $detailLines = [];
        $isCollecting = false;
        $foundDetailHeader = false;

        foreach ($lines as $line) {
            if (preg_match('/\b(?:Details|Day\s*Flight\s*Departure)\b/i', $line) === 1) {
                $isCollecting = true;
                $foundDetailHeader = true;

                continue;
            }

            if ($isCollecting && preg_match('/Duty Summary/i', $line) === 1) {
                $isCollecting = false;

                continue;
            }

            if ($isCollecting) {
                $detailLines[] = $line;
            }
        }

        return $foundDetailHeader ? $detailLines : $lines;
    }

    /**
     * @param  list<string>  $lines
     * @return list<list<string>>
     */
    public function detailBlocks(array $lines): array
    {
        $blocks = [];
        $currentBlock = [];
        $mode = null;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if (str_contains($trimmedLine, 'Duty start')) {
                if (! empty($currentBlock)) {
                    $blocks[] = $currentBlock;
                }

                $currentBlock = [$trimmedLine];
                $mode = 'duty';

                continue;
            }

            if ($this->isDateRange($trimmedLine)) {
                $previousLine = $currentBlock === [] ? null : end($currentBlock);

                if (is_string($previousLine) && preg_match('/\b(?:Leg|Duty) LT\b/i', $previousLine) === 1) {
                    $currentBlock[] = $trimmedLine;

                    continue;
                }

                if (! empty($currentBlock)) {
                    $blocks[] = $currentBlock;
                }

                $currentBlock = [$trimmedLine];
                $mode = 'date-range';

                continue;
            }

            if ($mode !== null) {
                $currentBlock[] = $trimmedLine;

                if ($mode === 'duty' && str_contains($trimmedLine, 'Duty end')) {
                    $blocks[] = $currentBlock;
                    $currentBlock = [];
                    $mode = null;
                }
            }
        }

        if (! empty($currentBlock)) {
            $blocks[] = $currentBlock;
        }

        return $blocks;
    }

    /**
     * @param  list<string>  $lines
     * @return array{trip_number: ?string, position: ?string, base: ?string, layovers: list<string>, block_time: ?string, roster_range: ?string}
     */
    public function extractTripSummary(array $lines): array
    {
        $summary = [
            'trip_number' => null,
            'position' => null,
            'base' => null,
            'layovers' => [],
            'block_time' => null,
            'roster_range' => null,
        ];

        $fullText = implode("\n", $lines);

        if (preg_match('/Trip\s*Id:\s*(\d+)/i', $fullText, $matches)) {
            $summary['trip_number'] = $matches[1];
        } elseif (preg_match('/\bTrip\b\D+(\d{4,})\b/s', $fullText, $matches)) {
            $summary['trip_number'] = $matches[1];
        }

        if (preg_match('/Crew:\s*\d*([A-Z]{2})/i', $fullText, $matches)) {
            $summary['position'] = strtoupper($matches[1]);
        } elseif (preg_match('/\b\d{4,}\s*\|?\s*([A-Z]{2})\.?\s+[A-Z]{3}\b/', $fullText, $matches)) {
            $summary['position'] = strtoupper($matches[1]);
        } else {
            $summary['position'] = $this->crewListParser->detectPosition($lines);
        }

        if (preg_match('/Homebase:\s*([A-Z]{3})/i', $fullText, $matches)) {
            $summary['base'] = $matches[1];
        } elseif (preg_match('/\b\d{4,}\s*\|?\s*[A-Z]{2}\.?\s+([A-Z]{3})\b/', $fullText, $matches)) {
            $summary['base'] = $matches[1];
        }

        if (preg_match('/Block\s+Time:\s*(\d{2}:\d{2})/i', $fullText, $matches)) {
            $summary['block_time'] = $matches[1];
        } elseif (preg_match('/\bBlock\s+(\d{1,2}:\d{2}h?)\b/i', $fullText, $matches)) {
            $summary['block_time'] = $matches[1];
        }

        if (preg_match_all('/([A-Z]{3})-([A-Z]{3})/', $fullText, $matches)) {
            $stations = [];
            foreach ($matches[2] as $arrivalStation) {
                if ($summary['base'] && $arrivalStation !== $summary['base']) {
                    $stations[] = $arrivalStation;
                }
            }
            $summary['layovers'] = array_values(array_unique($stations));
        }

        if (preg_match('/Date:\s*(\d{2}[A-Za-z]{3}\d{4})/', $fullText, $matches)) {
            $summary['roster_range'] = $matches[1];
        }

        return $summary;
    }

    public function isDateRange(string $line): bool
    {
        return (bool) preg_match('/^'.self::MONTH_ABBREVIATION_PATTERN.'\s+\d{1,2}\s+\d{2}:\d{2}\s+-\s+'.self::MONTH_ABBREVIATION_PATTERN.'\s+\d{1,2}\s+\d{2}:\d{2}$/', $line);
    }
}
