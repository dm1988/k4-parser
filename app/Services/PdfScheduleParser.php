<?php

namespace App\Services;

use App\Mappers\FlightMapper;
use Smalot\PdfParser\Parser;

class PdfScheduleParser
{
    protected Parser $parser;

    public function __construct(
        private readonly FlightMapper $flightMapper,
    ) {
        $this->parser = new Parser;
    }

    /**
     * Parse a schedule PDF and return a structured array.
     */
    public function parse(string $path): array
    {
        $pdf = $this->parser->parseFile($path);
        $rawText = $pdf->getText();

        // 1. Critical Sanitization Layer: Strip out interleaved null bytes
        $text = str_replace("\x00", '', $rawText);

        // Standardize line endings and clean up blank lines
        $lines = preg_split('/\r?\n/', $text);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, fn ($l) => $l !== '');
        $lines = array_values($lines);

        $result = [
            'file' => $path,
            'text' => $text, // Cleaned text string
            'pdf_meta' => [
                'trip_id' => null,
                'date' => null,
                'page_count' => count($pdf->getPages()),
            ],
            'parsed' => [
                'trip' => [
                    'trip_number' => null,
                    'position' => null,
                    'base' => null,
                    'layovers' => [],
                    'block_time' => null,
                    'roster_range' => null,
                ],
                'calendar_events' => [],
            ],
            'crew' => [],
        ];

        // 2. Metadata Extraction
        if (preg_match('/Trip\s*Id:\s*(\d+)/i', $text, $m)) {
            $result['pdf_meta']['trip_id'] = $m[1];
            $result['parsed']['trip']['trip_number'] = $m[1];
        }
        if (preg_match('/Date:\s*([0-9]{1,2}[A-Za-z]{3}[0-9]{4})/i', $text, $m)) {
            $result['pdf_meta']['date'] = $m[1];
        }
        if (preg_match('/Homebase:\s*([A-Z]{3,4})/i', $text, $m)) {
            $result['parsed']['trip']['base'] = $m[1];
        }
        if (preg_match('/Block\s+Time:\s*(\d{1,3}:\d{2})/i', $text, $m)) {
            $result['parsed']['trip']['block_time'] = $m[1];
        }
        if (preg_match('/Crew:\s*([A-Za-z0-9]+)/i', $text, $m)) {
            $result['parsed']['trip']['position'] = $m[1];
        }

        // 3. Flight Engine Processing (Route-Anchored Logic)
        $daysPattern = '/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun)\b/i';

