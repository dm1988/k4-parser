<?php

namespace App\Services\FlightPlan\Extractor\Etops;

class EtopsRouteExtractor
{
    /**
     * @return array{
     *     data: array{
     *         etps: list<array{label: string, airports: string, coordinates: string, scenario: string}>,
     *         eent_coordinates: ?string,
     *         eexp_coordinates: ?string
     *     },
     *     source_fragments: array<string, string>
     * }
     */
    public function extract(string $text): array
    {
        return [
            'data' => [
                'etps' => $this->equalTimePoints($text),
                'eent_coordinates' => $this->markerCoordinates($text, 'EENT'),
                'eexp_coordinates' => $this->markerCoordinates($text, 'EEXP'),
            ],
            'source_fragments' => [],
        ];
    }

    /** @return list<array{label: string, airports: string, coordinates: string, scenario: string}> */
    private function equalTimePoints(string $text): array
    {
        $pattern = '/(ETP\d+)\s+([A-Z]{4}-[A-Z]{4})\s+'
            .'([NS]\d{2}\s+\d{2}\.\d\s+[EW]\d{3}\s+\d{2}\.\d)\s+'
            .'(ALL ENGINE\/DECOMPRESSION\/LRC)\b/';
        $matches = [];

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $points = [];

        foreach ($matches as $match) {
            $coordinates = preg_replace('/\s+/', ' ', trim($match[3]));

            if (! is_string($coordinates)) {
                continue;
            }

            $signature = implode('|', [$match[1], $match[2], $coordinates, $match[4]]);
            $points[$signature] ??= [
                'label' => $match[1],
                'airports' => $match[2],
                'coordinates' => $coordinates,
                'scenario' => $match[4],
            ];
        }

        return array_values($points);
    }

    private function markerCoordinates(string $text, string $marker): ?string
    {
        $pattern = '/([NS]\d{2}\s+\d{2}\.\d\s+[EW]\d{3}\s+\d{2}\.\d)'
            .'\s*\('.preg_quote($marker, '/').'\)/';
        $matches = [];

        if (preg_match($pattern, $text, $matches) !== 1) {
            return null;
        }

        $coordinates = preg_replace('/\s+/', ' ', trim($matches[1]));

        return is_string($coordinates) ? $coordinates : null;
    }
}
