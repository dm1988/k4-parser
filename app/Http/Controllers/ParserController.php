<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Throwable;
use App\Services\PdfScheduleParser;
use Illuminate\Support\Facades\Storage;


class ParserController extends Controller
{
    public function index()
    {
        return view('parse');
    }

    // Deprecated methods `showUpload` and `parseUpload` removed — uploads now handled by `parseRoster`

    public function parseFlight(Request $request)
    {
        $text = $request->validate([
            'text' => ['required', 'string'],
        ])['text'];

        return back()->with('result', [
            'type' => 'flight',
            'raw' => $text,
            'parsed' => $this->extractFlight($text),
        ]);
    }

    public function parseHotel(Request $request)
    {
        $text = $request->validate([
            'text' => ['required', 'string'],
        ])['text'];

        return back()->with('result', [
            'type' => 'hotel',
            'raw' => $text,
            'parsed' => $this->extractHotel($text),
        ]);
    }

    public function parseRoster(Request $request)
    {
        $data = $request->validate([
            'file'          => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,bmp,tif,tiff,webp', 'max:20480', 'required_without:text'],
            'text'          => ['nullable', 'string', 'required_without:file'],
            'event_types'   => ['nullable', 'array'],
            'event_types.*' => ['in:flight,layover'],
        ]);

        $eventTypes = $data['event_types'] ?? [];

        // Track core execution metrics uniformly
        $text = '';
        $sourceType = 'text';
        $path = null;
        $meta = [];

        // 1. Ingestion Layer
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('uploads');
            $mime = $file->getMimeType();

            if ($mime === 'application/pdf') {
                $sourceType = 'pdf';
                try {
                    // Use getRealPath() if available; fallback securely to local disk reference
                    $tmpPath = $file->getRealPath();
                    $targetPath = ($tmpPath && file_exists($tmpPath)) ? $tmpPath : storage_path('app/' . $path);

                    $pdfData = app(PdfScheduleParser::class)->parse($targetPath);
                    $text = $pdfData['text'] ?? '';
                    $meta = [
                        'trip_id' => $pdfData['trip_id'] ?? null,
                        'date'    => $pdfData['date'] ?? null,
                    ];
                } catch (\Exception $e) {
                    return back()->with('result', ['error' => 'PDF parse failed: ' . $e->getMessage()]);
                }
            } else {
                $sourceType = 'image';
                try {
                    $text = $this->extractTextFromImage($file->getRealPath());
                } catch (\Exception $e) {
                    return back()->with('result', ['error' => 'Image OCR failed: ' . $e->getMessage()]);
                }
            }
        } else {
            $text = $data['text'] ?? '';
        }

        // 2. Core Processing Engine
        $parsed = $this->extractRoster($text);

        // Apply array filters cleanly to all source paths matching validation constraints
        if (!empty($eventTypes)) {
            $parsed['calendar_events'] = array_values(array_filter(
                $parsed['calendar_events'] ?? [],
                fn(array $event) => in_array($event['type'] ?? '', $eventTypes, true)
            ));
        }

        // 3. Normalized Output Contract
        $result = [
            'type'     => 'roster',
            'source'   => $sourceType,
            'file'     => $path,
            'mime'     => $mime ?? null,
            'raw'      => $text,
            'raw_text' => $text,
            'parsed'   => $parsed,
            'filters'  => $eventTypes,
            'meta'     => $meta ?: null,
        ];

        session(['parsed_result' => $result]);

