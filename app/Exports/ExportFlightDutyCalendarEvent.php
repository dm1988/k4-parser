<?php

namespace App\Exports;

use App\Services\Calendar\FlightDutyEvent;
use App\Services\Calendar\IcsGenerator;

final class ExportFlightDutyCalendarEvent
{
    public function __construct(
        private readonly FlightDutyEvent $flightDutyEvent,
        private readonly IcsGenerator $icsGenerator,
    ) {}

    public function handle(mixed $event, array $trip = []): ?string
    {
        $dutyEvent = $this->flightDutyEvent->buildFromFlight($event);

        if ($dutyEvent === null) {
            return null;
        }

        return $this->icsGenerator->serialize([$dutyEvent], $trip);
    }
}
