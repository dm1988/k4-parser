<?php

namespace App\Services;

use App\Enums\CrewPosition;

class CrewListParser
{
    /**
     * @param  array<int, string>|string  $input
     * @return list<array{name: string, employee_id: string, crew_id: string, base: ?string, role: ?string, deadheading: bool}>
     */
    public function parse(array|string $input): array
    {
        $lines = is_array($input) ? $input : (preg_split('/\r\n|\r|\n/', $input) ?: []);
        $crew = [];

        foreach ($lines as $line) {
            $member = $this->parseLine((string) $line);

            if ($member !== null) {
                $crew[] = $member;
            }
        }

        return $crew;
    }

    /**
     * @param  array<int, string>|string  $input
     * @return array{
     *     crew: list<array{name: string, employee_id: string, crew_id: string, base: ?string, role: ?string, deadheading: bool}>,
     *     crew_count: ?int,
     *     operating_crew_count: ?int,
     *     deadheading_crew_count: ?int
     * }
     */
    public function parseWithSummary(array|string $input): array
    {
        $crew = $this->parse($input);

        return [
            'crew' => $crew,
            ...$this->summarize($crew),
        ];
    }

    /**
     * @param  array<int, string>|string  $input
     */
    public function detectPosition(array|string $input): ?string
    {
        $lines = is_array($input) ? $input : (preg_split('/\r\n|\r|\n/', $input) ?: []);

        foreach ($this->parse($lines) as $member) {
            if (($member['role'] ?? null) !== null) {
                return $member['role'];
            }
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);

            if (($position = CrewPosition::tryFrom(strtoupper($line))) !== null) {
                return $position->value;
            }

            if (preg_match('/\b('.CrewPosition::regexPattern().')\b/i', $line, $matches) === 1) {
                return strtoupper($matches[1]);
            }
        }

        return null;
    }

    /**
     * @param  list<array{name: string, employee_id: string, crew_id: string, base: ?string, role: ?string, deadheading: bool}>  $crew
     * @return array{crew_count: ?int, operating_crew_count: ?int, deadheading_crew_count: ?int}
     */
    public function summarize(array $crew): array
    {
        $count = count($crew);

        if ($count === 0) {
            return [
                'crew_count' => null,
                'operating_crew_count' => null,
                'deadheading_crew_count' => null,
            ];
        }

        $deadheadingCount = count(array_filter($crew, fn (array $member): bool => $member['deadheading']));
        $operatingCount = $count - $deadheadingCount;

        return [
            'crew_count' => $count,
            'operating_crew_count' => $operatingCount,
            'deadheading_crew_count' => $deadheadingCount,
        ];
    }

    /**
     * @return array{name: string, employee_id: string, crew_id: string, base: ?string, role: ?string, deadheading: bool}|null
     */
    public function parseLine(string $line): ?array
    {
        $line = trim((string) preg_replace('/\s+/', ' ', $line));

        if ($line === '' || preg_match('/\bName\s+Crew\s+Pos\s+Base\b/i', $line) === 1) {
            return null;
        }

        if (preg_match('/\b(\d{4,6})\b/', $line, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $employeeId = $matches[1][0];
        $employeeIdOffset = $matches[1][1];
        $nameSegment = trim(substr($line, 0, $employeeIdOffset));
        $detailSegment = trim(substr($line, $employeeIdOffset + strlen($employeeId)));

        $name = $this->extractName($nameSegment);

        if ($name === null) {
            return null;
        }

        $deadheading = preg_match('/\bDH\b/i', $detailSegment) === 1;
        $base = $this->extractBase($detailSegment);
        $role = $this->extractRole($detailSegment, $deadheading);

        return [
            'name' => $name,
            'employee_id' => $employeeId,
            'crew_id' => $employeeId,
            'base' => $base,
            'role' => $role,
            'deadheading' => $deadheading,
        ];
    }

    private function extractName(string $value): ?string
    {
        $value = trim((string) preg_replace('/^[^A-Za-z]+/', '', $value));
        $words = preg_split('/\s+/', $value) ?: [];

        while (count($words) > 2 && preg_match('/^[A-Z][a-z]+$/', $words[0]) !== 1) {
            array_shift($words);
        }

        $value = implode(' ', $words);

        if (preg_match('/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)$/', $value, $matches) === 1) {
            return $matches[1];
        }

        $value = trim((string) preg_replace('/^[A-Za-z]{1,3}\s+/', '', $value));

        if (preg_match('/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)$/', $value, $matches) === 1) {
            return $matches[1];
        }

        return $value !== '' ? $value : null;
    }

    private function extractBase(string $value): ?string
    {
        if (preg_match_all('/\b([A-Z]{3})\b/', $value, $matches) !== 1 || empty($matches[1])) {
            return null;
        }

        return end($matches[1]);
    }

    private function extractRole(string $value, bool $deadheading): ?string
    {
        if ($deadheading) {
            return CrewPosition::Deadhead->value;
        }

        if (preg_match('/\b('.CrewPosition::regexPattern().')\b/i', $value, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return null;
    }
}
