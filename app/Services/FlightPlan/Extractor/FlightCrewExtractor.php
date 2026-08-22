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
     *     data: list<array{name: string, role: ?string, base: ?string}>,
     *     source_fragments: array<string, string>
     * }
     */
    public function extract(string $text): array
    {
        $section = $this->crewSection($text) ?? $this->releaseManifestSection($text);

        if ($section === null) {
            return [
                'data' => [],
                'source_fragments' => [],
            ];
        }

        $members = [];

        foreach ($this->crewListParser->parse($section['body']) as $member) {
            $key = $member['name'].'|'.($member['role'] ?? '').'|'.($member['base'] ?? '');
            $members[$key] = [
                'name' => $member['name'],
                'role' => $member['role'],
                'base' => $member['base'],
            ];
        }

        return [
            'data' => array_values($members),
            'source_fragments' => ['flight_crew' => $section['source']],
        ];
    }

    /** @return array{body: string, source: string}|null */
    private function crewSection(string $text): ?array
    {
        $pattern = '/(?:^|\R)\h*CREW(?:\h+LIST)?\h*:?\h*(?<body>.*?)'
            .'(?=(?:\R\h*(?:MAINTENANCE(?:\h+LOG|\h+ITEMS?)?|MEL\h*\/\h*CDL\h*\/\h*DMI|FUEL\h+SUMMARY|ROUTE|NOTAMS?|WEATHER)\b)|\z)/is';
        $matches = [];

        if (preg_match($pattern, $text, $matches) !== 1) {
            return null;
        }

        return [
            'body' => trim($matches['body']),
            'source' => Str::squish($matches[0]),
        ];
    }

    /** @return array{body: string, source: string}|null */
    private function releaseManifestSection(string $text): ?array
    {
        $lines = preg_split('/\R/', $text) ?: [];
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

            if ($memberCount === 0) {
                continue;
            }

            if ($line === '') {
                continue;
            }

            if (preg_match('/^(?:ADDNTL|CAPT|ACM(?:\h+ACM)*)$/i', $line) === 1) {
                $sourceLines[] = $line;

                continue;
            }

            break;
        }

        if ($memberCount < 2) {
            return null;
        }

        $body = implode("\n", $sourceLines);

        return [
            'body' => $body,
            'source' => Str::squish($body),
        ];
    }
}
