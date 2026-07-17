<?php

namespace App\Exports;

use App\Services\FlightDutyCalendarEventService;
use App\Services\IcsCalendarService;

final class ExportFlightDutyCalendarEvent
{
    public function __construct(
        private readonly FlightDutyCalendarEventService $flightDutyCalendarEventService,
        private readonly IcsCalendarService $icsCalendarService,
    ) {}

    public function handle(mixed $event, array $trip = []): ?string
    {
        $dutyEvent = $this->flightDutyCalendarEventService->buildFromFlight($event);

        if ($dutyEvent === null) {
            return null;
        }

        return $this->icsCalendarService->serialize([$dutyEvent], $trip);
    }
}
