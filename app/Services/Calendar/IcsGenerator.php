<?php

namespace App\Services\Calendar;

use App\DTOs\ExtractedEventDTO;
use App\DTOs\Flight;
use App\Enums\MetadataKey;
use App\Mappers\FlightMapper;
use Illuminate\Support\Carbon;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;
use Spatie\IcalendarGenerator\Properties\TextProperty;

class IcsGenerator
{
    public function __construct(
        private readonly FlightMapper $flightMapper,
        private readonly IcsDescriptionFormatter $descriptionFormatter,
    ) {}

    public function serialize(array $events, array $trip = []): string
    {
        $tripNumber = $trip['trip_number'] ?? null;
        $calendarName = filled($tripNumber) ? 'JCA Parsed Trip '.$tripNumber : null;
        $calendar = Calendar::create($calendarName)
            ->description('Calendar export from Crew Compass JCA parser')
            ->productIdentifier('-//Crew Compass//Roster Extractor//EN')
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

            $flightAwareUrl = $event['metadata'][MetadataKey::FlightawareUrl->value] ?? null;
            $description = $this->descriptionFormatter->format($event);
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

        if ($event instanceof ExtractedEventDTO) {
            return $event->toArray();
        }

        return is_array($event) ? $event : null;
    }
}
