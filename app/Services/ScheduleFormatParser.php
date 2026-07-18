<?php

namespace App\Services;

use App\DTOs\Flight;
use App\Enums\ScheduleDocumentType;
use App\Mappers\FlightMapper;

class ScheduleFormatParser
{
    public function __construct(
        private readonly FlightMapper $flightMapper,
        private readonly TripInformationParser $tripInformationParser,
        private readonly PublishedRosterParser $publishedRosterParser,
    ) {}

    public function parse(string $text, ?string $documentType = null): array
    {
        return match (ScheduleDocumentType::tryFrom((string) $documentType)) {
            ScheduleDocumentType::PublishedRoster => $this->publishedRosterParser->parse($text),
            ScheduleDocumentType::TripInformation,
            null => $this->tripInformationParser->parse($text),
        };
    }

    /**
     * @return list<Flight>
     */
    public function extractFlightsDto(string $text, ?string $documentType = null): array
    {
        $flights = [];

        foreach ($this->parse($text, $documentType)['calendar_events'] ?? [] as $event) {
            if (! is_array($event)) {
                continue;
            }

            $flight = $this->flightMapper->fromCalendarEvent($event);

            if ($flight !== null) {
                $flights[] = $flight;
            }
        }

        return $flights;
    }
}
