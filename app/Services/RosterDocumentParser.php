<?php

namespace App\Services;

use App\DTOs\Flight;
use App\Mappers\FlightMapper;

class RosterDocumentParser
{
    public function __construct(
        private readonly FlightMapper $flightMapper,
        private readonly RosterParser $tripInformationParser,
        private readonly PublishedRosterParser $publishedRosterParser,
    ) {
    }

    public function parse(string $text, ?string $documentType = null): array
    {
        return match ($documentType) {
            RosterSourceResolver::PDF_TYPE_PUBLISHED_ROSTER => $this->publishedRosterParser->parse($text),
            RosterSourceResolver::PDF_TYPE_TRIP_INFORMATION,
            null => $this->tripInformationParser->parse($text),
            default => $this->tripInformationParser->parse($text),
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
