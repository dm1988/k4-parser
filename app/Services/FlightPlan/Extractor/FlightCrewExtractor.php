<?php

namespace App\Services\FlightPlan\Extractor;

use App\Services\Schedule\Extractor\CrewListParser;
use Illuminate\Support\Str;

class FlightCrewExtractor
{
    public function __construct(
        private readonly CrewListParser $crewListParser,
    ) {}

    /**
     * @return array{
     *     data: list<array{name: string, role: ?string, base: ?string, employee_number: ?string, high_mins: bool}>,
     *     source_fragments: array<string, string>
     * }
     */
    public function extract(string $text): array
    {
        $sections = [
            $this->releaseManifestSection($text),
            $this->crewSection($text),
        ];

        foreach ($sections as $section) {
            if ($section === null) {
                continue;
            }

            $members = [];

            foreach ($this->crewListParser->parse($section['body']) as $member) {
                if ($member['role'] === null) {
                    continue;
                }

                $normalized = [
                    'name' => $member['name'],
                    'role' => $member['role'],
                    'base' => $member['base'],
                    'employee_number' => $member['employee_id'] !== '' ? $member['employee_id'] : null,
                    'high_mins' => $member['high_mins'],
                ];
                $key = implode('|', [
                    $normalized['name'],
                    $normalized['role'],
                    $normalized['base'] ?? '',
                    $normalized['employee_number'],
                ]);

                if (isset($members[$key])) {
                    $members[$key]['high_mins'] = $members[$key]['high_mins'] || $normalized['high_mins'];

                    continue;
                }

                $members[$key] = $normalized;
            }

            if ($members !== []) {
                return [
                    'data' => array_values($members),
                    'source_fragments' => ['flight_crew' => $section['source']],
                ];
            }
        }

        return [
            'data' => [],
            'source_fragments' => [],
        ];
    }

    /** @return array{body: string, source: string}|null */
    private function crewSection(string $text): ?array
    {
        $pattern = '/(?:^|\R)\h*(?<heading>CREW(?:\h+LIST)?\h*:?)\h*(?<body>.*?)'
            .'(?=(?:\R\h*(?:MAINTENANCE(?:\h+LOG|\h+ITEMS?)?|MEL\h*\/\h*CDL\h*\/\h*DMI|FUEL\h+SUMMARY|ROUTE|NOTAMS?|WEATHER)\b)|\z)/is';
        $matches = [];

        if (preg_match($pattern, $text, $matches) !== 1) {
            return null;
        }

        $sourceLines = [];

        foreach (preg_split('/\R/', $matches['body']) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || preg_match('/^Name\h+Crew\h+Pos\h+Base$/i', $line) === 1) {
                continue;
            }

            $members = $this->crewListParser->parse($line);

            if ($members === [] || array_filter($members, static fn (array $member): bool => $member['role'] === null) !== []) {
                break;
            }

            $sourceLines[] = $line;
        }

        if ($sourceLines === []) {
            return null;
        }

        $body = implode("\n", $sourceLines);

        return [
            'body' => $body,
            'source' => Str::squish($matches['heading'].' '.$body),
        ];
    }

    /** @return array{body: string, source: string}|null */
    private function releaseManifestSection(string $text): ?array
    {
        $headerPattern = '121-91\h+FLIGHT\h+RELEASE\h+I\.F\.R';
        $pattern = '/\b'.$headerPattern.'\b\h*:?\h*(?<body>.*?)'
            .'(?=\b(?:CIRCLE\h+THE\h+APPROPRIATE\h+STATUS|RELEASE\h+TIME|FUEL\h+SUMMARY|'.$headerPattern.')\b|\z)/is';
        $sections = [];

        if (preg_match_all($pattern, $text, $sections, PREG_SET_ORDER) < 1) {
            return null;
        }

        foreach ($sections as $section) {
            $lines = preg_split('/\R/', $section['body']) ?: [];
            $sourceLines = [];
            $memberCount = 0;

            foreach ($lines as $line) {
                $line = trim($line);
                $members = $this->crewListParser->parseReleaseManifestLine($line);

                if ($members !== []) {
                    $sourceLines[] = $line;
                    $memberCount += count($members);

                    continue;
                }

                if ($line === '' || preg_match('/^(?:ADDNTL|CAPT|IRP|MX|LM|ACM)(?:\h+(?:CAPT|IRP|MX|LM|ACM))*$/i', $line) === 1) {
                    $sourceLines[] = $line;

                    continue;
                }

                break;
            }

            if ($memberCount === 0) {
                continue;
            }

            $body = trim(implode("\n", $sourceLines));

            return [
                'body' => $body,
                'source' => Str::squish($body),
            ];
        }

        return null;
    }
}