        foreach ($lines as $index => $currentLine) {
            if (preg_match($daysPattern, $currentLine)) {

                $prevLine = $lines[$index - 1] ?? '';
                $nextLine = $lines[$index + 1] ?? '';

                // Find the sector route pattern (e.g., CVG-NRT, AUS-CVG, HKG-HKG)
                if (preg_match('/([A-Z]{3,4}-[A-Z]{3,4})/', $currentLine, $routeMatch, PREG_OFFSET_CAPTURE)) {
                    $route = $routeMatch[1][0];
                    $routeOffset = $routeMatch[1][1];

                    // Everything before the route contains Day, DH flag, and Flight number
                    $beforeRoute = trim(substr($currentLine, 0, $routeOffset));

                    // Isolate Day and Deadhead flags
                    preg_match('/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun)(\s+DH)?/i', $beforeRoute, $dayDhMatch);
                    $day = $dayDhMatch[1] ?? '';
                    $isDh = ! empty($dayDhMatch[2]);

                    // The remaining text in beforeRoute is the Flight ID
                    $flight = trim(substr($beforeRoute, strlen($dayDhMatch[0] ?? '')));

                    // Everything after the route contains times and aircraft tags
                    $afterRoute = trim(substr($currentLine, $routeOffset + strlen($route)));

                    // Unpack compressed consecutive HH:MM timestamps
                    preg_match_all('/\d{2}:\d{2}/', $afterRoute, $timeMatches);
                    $times = $timeMatches[0] ?? [];

                    // Extract tail aircraft frame codes (e.g., 77X, 77V)
                    $ac = null;
                    if (preg_match('/77[XV]/i', $afterRoute, $acMatch)) {
                        $ac = strtoupper($acMatch[0]);
                    }

                    // Gather operational context boundaries from surrounding rows
                    $dutyStart = null;
                    $dutyEnd = null;
                    $date = null;

                    if (preg_match('/Duty start\s*(\d{2}:\d{2})/i', $prevLine, $pm)) {
                        $dutyStart = $pm[1];
                    }
                    if (preg_match('/^([0-9]{1,2}[A-Za-z]{3})Duty end\s*(\d{2}:\d{2})/i', $nextLine, $nm)) {
                        $date = $nm[1];
                        $dutyEnd = $nm[2];
                    }

                    // Grab block hours dynamically based on item presence
                    $block = (count($times) >= 5) ? $times[4] : null;

                    // Append into standard structure
                    $result['parsed']['calendar_events'][] = [
                        'date' => $date,
                        'day' => $day,
                        'flight_number' => $flight,
                        'route' => $route,
                        'is_deadhead' => $isDh,
                        'aircraft_type' => $ac,
                        'block_time' => $block,
                        'duty_start' => $dutyStart,
                        'duty_end' => $dutyEnd,
                        'raw_times' => $times,
                    ];

                    if ($route && ! in_array($route, $result['parsed']['trip']['layovers'])) {
                        $result['parsed']['trip']['layovers'][] = $route;
                    }
                }
            }
        }

        // 4. Crew Block Parser
        $crewStart = null;
        foreach ($lines as $i => $line) {
            if (preg_match('/Crew on trip/i', $line)) {
                $crewStart = $i + 2; // Pass over the table column headers
                break;
            }
        }

        if ($crewStart !== null) {
            for ($i = $crewStart; $i < count($lines); $i++) {
                $l = $lines[$i];
                if (preg_match('/^(Annotations|Created)/i', $l) || trim($l) === '' || strpos($l, 'None') !== false) {
                    break;
                }

                if (preg_match('/^([A-Z]{2,3})\s+(\d+)\s+(.+)$/', $l, $m)) {
                    $result['crew'][] = [
                        'position' => $m[1],
                        'crew_id' => $m[2],
                        'name' => trim($m[3]),
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * @return list\App\DTOs\Flight
     */
    public function extractFlightsDto(string $path): array
    {
        $data = $this->parse($path);
        $events = $data['parsed']['calendar_events'] ?? [];
        $flights = [];

        foreach ($events as $ev) {
            if (! is_array($ev)) {
                continue;
            }

            $route = $ev['route'] ?? null;
            $origin = null;
            $destination = null;

            if (is_string($route) && str_contains($route, '-')) {
                [$origin, $destination] = explode('-', $route, 2) + [null, null];
            }

            $calendarEvent = [
                'type' => 'flight',
                'title' => trim((string) ($ev['flight_number'] ?? '').' '.($route ?? '')),
                'start' => $ev['date'] ?? null,
                'end' => $ev['date'] ?? null,
                'timezone' => config('app.timezone'),
                'metadata' => array_filter([
                    'flight_number' => $ev['flight_number'] ?? null,
                    'origin' => $origin,
                    'destination' => $destination,
                    'aircraft' => $ev['aircraft_type'] ?? null,
                    'deadhead' => $ev['is_deadhead'] ?? false,
                    'block_time' => $ev['block_time'] ?? null,
                    'raw_times' => $ev['raw_times'] ?? null,
                ], fn ($v) => $v !== null && $v !== ''),
            ];

            $flight = $this->flightMapper->fromCalendarEvent($calendarEvent);

            if ($flight !== null) {
                $flights[] = $flight;
            }
        }

        return $flights;
    }
}
