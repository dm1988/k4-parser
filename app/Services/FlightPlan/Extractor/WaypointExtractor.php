<?php

namespace App\Services\FlightPlan\Extractor;

use Illuminate\Support\Str;

class WaypointExtractor
{
    private const PRIMARY_HEADER = 'IDENT DIST MC FL WIND CMP TAS/MAC TIME ETA ATA TBO FRMG EFB';

    private const SECONDARY_HEADER = 'FRQ DTGO MH W/S OAT G/S T/TME REV REM ABO AFOB DSTN';

    private const HEADER_PATTERN = '/IDENT\h+DIST\h+MC\h+FL\h+WIND\h+CMP\h+TAS\/MAC\h+TIME\h+ETA\h+ATA\h+TBO\h+FRMG\h+EFB'
        .'\s*FRQ\h+DTGO\h+MH\h+W\/S\h+OAT\h+G\/S\h+T\/TME\h+REV\h+REM\h+ABO\h+AFOB\h+DSTN/i';

    private const COORDINATE_PATTERN = '/(?<latitude>[NS](?:[0-8]\d|90)\h+[0-5]\d(?:\.\d+)?)'
        .'\h*\/?\h*(?<longitude>[EW](?:0\d{2}|1[0-7]\d|180)\h+[0-5]\d(?:\.\d+)?)/i';

    /**
     * @return array{
     *     data: list<array{coordinate: string, identifier: string, time: ?string, total_time: ?string, remaining_fuel: ?string}>,
     *     source_fragments: array<string, string>
     * }
     */
    public function extract(string $text): array
    {
        $section = $this->computedFlightPlanSection($text);

        if ($section === null) {
            return ['data' => [], 'source_fragments' => []];
        }

        $waypoints = [];
        $sourceLines = [self::PRIMARY_HEADER, self::SECONDARY_HEADER];

        $records = $this->coordinateDelimitedRecords($section);

        foreach ($records as $record) {
            $detail = $this->detail($record['content']);

            if ($detail === null) {
                continue;
            }

            $totalTime = $this->totalTime($record['content']);

            $waypoints[] = [
                'coordinate' => $record['coordinate'],
                'identifier' => $detail['identifier'],
                'time' => $detail['time'],
                'total_time' => $totalTime,
                'remaining_fuel' => $detail['remaining_fuel'],
            ];

            $sourceLines[] = implode(' ', array_filter([
                $record['coordinate'],
                $detail['identifier'],
                $detail['time'],
                $totalTime,
                $detail['remaining_fuel'],
            ], static fn (?string $value): bool => $value !== null));
        }

        return [
            'data' => $waypoints,
            'source_fragments' => $waypoints === [] ? [] : [
                'computed_flight_plan_waypoints' => Str::squish(implode("\n", $sourceLines)),
            ],
        ];
    }

    private function computedFlightPlanSection(string $text): ?string
    {
        $matches = [];

        if (preg_match(self::HEADER_PATTERN, $text, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $header = $matches[0][0];
        $sectionStart = $matches[0][1] + strlen($header);
        $remainingText = substr($text, $sectionStart);

        return $this->textUntilSectionEnd($remainingText);
    }

    private function textUntilSectionEnd(string $text): string
    {
        $matches = [];

        if (preg_match('/-{3,}\h*ALTERNATE\b|\b(?:ATC|ICAO)\h+FLIGHT\h+PLAN\b|\b(?:FUEL\h+SUMMARY|ETOPS|NOTAMS?|WEATHER|MEL\h*\/\h*CDL|RAIM)\b/i', $text, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return $text;
        }

        return substr($text, 0, $matches[0][1]);
    }

    /**
     * @return list<array{coordinate: string, content: string}>
     */
    private function coordinateDelimitedRecords(string $section): array
    {
        $matches = [];

        if (preg_match_all(self::COORDINATE_PATTERN, $section, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $records = [];
        $coordinates = $matches[0];

        foreach ($coordinates as $index => [$rawCoordinate, $offset]) {
            $contentStart = $offset + strlen($rawCoordinate);
            $contentEnd = $coordinates[$index + 1][1] ?? strlen($section);
            $records[] = [
                'coordinate' => Str::upper(Str::squish($matches['latitude'][$index][0].' '.$matches['longitude'][$index][0])),
                'content' => Str::squish(substr($section, $contentStart, $contentEnd - $contentStart)),
            ];
        }

        return $records;
    }

    /** @return array{identifier: string, time: ?string, remaining_fuel: ?string}|null */
    private function detail(string $line): ?array
    {
        $matches = [];

        if (preg_match('/^\h*(?<identifier>-?[A-Z0-9]{2,7})\h+(?:\d{4}|----)\h+(?<details>.+)$/i', $line, $matches) !== 1) {
            return null;
        }

        $timeMatches = [];
        $time = preg_match('/\h(?<time>\d{3}|---)\h+\.{2,3}\h+\.{2,3}(?:\h|$)/', $matches['details'], $timeMatches) === 1
            ? $timeMatches['time']
            : null;
        $fuelMatches = [];
        $remainingFuel = preg_match('/\h(?:\d{4}|----)\h+(?<fuel>\d{4}|----)\h+(?:\d{4}|\.{2,4})(?:\h|$)/', $matches['details'], $fuelMatches) === 1
            ? $fuelMatches['fuel']
            : null;

        return [
            'identifier' => Str::upper($matches['identifier']),
            'time' => $time === '---' ? null : $time,
            'remaining_fuel' => $remainingFuel === '----' ? null : $remainingFuel,
        ];
    }

    private function totalTime(string $line): ?string
    {
        $matches = [];

        if (preg_match('/\h(?<total_time>\d{2}\.\d{2})\h+\.{2,3}\h+\.{2,3}(?:\h|$)/', $line, $matches) !== 1) {
            return null;
        }

        return $matches['total_time'];
    }
}
