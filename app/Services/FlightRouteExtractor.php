<?php

namespace App\Services;

use App\Exceptions\FlightRouteNotFoundException;
use Smalot\PdfParser\Parser;
use Throwable;

class FlightRouteExtractor
{
    private const ICAO_ROUTE_LINE_LENGTH = 58;

    private const FLIGHT_PLAN_DETAILS_PATTERN = '/\(FPL-[^-]+-[^-]+\s*-[^\r\n]*\s*-([A-Z]{4})\d{4}\s*-(?:N\d{4}|K\d{4}|M\d{3})([A-Z]\d{3,4})\h+(.+?)\s*-([A-Z]{4})(\d{4})(?:\h+([A-Z]{4}))?\b/s';

    public function __construct(
        private readonly Parser $parser,
    ) {}

    /**
     * @throws FlightRouteNotFoundException
     */
    public function extractRoute(string $filePath): string
    {
        try {
            $text = $this->parser->parseFile($filePath)->getText();
        } catch (Throwable $throwable) {
            throw FlightRouteNotFoundException::pdfCouldNotBeRead($throwable->getMessage());
        }

        return $this->extractRouteFromText($text);
    }

    /**
     * @return array{
     *     departure: string,
     *     destination: string,
     *     alternate: ?string,
     *     initial_altitude: string,
     *     duration: string,
     *     route: string
     * }
     *
     * @throws FlightRouteNotFoundException
     */
    public function extractFlightPlanData(string $filePath): array
    {
        try {
            $text = $this->parser->parseFile($filePath)->getText();
        } catch (Throwable $throwable) {
            throw FlightRouteNotFoundException::pdfCouldNotBeRead($throwable->getMessage());
        }

        return $this->extractFlightPlanDataFromText($text);
    }

    /**
     * @throws FlightRouteNotFoundException
     */
    public function extractRouteFromText(string $text): string
    {
        $flightPlanBlock = $this->extractFlightPlanBlock($text);
        $pattern = '/-(?:N\d{4}|K\d{4}|M\d{3})[A-Z]?\d{3,4}\h+(.+?)\s*-[A-Z]{4}\d{4}\b/s';

        if (! preg_match($pattern, $flightPlanBlock, $matches)) {
            throw FlightRouteNotFoundException::routeSegmentMissing();
        }

        return $this->normalizeExtractedRoute($matches[1]);
    }

    /**
     * @return array{
     *     departure: string,
     *     destination: string,
     *     alternate: ?string,
     *     initial_altitude: string,
     *     duration: string,
     *     route: string
     * }
     *
     * @throws FlightRouteNotFoundException
     */
    public function extractFlightPlanDataFromText(string $text): array
    {
        $flightPlanBlock = $this->extractFlightPlanBlock($text);

        if (! preg_match(self::FLIGHT_PLAN_DETAILS_PATTERN, $flightPlanBlock, $matches)) {
            throw FlightRouteNotFoundException::routeSegmentMissing();
        }

        $route = $this->normalizeExtractedRoute($matches[3]);

        return [
            'departure' => $matches[1],
            'destination' => $matches[4],
            'alternate' => $matches[6] ?? null,
            'initial_altitude' => $this->formatInitialAltitude($matches[2]),
            'duration' => $this->formatDuration($matches[5]),
            'route' => $route,
        ];
    }

    /**
     * @throws FlightRouteNotFoundException
     */
    private function extractFlightPlanBlock(string $text): string
    {
        if (! preg_match('/\(FPL-.*?\)/s', $text, $matches)) {
            throw FlightRouteNotFoundException::flightPlanBlockMissing();
        }

        return $matches[0];
    }

    private static function normalizeRouteLine(string $line): string
    {
        return preg_replace('/\h+/', ' ', trim($line)) ?? '';
    }

    /**
     * @throws FlightRouteNotFoundException
     */
    private function normalizeExtractedRoute(string $routeText): string
    {
        $lines = preg_split('/\R/', trim($routeText));

        if ($lines === false) {
            throw FlightRouteNotFoundException::routeSegmentEmpty();
        }

        $normalizedLines = array_values(array_filter(array_map(
            static fn (string $line): string => self::normalizeRouteLine($line),
            $lines,
        )));

        if ($normalizedLines === []) {
            throw FlightRouteNotFoundException::routeSegmentEmpty();
        }

        return implode(PHP_EOL, $normalizedLines);
    }

    private function formatInitialAltitude(string $level): string
    {
        if (preg_match('/^F(\d{3,4})$/', $level, $matches) === 1) {
            return 'FL '.$matches[1];
        }

        return $level;
    }

    private function formatDuration(string $duration): string
    {
        return substr($duration, 0, 2).'h'.substr($duration, 2, 2).'m';
    }

    public function formatForIcaoDisplay(string $route): string
    {
        $segments = $this->routeElements($route);

        if ($segments === []) {
            return trim($route);
        }

        $lines = [];
        $currentLine = '';

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            $linePrefix = $lines === [] ? '' : ' ';
            $candidate = $currentLine === ''
                ? $linePrefix.$segment
                : $currentLine.' '.$segment;

            if ($currentLine !== '' && strlen($candidate) > self::ICAO_ROUTE_LINE_LENGTH) {
                $lines[] = $currentLine;
                $currentLine = ' '.$segment;

                continue;
            }

            $currentLine = $candidate;
        }

        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * ICAO routes are whitespace-delimited elements, so wraps must happen
     * only before the next full element and never inside one.
     *
     * @return array<int, string>
     */
    private function routeElements(string $route): array
    {
        $segments = preg_split('/\s+/', trim($route));

        if ($segments === false) {
            return [];
        }

        return array_values(array_filter(
            $segments,
            static fn (string $segment): bool => $segment !== '',
        ));
    }
}
