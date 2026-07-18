<?php

namespace App\Services;

use App\DTOs\Flight;
use App\DTOs\ParsedEventDTO;
use App\Enums\MetadataKey;
use App\Mappers\FlightMapper;
use Illuminate\Support\Carbon;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;
use Spatie\IcalendarGenerator\Properties\TextProperty;

class IcsCalendarService
{
    public function __construct(
        private readonly FlightMapper $flightMapper,
        private readonly CrewParserService $crewParser,
    ) {}

    public function serialize(array $events, array $trip = []): string
    {
        $tripNumber = $trip['trip_number'] ?? null;
        $calendarName = filled($tripNumber) ? 'JCA Parsed Trip '.$tripNumber : null;
        $calendar = Calendar::create($calendarName)
            ->description('Calendar export from Crew Compass JCA parser')
            ->productIdentifier('-//Crew Compass//Roster Parser//EN')
            ->withoutAutoTimezoneComponents();

        $calendar->appendProperty(TextProperty::create('CALSCALE', 'GREGORIAN'));
        $calendar->appendProperty(TextProperty::create('METHOD', 'PUBLISH'));

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

            $calendarEvent = Event::create($event['title'])
                ->uniqueIdentifier($uid.'@crew-compass')
                ->createdAt(now()->setTimezone('UTC'))
                ->startsAt($start)
                ->endsAt($end)
                ->description($description);

            if (is_string($flightAwareUrl) && $flightAwareUrl !== '') {
                $calendarEvent->url($flightAwareUrl);
            }

            $calendar->event($calendarEvent);
        }

        return $calendar->get()."\r\n";
    }

    private function normalizeEvent(mixed $event): ?array
    {
        if ($event instanceof Flight) {
            return $this->flightMapper->toCalendarEvent($event);
        }

        if ($event instanceof ParsedEventDTO) {
            return $event->toArray();
        }

        return is_array($event) ? $event : null;
    }

    private function formatDescription(array $event): string
    {
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

        foreach ([MetadataKey::CrewCount, MetadataKey::OperatingCrewCount, MetadataKey::DeadheadingCrewCount] as $metaKey) {
            $value = $metadata[$metaKey->value] ?? null;

            if ($value === null) {
                continue;
            }

            $label = $metaKey->metadataLabel() ?? ucfirst(str_replace('_', ' ', $metaKey->value));
            $lines[] = $metaKey->metadataPrefix().$label.$metaKey->metadataSuffix().$value;
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
}