        return back()->with('result', $result);
    }

    public function exportCalendar(Request $request)
    {
        $sessionResult = session('parsed_result', session('result'));

        if (! is_array($sessionResult) || ! isset($sessionResult['parsed']['calendar_events'])) {
            abort(404);
        }

        $eventTypes = $request->query('event_types', $sessionResult['filters'] ?? []);
        $events = $sessionResult['parsed']['calendar_events'];

        if ($eventTypes !== []) {
            $events = array_values(array_filter(
                $events,
                fn(array $event) => in_array($event['type'], $eventTypes, true),
            ));
        }

        if (count($events) === 0) {
            abort(404);
        }

        $tripNumber = $sessionResult['parsed']['trip']['trip_number'] ?? null;
        $filename = 'crew-compass' . ($tripNumber ? "-{$tripNumber}" : '') . '.ics';

        return response($this->buildIcs($events, $sessionResult['parsed']['trip'] ?? []), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportCalendarEvent(Request $request, int $eventIndex)
    {
        $sessionResult = session('parsed_result', session('result'));

        if (! is_array($sessionResult) || ! isset($sessionResult['parsed']['calendar_events'][$eventIndex])) {
            abort(404);
        }

        $event = $sessionResult['parsed']['calendar_events'][$eventIndex];
        $trip = $sessionResult['parsed']['trip'] ?? [];
        $tripNumber = $trip['trip_number'] ?? null;
        $slug = 'event-' . $eventIndex;
        $filename = 'crew-compass' . ($tripNumber ? "-{$tripNumber}" : '') . '-' . $slug . '.ics';

        return response($this->buildIcs([$event], $trip), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function extractFlight(string $text): array
    {
        return $this->extractRoster($text)['calendar_events'];
    }

    private function extractHotel(string $text): array
    {
        return array_values(array_filter(
            $this->extractRoster($text)['calendar_events'],
            fn(array $event) => $event['type'] === 'layover',
        ));
    }

    private function extractRoster(string $text): array
    {
        $lines = $this->normaliseLines($text);
        $defaultYear = $this->detectRosterYear($lines);
        $monthYears = $this->detectMonthYears($lines, $defaultYear);

        // 1. Dynamic Boundary Identification
        // Find the table header row where flight records actually begin
        $detailStart = $this->firstLineMatchingPattern($lines, '/\b(?:Details|Day\s*Flight\s*Departure)\b/i');

        // Find where the duty rows end so we don't accidentally parse metadata as events
        $detailEnd = $this->firstLineMatchingPattern($lines, '/Duty Summary/i');

        if ($detailStart !== false) {
            $sliceLength = ($detailEnd !== false) ? ($detailEnd - $detailStart - 1) : null;
            $detailLines = array_slice($lines, $detailStart + 1, $sliceLength);
        } else {
            $detailLines = $lines;
        }

        $events = [];

        foreach ($this->detailBlocks($detailLines) as $block) {
            $event = $this->parseDetailBlock($block, $monthYears, $defaultYear);

            if ($event !== null) {
                $events[] = $event;
            }
        }

        return [
            'trip' => $this->extractTripSummary($lines),
            'calendar_events' => $events,
        ];
    }

    /**
     * Helper to dynamically locate lines using regex patterns instead of rigid string matches.
     */
    private function firstLineMatchingPattern(array $lines, string $pattern): int|false
    {
        foreach ($lines as $index => $line) {
            if (preg_match($pattern, $line)) {
                return $index;
            }
        }
        return false;
    }
    private function extractTextFromImage(string $path): string
    {
        $tesseract = config('services.ocr.tesseract_path', '/usr/bin/tesseract');

        if (! is_executable($tesseract)) {
            throw ValidationException::withMessages([
                'image' => "OCR is not installed in the web server container. Expected Tesseract at {$tesseract}.",
            ]);
        }

        $process = new Process([
            $tesseract,
            $path,
            'stdout',
            '--psm',
            '6',
        ]);
        $process->setTimeout(30);

        try {
            $process->mustRun();
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'image' => 'OCR failed. Try a sharper roster screenshot or paste the extracted text instead.',
            ]);
        }

        $text = trim($process->getOutput());

        if ($text === '') {
            throw ValidationException::withMessages([
                'image' => 'OCR did not find any text in that image. Try a clearer screenshot or paste the text manually.',
            ]);
        }

        return $text;
    }

    private function normaliseLines(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $lines = [];

        foreach (explode("\n", $text) as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line));

            if ($line === '') {
                continue;
            }

            if (preg_match('/([A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2}\s+-\s+[A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2})/', $line, $matches)) {
                $before = trim(substr($line, 0, strpos($line, $matches[1])));
                $after = trim(substr($line, strpos($line, $matches[1]) + strlen($matches[1])));

                if ($before !== '') {
                    $lines[] = $before;
                }

                $lines[] = $matches[1];

                if ($after !== '') {
                    $lines[] = $after;
                }

                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    private function detectRosterYear(array $lines): int
    {
        foreach ($lines as $line) {
            if (preg_match('/\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2}\s+\d{2}:\d{2}\b/', $line)) {
                continue;
            }

            if (preg_match('/\b(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})\b/', $line, $matches)) {
                return (int) $matches[1];
            }
        }

        return (int) now()->year;
    }

    private function detectMonthYears(array $lines, int $defaultYear): array
    {
        $monthYears = [];

        foreach ($lines as $line) {
            if (preg_match('/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})\b/', $line, $matches)) {
                $monthYears[substr($matches[1], 0, 3)] = (int) $matches[2];
                $monthYears[strtolower(substr($matches[1], 0, 3))] = (int) $matches[2];
            }
        }

        return $monthYears ?: ['Jan' => $defaultYear];
    }

    private function detailBlocks(array $lines): array
    {
        $blocks = [];
        $currentBlock = [];
        $mode = null;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if (str_contains($trimmedLine, 'Duty start')) {
                if (!empty($currentBlock)) {
                    $blocks[] = $currentBlock;
                }

                $currentBlock = [$trimmedLine];
                $mode = 'duty';
                continue;
            }

            if ($this->isDateRange($trimmedLine)) {
                if (!empty($currentBlock)) {
                    $blocks[] = $currentBlock;
                }

                $currentBlock = [$trimmedLine];
                $mode = 'date-range';
                continue;
            }

            if ($mode !== null) {
                $currentBlock[] = $trimmedLine;

                if ($mode === 'duty' && str_contains($trimmedLine, 'Duty end')) {
                    $blocks[] = $currentBlock;
                    $currentBlock = [];
                    $mode = null;
                }
            }
        }

        if (!empty($currentBlock)) {
            $blocks[] = $currentBlock;
        }

        return $blocks;
    }
    private function parseDetailBlock(array $block, array $monthYears, int $defaultYear): ?array
    {
        if ($this->isDateRange($block[0] ?? '')) {
            return $this->parseDateRangeDetailBlock($block, $monthYears, $defaultYear);
        }

        // Ensure we have a valid 3-line duty segment block
        if (count($block) < 3) {
            return null;
        }

        $lineStart = $block[0]; // "Duty start\t22:44"
        $lineData  = $block[1]; // "Fri DH G4368AUS-CVG 17:4421:1722:4401:17 -        "
        $lineEnd   = $block[2]; // "12JunDuty end\t01:17"

        // 1. Extract the Event Date (e.g., "12Jun") from the bottom line anchor
        if (!preg_match('/(\d{1,2})([A-Za-z]{3})Duty\s+end/i', $lineEnd, $dateMatches)) {
            return null;
        }

        $day = $dateMatches[1];
        $monthStr = $dateMatches[2];
        $year = $monthYears[strtolower($monthStr)] ?? $defaultYear;

        // 2. Extract Smashed Flight Operational Times
        // Looks for 4 consecutive HH:MM timestamps smashed together: Departure, Arrival, Local Start, Local End
        if (!preg_match('/(\d{2}:\d{2})(\d{2}:\d{2})(\d{2}:\d{2})(\d{2}:\d{2})/', $lineData, $timeMatches)) {
            return null;
        }

        $depTime = $timeMatches[1]; // "17:44"
        $arrTime = $timeMatches[2]; // "21:17"

        // 3. Extract Flight Number and Airport Pair
        // Capture the airline designator + number (e.g., "DH G4368" or "206") and routing ("AUS-CVG")
        if (!preg_match('/(?:[A-Z]{2}\s+)?([A-Z0-9]+)\s+([A-Z]{3})-([A-Z]{3})/', $lineData, $flightMatches)) {
            return null;
        }

        $flightNumber = $flightMatches[1]; // "G4368" or "206"
        $origin       = $flightMatches[2]; // "AUS"
        $destination  = $flightMatches[3]; // "CVG"

        // 4. Construct Precise Carbon Timestamps
        // Note: If a flight crosses midnight (e.g., Departure 23:00, Arrival 02:00), adjust the date forward.
        try {
            $start = now()->createFromFormat('Y-M-d H:i', "{$year}-{$monthStr}-{$day} {$depTime}");
            $end   = now()->createFromFormat('Y-M-d H:i', "{$year}-{$monthStr}-{$day} {$arrTime}");

            if ($end->lessThan($start)) {
                $end->addDay(); // Handle overnight flight spans smoothly
            }
        } catch (\Exception $e) {
            return null; // Skip if date parsing engine fails invalid configurations
        }

        // Determine event classification type
        $isDeadhead = str_contains(strtoupper($lineData), ' DH ');
        $type = $isDeadhead ? 'layover' : 'flight'; // Or map custom flags based on your $eventTypes rules
        $title = "{$origin} - {$destination} ({$flightNumber})";

        // 5. Build and Return standard payload contract
        return $this->calendarEvent($type, $title, $start, $end, [
            'flight_number' => $flightNumber,
            'origin'        => $origin,
            'destination'   => $destination,
            'deadhead'      => $isDeadhead,
        ]);
    }

    private function parseDateRangeDetailBlock(array $block, array $monthYears, int $defaultYear): ?array
    {
        if (!preg_match('/^([A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2})\s+-\s+([A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2})$/', $block[0], $matches)) {
            return null;
        }

        $start = $this->parseRosterDate($matches[1], $monthYears, $defaultYear);
        $end = $this->parseRosterDate($matches[2], $monthYears, $defaultYear);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addYear();
        }

        $body = array_values(array_filter(array_slice($block, 1), fn(string $line): bool => $line !== ''));
        $joinedBody = implode(' ', $body);

        if ($route = $this->extractFlightRoute($body)) {
            $flightNumber = $this->extractFlightNumber($body);
            $position = $this->detectCrewPosition($body);
            $aircraft = $this->detectAircraft($body);
            $blockTime = $this->firstMatchingLine($body, '/\b\d{1,2}:\d{2}h\b/');
            $isDeadhead = (bool) preg_match('/\bDH\b/i', $joinedBody);

            return $this->calendarEvent(
                'flight',
                trim(($flightNumber ? "{$flightNumber} " : '') . "{$route['origin']}-{$route['destination']}"),
                $start,
                $end,
                array_filter([
                    'flight_number' => $flightNumber,
                    'origin' => $route['origin'],
                    'destination' => $route['destination'],
                    'position' => $position,
                    'aircraft' => $aircraft,
                    'block_time' => $blockTime,
                    'deadhead' => $isDeadhead,
                    'raw_lines' => $body,
                ], fn($value) => $value !== null && $value !== '')
            );
        }

        if ($layover = $this->extractLayover($body)) {
            return $this->calendarEvent(
                'layover',
                "Layover {$layover['station']}",
                $start,
                $end,
                [
                    'station' => $layover['station'],
                    'hotel' => $layover['hotel'],
                    'raw_lines' => $body,
                ],
            );
        }

        if (preg_match('/\b([A-Z]{3})\b/', $joinedBody, $matches)) {
            return $this->calendarEvent(
                'duty',
                "Duty {$matches[1]}",
                $start,
                $end,
                [
                    'station' => $matches[1],
                    'raw_lines' => $body,
                ],
            );
        }

        return null;
    }

    private function extractFlightRoute(array $lines): ?array
    {
        foreach ($lines as $line) {
            if (preg_match('/\b([A-Z]{3})\s*-\s*([A-Z]{3})\b/', $line, $matches)) {
                return [
                    'origin' => $matches[1],
                    'destination' => $matches[2],
                ];
            }
        }

        return null;
    }

    private function extractLayover(array $lines): ?array
    {
        foreach ($lines as $line) {
            if (!preg_match('/\b([A-Z]{3})\s*-\s*(.+)$/', $line, $matches)) {
                continue;
            }

            $hotel = preg_replace('/\s+[vV]{1,2}\s*$/', '', trim($matches[2]));

            if (preg_match('/^[A-Z]{3}\b/', $hotel)) {
                continue;
            }

            return [
                'station' => $matches[1],
                'hotel' => trim($hotel, " \t\n\r\0\x0B|"),
            ];
        }

        return null;
    }

    private function extractFlightNumber(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/\b([A-Z][A-Z0-9]?\s*\d{1,4}[A-Z]?)\b/', $line, $matches)) {
                return preg_replace('/\s+/', ' ', trim($matches[1]));
            }
        }

        return null;
    }

    private function parseRosterDate(string $value, array $monthYears, int $defaultYear): Carbon
    {
        preg_match('/^([A-Z][a-z]{2})\s+(\d{1,2})\s+(\d{2}:\d{2})$/', $value, $matches);

        $year = $monthYears[$matches[1]] ?? $defaultYear;

        return Carbon::createFromFormat('Y M j H:i', "{$year} {$matches[1]} {$matches[2]} {$matches[3]}");
    }

    private function calendarEvent(string $type, string $title, Carbon $start, Carbon $end, array $metadata = []): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'start' => $start->toIso8601String(),
            'end' => $end->toIso8601String(),
            'timezone' => config('app.timezone'),
            'metadata' => $metadata,
        ];
    }

    private function buildIcs(array $events, array $trip = []): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'PRODID:-//Crew Compass//Roster Parser//EN',
        ];

        if (! empty($trip['trip_number'])) {
            $lines[] = 'X-WR-CALNAME:Crew Compass Trip ' . $this->escapeIcsValue($trip['trip_number']);
        }

        $lines[] = 'X-WR-CALDESC:Calendar export from Crew Compass';

        foreach ($events as $event) {
            $start = Carbon::parse($event['start'])->setTimezone('UTC');
            $end = Carbon::parse($event['end'])->setTimezone('UTC');
            $description = $this->formatEventDescription($event);
            $uid = sha1($event['title'] . $event['start'] . $event['end']);

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid . '@crew-compass';
            $lines[] = 'DTSTAMP:' . now()->setTimezone('UTC')->format('Ymd\THis\Z');
            $lines[] = 'DTSTART:' . $start->format('Ymd\THis\Z');
            $lines[] = 'DTEND:' . $end->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:' . $this->escapeIcsValue($event['title']);
            $lines[] = 'DESCRIPTION:' . $this->escapeIcsValue($description);
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private function formatEventDescription(array $event): string
    {
        $description = ['Type: ' . ucfirst($event['type'])];

        foreach ($event['metadata'] as $key => $value) {
            if ($key === 'raw_lines') {
                continue;
            }

            if (is_array($value)) {
                $value = implode(', ', array_filter($value, fn($item) => $item !== null && $item !== ''));
            }

            if ($value === null || $value === '') {
                continue;
            }

            $description[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
        }

        return implode("\n", $description);
    }

    private function escapeIcsValue(string $value): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", ',', ';'],
            ['\\\\', '\\n', '\\n', '\\,', '\\;'],
            $value,
        );
    }

    private function extractTripSummary(array $lines): array
    {
        $summary = [
            'trip_number'  => null,
            'position'     => null,
            'base'         => null,
            'layovers'     => [],
            'block_time'   => null,
            'roster_range' => null,
        ];

        // Join lines temporarily to perform global regex scans easily across inline boundaries
        $fullText = implode("\n", $lines);

        if (preg_match('/Trip\s*Id:\s*(\d+)/i', $fullText, $matches)) {
            $summary['trip_number'] = $matches[1];
        } elseif (preg_match('/\bTrip\b\D+(\d{4,})\b/s', $fullText, $matches)) {
            $summary['trip_number'] = $matches[1];
        }

        if (preg_match('/Crew:\s*\d*([A-Z]{2})/i', $fullText, $matches)) {
            $summary['position'] = strtoupper($matches[1]);
        } elseif (preg_match('/\b\d{4,}\s*\|?\s*([A-Z]{2})\.?\s+[A-Z]{3}\b/', $fullText, $matches)) {
            $summary['position'] = strtoupper($matches[1]);
        } else {
            $summary['position'] = $this->detectCrewPosition($lines);
        }

        if (preg_match('/Homebase:\s*([A-Z]{3})/i', $fullText, $matches)) {
            $summary['base'] = $matches[1];
        } elseif (preg_match('/\b\d{4,}\s*\|?\s*[A-Z]{2}\.?\s+([A-Z]{3})\b/', $fullText, $matches)) {
            $summary['base'] = $matches[1];
        }

        if (preg_match('/Block\s+Time:\s*(\d{2}:\d{2})/i', $fullText, $matches)) {
            $summary['block_time'] = $matches[1];
        } elseif (preg_match('/\bBlock\s+(\d{1,2}:\d{2}h?)\b/i', $fullText, $matches)) {
            $summary['block_time'] = $matches[1];
        }

        // 5. Build Layover List Dynamically
        // Scans your main flight text for airport routings like "AUS-CVG" or "CVG-NRT"
        if (preg_match_all('/([A-Z]{3})-([A-Z]{3})/', $fullText, $matches)) {
            $stations = [];
            foreach ($matches[2] as $arrivalStation) {
                // If they land somewhere that isn't their home base, track it as a layover spot
                if ($summary['base'] && $arrivalStation !== $summary['base']) {
                    $stations[] = $arrivalStation;
                }
            }
            // Deduplicate the array routing stops cleanly
            $summary['layovers'] = array_values(array_unique($stations));
        }

        // 6. Infer Roster Range from Trip Header Dates if present
        if (preg_match('/Date:\s*(\d{2}[A-Za-z]{3}\d{4})/', $fullText, $matches)) {
            $summary['roster_range'] = $matches[1]; // Fallback anchor date context
        }

        return $summary;
    }

    private function isDateRange(string $line): bool
    {
        return (bool) preg_match('/^[A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2}\s+-\s+[A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2}$/', $line);
    }

    private function firstMatchingLine(array $lines, string $pattern): ?string
    {
        foreach ($lines as $line) {
            if (preg_match($pattern, $line)) {
                return $line;
            }
        }

        return null;
    }

    private function firstLineIndexContaining(array $lines, string $needle): int|false
    {
        foreach ($lines as $index => $line) {
            if (str_contains($line, $needle)) {
                return $index;
            }
        }

        return false;
    }

    private function detectCrewPosition(array $lines): ?string
    {
        $positions = ['CA', 'CAPT', 'FO', 'DH', 'FE', 'AC'];

        foreach ($lines as $line) {
            if (in_array($line, $positions, true)) {
                return $line;
            }
        }

        return null;
    }

    private function detectAircraft(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/^(?:\d{2}[A-Z]|[A-Z]\d{2})$/', $line)) {
                return $line;
            }
        }

        return null;
    }
}
