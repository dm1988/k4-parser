<?php

namespace App\Services;

use App\Exceptions\FlightRouteNotFoundException;
use Smalot\PdfParser\Parser;
use Throwable;

class FlightRouteExtractor
{
    private const ICAO_ROUTE_LINE_LENGTH = 58;

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
     * @throws FlightRouteNotFoundException
     */
    public function extractRouteFromText(string $text): string
    {
        $flightPlanBlock = $this->extractFlightPlanBlock($text);
        $pattern = '/-(?:N\d{4}|K\d{4}|M\d{3})[A-Z]?\d{3,4}\h+(.+?)\s*-[A-Z]{4}\d{4}\b/s';

        if (! preg_match($pattern, $flightPlanBlock, $matches)) {
            throw FlightRouteNotFoundException::routeSegmentMissing();
        }

        $lines = preg_split('/\R/', trim($matches[1]));

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
