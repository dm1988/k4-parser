<?php

namespace App\Services;

use App\DTOs\Flight;
use App\Enums\ParserEventType;
use App\Mappers\FlightMapper;
use Illuminate\Support\Carbon;

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
        $eventType = ParserEventType::fromValue($event['type'] ?? null);
        $metadata = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];
        $description = [
            'Type: '.$eventType->label(),
            'Type description: '.$eventType->description(),
        ];

        foreach ($metadata as $key => $value) {
            if ($key === 'raw_lines' || $key === 'flightaware_url') {
                continue;
            }

            if ($key === 'deadhead' && ! $value) {
                continue;
            }

            if (is_array($value)) {
                $value = implode(', ', array_filter($value, fn ($item) => $item !== null && $item !== ''));
            }

            if ($value === null || $value === '') {
                continue;
            }

            $description[] = ucfirst(str_replace('_', ' ', $key)).': '.$value;
        }

        return implode("\n", $description);
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
