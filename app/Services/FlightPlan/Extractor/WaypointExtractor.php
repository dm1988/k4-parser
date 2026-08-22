<?php

namespace App\Services\FlightPlan\Extractor;

use Illuminate\Support\Str;

class WaypointExtractor
{
    private const PRIMARY_HEADER = 'IDENT DIST MC FL WIND CMP TAS/MAC TIME ETA ATA TBO FRMG EFB';

    private const SECONDARY_HEADER = 'FRQ DTGO MH W/S OAT G/S T/TME REV REM ABO AFOB DSTN';

    /**
     * @return array{
     *     data: list<array{coordinate: string, identifier: string, time: ?string, total_time: ?string}>,
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

        foreach ($section as $index => $line) {
            $coordinate = $this->coordinate($line);

            if ($coordinate === null) {
                continue;
            }

            $detailIndex = $this->nextNonEmptyLineIndex($section, $index + 1);
            $detail = $detailIndex === null ? null : $this->detail($section[$detailIndex]);

            if ($detail === null) {
                continue;
            }

            $continuationIndex = $this->nextNonEmptyLineIndex($section, $detailIndex + 1);
            $totalTime = $continuationIndex === null ? null : $this->totalTime($section[$continuationIndex]);

            $waypoints[] = [
                'coordinate' => $coordinate,
                'identifier' => $detail['identifier'],
                'time' => $detail['time'],
                'total_time' => $totalTime,
            ];

            $sourceLines[] = $line;
            $sourceLines[] = $section[$detailIndex];

            if ($continuationIndex !== null && $totalTime !== null) {
                $sourceLines[] = $section[$continuationIndex];
            }
        }

        return [
            'data' => $waypoints,
            'source_fragments' => $waypoints === [] ? [] : [
                'computed_flight_plan_waypoints' => Str::squish(implode("\n", $sourceLines)),
            ],
        ];
    }

    /** @return list<string>|null */
    private function computedFlightPlanSection(string $text): ?array
    {
        $lines = preg_split('/\R/', $text);

        if ($lines === false) {
            return null;
        }

        foreach ($lines as $index => $line) {
            if (Str::upper(Str::squish($line)) !== self::PRIMARY_HEADER) {
                continue;
            }

            $secondaryHeaderIndex = $this->nextNonEmptyLineIndex($lines, $index + 1);

            if ($secondaryHeaderIndex === null
                || Str::upper(Str::squish($lines[$secondaryHeaderIndex])) !== self::SECONDARY_HEADER) {
                continue;
            }

            return $this->linesUntilSectionEnd($lines, $secondaryHeaderIndex + 1);
        }

        return null;
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function linesUntilSectionEnd(array $lines, int $start): array
    {
        $section = [];

        for ($index = $start; $index < count($lines); $index++) {
            if ($this->isSectionHeading($lines[$index])) {
                break;
            }

            $section[] = $lines[$index];
        }

        return $section;
    }

    private function isSectionHeading(string $line): bool
    {
        return preg_match('/^\h*(?:ATC|ICAO)\h+FLIGHT\h+PLAN\b|^\h*(?:FUEL\h+SUMMARY|ETOPS|NOTAMS?|WEATHER|MEL\h*\/\h*CDL|RAIM)\b/i', $line) === 1;
    }

    private function coordinate(string $line): ?string
    {
        $matches = [];
        $pattern = '/^\h*(?<latitude>[NS](?:[0-8]\d|90)\h+[0-5]\d(?:\.\d+)?)'
            .'\h*\/?\h*(?<longitude>[EW](?:0\d{2}|1[0-7]\d|180)\h+[0-5]\d(?:\.\d+)?)\h*$/i';

        if (preg_match($pattern, $line, $matches) !== 1) {
            return null;
        }

        return Str::upper(Str::squish($matches['latitude'].' '.$matches['longitude']));
    }

    /** @return array{identifier: string, time: ?string}|null */
    private function detail(string $line): ?array
    {
        $matches = [];

        if (preg_match('/^\h*(?<identifier>-?[A-Z0-9]{2,7})\h+(?:\d{4}|----)\h+(?<details>.+)$/i', $line, $matches) !== 1) {
            return null;
        }

        $timeMatches = [];
        $time = preg_match('/\h(?<time>\d{3}|---)\h+\.{3}\h+\.{3}(?:\h|$)/', $matches['details'], $timeMatches) === 1
            ? $timeMatches['time']
            : null;

        return [
            'identifier' => Str::upper($matches['identifier']),
            'time' => $time === '---' ? null : $time,
        ];
    }

    private function totalTime(string $line): ?string
    {
        $matches = [];

        if (preg_match('/\h(?<total_time>\d{2}\.\d{2})\h+\.{3}\h+\.{3}(?:\h|$)/', $line, $matches) !== 1) {
            return null;
        }

        return $matches['total_time'];
    }

    /** @param list<string> $lines */
    private function nextNonEmptyLineIndex(array $lines, int $start): ?int
    {
        for ($index = $start; $index < count($lines); $index++) {
            if (trim($lines[$index]) !== '') {
                return $index;
            }
        }

        return null;
    }
}
