<?php

namespace App\Services;

use App\DTOs\Flight;
use App\Enums\ParserEventType;
use App\Mappers\FlightMapper;
use App\Enums\MetadataKey;
use Illuminate\Support\Carbon;

class IcsCalendarService
{
    public function __construct(
        private readonly FlightMapper $flightMapper,
        private readonly CrewParserService $crewParser,
    ) {}

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
            $lines[] = 'X-WR-CALNAME:JCA Parsed Trip '.$this->escapeValue($trip['trip_number']);
        }

        $lines[] = 'X-WR-CALDESC:Calendar export from Crew Compass JCA parser';

        foreach ($events as $event) {
            $event = $this->normalizeEvent($event);

            if ($event === null) {
                continue;
            }

            $start = Carbon::parse($event['start'])->setTimezone('UTC');
            $end = Carbon::parse($event['end'])->setTimezone('UTC');

            $event['metadata'][MetadataKey::UtcStart->value] = $start->format('m-d H:i').' Z';
            $event['metadata'][MetadataKey::UtcEnd->value] = $end->format('m-d H:i').' Z';
            $event['metadata'][MetadataKey::LocalStart->value] = $start->format('m-d H:i').' ';
            $event['metadata'][MetadataKey::LocalEnd->value] = $end->format('m-d H:i').' ';

            $flightAwareUrl = $event['metadata'][MetadataKey::FlightawareUrl->value] ?? null;
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
        $metadata = $this->normalizeCrewMetadata(
            is_array($event['metadata'] ?? null) ? $event['metadata'] : []
        );
        $routeLine = $this->formatRouteLine($metadata);

        // 1. Header & Type Information
        $lines = [];

        // 2. Separate metadata fields for logical grouping
        $flightDetails = [];
        $crewInfo = [];
        $timings = [];

        foreach ($metadata as $key => $value) {
            // Drop clutter fields that aren't useful in a calendar note
            if (in_array($key, [
                MetadataKey::RawLines->value,
                MetadataKey::FlightawareUrl->value,
                MetadataKey::DutyRawLines->value,
                'crew',
                MetadataKey::CrewCount->value,
                MetadataKey::OperatingCrewCount->value,
                MetadataKey::DeadheadingCrewCount->value,
                MetadataKey::Origin->value,
                MetadataKey::Destination->value,
            ], true)) {
                continue;
            }

            if ($key === MetadataKey::Deadhead->value && ! $value) {
                continue;
            }

            // Clean & safe string conversion
            $stringVal = $this->stringifyMetadataValue($value);
            if ($stringVal === null || $stringVal === '') {
                continue;
            }

            $label = $this->formatMetadataLabel($key);
            $formattedLine = "• {$label}: {$stringVal}";

            // Sort fields into their respective blocks
            if (in_array($key, [MetadataKey::UtcStart->value, MetadataKey::UtcEnd->value], true)) {
                $timings[] = $formattedLine;
            } else {
                $flightDetails[] = "• {$label}: {$stringVal}";
            }
        }

        $crewInfo = $this->formatCrewSection($metadata);

        if ($routeLine !== null) {
            array_unshift($flightDetails, $routeLine);
        }

        // 3. Compile the sections neatly with double line breaks
        if (! empty($flightDetails)) {
            $lines[] = "✈️ FLIGHT DETAILS\n".implode("\n", $flightDetails);
        }

        if (! empty($crewInfo)) {
            $lines[] = "\n👥 CREW LOGISTICS\n".implode("\n", $crewInfo);
        }

        if (! empty($timings)) {
            $lines[] = "\n⏰ TIMES\n".implode("\n", $timings);
        }

        // Return a single clean string. (The parent loop passes this to escapeValue,
        // which converts \n into literal calendar-safe \n syntax)
        return implode("\n", $lines);
    }

    private function formatRouteLine(array $metadata): ?string
    {
        $origin = $metadata[MetadataKey::Origin->value] ?? null;
        $destination = $metadata[MetadataKey::Destination->value] ?? null;

        if (! is_string($origin) || ! is_string($destination) || $origin === '' || $destination === '') {
            return null;
        }

        return "• {$origin} - {$destination}";
    }

    private function formatMetadataLabel(string $key): string
    {
        return str_ireplace('utc', 'UTC', ucfirst(str_replace('_', ' ', $key)));
    }

    private function normalizeCrewMetadata(array $metadata): array
    {
        $crew = is_array($metadata['crew'] ?? null) ? $metadata['crew'] : [];
        $summary = $this->crewParser->summarize($crew);

        if ($crew === [] || $summary['crew_count'] === null) {
            $candidateLines = [];

            foreach ([MetadataKey::DutyRawLines->value, MetadataKey::RawLines->value] as $key) {
                if (is_array($metadata[$key] ?? null)) {
                    $candidateLines = array_merge($candidateLines, $metadata[$key]);
                }
            }

            if ($candidateLines !== []) {
                $parsed = $this->crewParser->parseWithSummary($candidateLines);

                if ($crew === [] && $parsed['crew'] !== []) {
                    $crew = $parsed['crew'];
                }

                if ($summary['crew_count'] === null && $parsed['crew_count'] !== null) {
                    $summary = [
                        'crew_count' => $parsed['crew_count'],
                        'operating_crew_count' => $parsed['operating_crew_count'],
                        'deadheading_crew_count' => $parsed['deadheading_crew_count'],
                    ];
                }
            }
        }

        if ($crew !== []) {
            $metadata['crew'] = $crew;
        }

        foreach ([MetadataKey::CrewCount->value, MetadataKey::OperatingCrewCount->value, MetadataKey::DeadheadingCrewCount->value] as $key) {
            if (($metadata[$key] ?? null) === null && $summary[$key] !== null) {
                $metadata[$key] = $summary[$key];
            }
        }

        return $metadata;
    }

    private function formatCrewSection(array $metadata): array
    {
        $lines = [];

        if (($metadata[MetadataKey::CrewCount->value] ?? null) !== null) {
            $lines[] = '• Crew count: '.$metadata[MetadataKey::CrewCount->value];
        }

        if (($metadata[MetadataKey::OperatingCrewCount->value] ?? null) !== null) {
            $lines[] = '• Operating crew count: '.$metadata[MetadataKey::OperatingCrewCount->value];
        }

        if (($metadata[MetadataKey::DeadheadingCrewCount->value] ?? null) !== null) {
            $lines[] = '• Deadheading crew count: '.$metadata[MetadataKey::DeadheadingCrewCount->value];
        }

        $crew = is_array($metadata['crew'] ?? null) ? $metadata['crew'] : [];

        if ($crew === []) {
            return $lines;
        }

        $lines[] = '• Crew Members:';

        foreach ($crew as $member) {
            if (! is_array($member)) {
                continue;
            }

            $parts = [];
            $name = $member['name'] ?? 'Unknown';
            $role = $member['role'] ?? null;
            $base = $member['base'] ?? null;
            $employeeId = $member['employee_id'] ?? null;
            $deadheading = ($member['deadheading'] ?? false) ? 'DH' : null;

            if ($role) {
                $parts[] = $role;
            }

            if ($base) {
                $parts[] = $base;
            }

            if ($employeeId) {
                $parts[] = '#'.$employeeId;
            }

            if ($deadheading && $role !== 'DH') {
                $parts[] = $deadheading;
            }

            $suffix = $parts === [] ? '' : ' ('.implode(' • ', $parts).')';
            $lines[] = "  └─ {$name}{$suffix}";
        }

        return $lines;
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
