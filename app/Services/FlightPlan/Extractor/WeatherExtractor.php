<?php

namespace App\Services\FlightPlan\Extractor;

use Illuminate\Support\Str;

class WeatherExtractor
{
    /**
     * @return array{
     *     data: array{
     *         departure: array{airport: string, metars: list<string>, tafs: list<string>}|null,
     *         destination: array{airport: string, metars: list<string>, tafs: list<string>}|null,
     *         alternate: array{airport: string, metars: list<string>, tafs: list<string>}|null,
     *         raim: ?string
     *     },
     *     source_fragments: array<string, string>
     * }
     */
    public function extract(string $text): array
    {
        $weather = [
            'departure' => null,
            'destination' => null,
            'alternate' => null,
            'raim' => $this->raim($text),
        ];
        $sourceFragments = [];
        $matches = [];
        $pattern = '/(?<role>DEPARTURE|ARRIVAL|ALTERNATE|OTHER):\h*(?<body>.*?)(?=(?:DEPARTURE|ARRIVAL|ALTERNATE|OTHER):|KALITTA\h+BRIEF\h+PAGE\b|\z)/is';

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $role = $this->role($match['role']);

                if ($weather[$role] !== null) {
                    continue;
                }

                $airportWeather = $this->airportWeather($match['body']);

                if ($airportWeather === null) {
                    continue;
                }

                $weather[$role] = $airportWeather;
                $sourceFragments['weather_'.$role] = Str::squish($match[0]);
            }
        }

        if ($weather['raim'] !== null) {
            $sourceFragments['weather_raim'] = $weather['raim'];
        }

        return ['data' => $weather, 'source_fragments' => $sourceFragments];
    }

    private function role(string $heading): string
    {
        return match (Str::upper($heading)) {
            'DEPARTURE' => 'departure',
            'ARRIVAL' => 'destination',
            default => 'alternate',
        };
    }

    /** @return array{airport: string, metars: list<string>, tafs: list<string>}|null */
    private function airportWeather(string $section): ?array
    {
        $matches = [];
        $pattern = '/(?<type>METAR|SPECI|TAF)(?:\h+(?:AMD|COR))?\h+(?<airport>[A-Z]{4})\h+\d{6}Z\b/i';

        if (preg_match_all($pattern, $section, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false || $matches === []) {
            return null;
        }

        $airport = Str::upper($matches[0]['airport'][0]);
        $metars = [];
        $tafs = [];

        foreach ($matches as $index => $match) {
            if (Str::upper($match['airport'][0]) !== $airport) {
                continue;
            }

            $start = $match[0][1];
            $end = $matches[$index + 1][0][1] ?? strlen($section);
            $report = trim(substr($section, $start, $end - $start));
            $report = preg_replace('/\h*[?$]\h*$/', '', $report) ?? $report;
            $report = Str::squish($report);

            if ($report === '') {
                continue;
            }

            if (Str::upper($match['type'][0]) === 'TAF') {
                $tafs[] = $report;
            } else {
                $metars[] = $report;
            }
        }

        return [
            'airport' => $airport,
            'metars' => array_values(array_unique($metars)),
            'tafs' => array_values(array_unique($tafs)),
        ];
    }

    private function raim(string $text): ?string
    {
        $matches = [];

        if (preg_match(
            '/PASSED\s+RAIM\s+REQUIREMENTS\s+FOR\s+PRIMARY\s+NAVIGATION\s*VALID\s+FROM\s+(?<from>\d{4}Z)\s+TO\s+(?<to>\d{4}Z)\b/i',
            $text,
            $matches,
        ) !== 1) {
            return null;
        }

        return 'PASSED RAIM REQUIREMENTS FOR PRIMARY NAVIGATION VALID FROM '
            .Str::upper($matches['from']).' TO '.Str::upper($matches['to']);
    }
}
