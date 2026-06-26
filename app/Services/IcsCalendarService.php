<?php

namespace App\Services;

use App\DTOs\Flight;
use App\Enums\ParserEventType;
use App\Mappers\FlightMapper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class IcsCalendarService
{
    public function __construct(
        private readonly FlightMapper $flightMapper,
    ) {
    }

    public function serialize(array $events, array $trip = []): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'PRODID:-//Crew Compass//Roster Parser//EN',
        ];

        if (! empty($trip['trip_number'])) {
            $lines[] = 'X-WR-CALNAME:Crew Compass Trip '.$this->escapeValue($trip['trip_number']);
        }

        $lines[] = 'X-WR-CALDESC:Calendar export from Crew Compass';

        foreach ($events as $event) {
            $event = $this->normalizeEvent($event);

            if ($event === null) {
                continue;
            }

            $start = Carbon::parse($event['start'])->setTimezone('UTC');
            $end = Carbon::parse($event['end'])->setTimezone('UTC');

            $event['metadata']['utc_start'] = $start->format('m-d H:i').'Z';
            $event['metadata']['utc_end'] = $end->format('m-d H:i').'Z';

            $flightAwareUrl = $event['metadata']['flightaware_url'] ?? null;
            $description = $this->formatDescription($event);
            $uid = sha1($event['title'].$event['start'].$event['end']);

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$uid.'@crew-compass';
            $lines[] = 'DTSTAMP:'.now()->setTimezone('UTC')->format('Ymd\THis\Z');
            $lines[] = 'DTSTART:'.$start->format('Ymd\THis\Z');
            $lines[] = 'DTEND:'.$end->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:'.$this->escapeValue($event['title']);
            if ($flightAwareUrl) {
                $lines[] = 'URL:'.$this->escapeValue($flightAwareUrl);
            }
            $lines[] = 'DESCRIPTION:'.$this->escapeValue($description);
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    private function normalizeEvent(mixed $event): ?array
    {
        if ($event instanceof Flight) {
            return $this->flightMapper->toCalendarEvent($event);
        }

        return is_array($event) ? $event : null;
    }

    private function formatDescription(array $event): string
    {
        $eventType = ParserEventType::fromEvent($event);
        $metadata = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];

        // 1. Header & Type Information
        $lines = [
            "✈️ TYPE: " . $eventType->label() . " (" . $eventType->description() . ")",
            "----------------------------------------",
        ];

        // 2. Separate metadata fields for logical grouping
        $flightDetails = [];
        $crewInfo = [];
        $timings = [];

        foreach ($metadata as $key => $value) {
            // Drop clutter fields that aren't useful in a calendar note
            if (in_array($key, ['raw_lines', 'flightaware_url', 'duty_raw_lines'])) {
                continue;
            }

            if ($key === 'deadhead' && !$value) {
                continue;
            }

            // Clean & safe string conversion
            $stringVal = $this->stringifyMetadataValue($value);
            if ($stringVal === null || $stringVal === '') {
                continue;
            }

            $label = ucfirst(str_replace('_', ' ', $key));
            $formattedLine = "• {$label}: {$stringVal}";

            // Sort fields into their respective blocks
            if (in_array($key, ['utc_start', 'utc_end'])) {
                $timings[] = $formattedLine;
            } elseif (Str::contains($key, 'crew') || $key === 'crew_count' || $key === 'operating_crew_count') {
                // If it's the main crew array, split members onto their own bullet lines
                if ($key === 'crew' && is_array($value)) {
                    $crewInfo[] = "• Crew Members:";
                    foreach ($value as $member) {
                        $name = $member['name'] ?? 'Unknown';
                        $role = isset($member['role']) ? " ({$member['role']})" : '';
                        $crewInfo[] = "  └─ {$name}{$role}";
                    }
                } else {
                    $crewInfo[] = $formattedLine;
                }
            } else {
                $flightDetails[] = "• {$label}: {$stringVal}";
            }
        }

        // 3. Compile the sections neatly with double line breaks
        if (!empty($flightDetails)) {
            $lines[] = "📦 FLIGHT DETAILS\n" . implode("\n", $flightDetails);
        }

        if (!empty($crewInfo)) {
            $lines[] = "\n👥 CREW LOGISTICS\n" . implode("\n", $crewInfo);
        }

        if (!empty($timings)) {
            $lines[] = "\n⏰ TIMINGS (UTC)\n" . implode("\n", $timings);
        }

        // Return a single clean string. (The parent loop passes this to escapeValue, 
        // which converts \n into literal calendar-safe \n syntax)
        return implode("\n", $lines);
    }

    private function stringifyMetadataValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (! is_array($value)) {
            return null;
        }

        $parts = [];

        foreach ($value as $key => $item) {
            $item = $this->stringifyMetadataValue($item);

            if ($item === null || $item === '') {
                continue;
            }

            if (is_string($key)) {
                $parts[] = ucfirst(str_replace('_', ' ', $key)).': '.$item;

                continue;
            }

            $parts[] = $item;
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function escapeValue(string $value): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", ',', ';'],
            ['\\\\', '\\n', '\\n', '\\,', '\\;'],
            $value,
        );
    }
}
