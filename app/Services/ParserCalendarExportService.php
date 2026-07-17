<?php

namespace App\Services;

use App\Actions\ExportFlightDutyCalendarEvent;
use App\DTOs\ParsedEventDTO;
use App\Enums\MetadataKey;
use App\Enums\ParserEventType;
use Illuminate\Http\Response;

class ParserCalendarExportService
{
    public function __construct(
        private readonly ExportFlightDutyCalendarEvent $exportFlightDutyCalendarEvent,
        private readonly IcsCalendarService $icsCalendarService,
    ) {}

    /**
     * @param  array<string, mixed>  $sessionResult
     * @param  list<string>  $eventTypes
     */
    public function exportCalendar(array $sessionResult, array $eventTypes = []): Response
    {
        $events = $sessionResult['parsed']['calendar_events'];

        if ($eventTypes !== []) {
            $events = array_values(array_filter(
                $events,
                fn (mixed $event): bool => in_array($this->eventType($event), $eventTypes, true),
            ));
        }

        if ($events === []) {
            abort(404);
        }

        $trip = is_array($sessionResult['parsed']['trip'] ?? null) ? $sessionResult['parsed']['trip'] : [];
        $tripNumber = $trip['trip_number'] ?? null;
        $filename = 'crew-compass'.($tripNumber ? "-{$tripNumber}" : '').'.ics';

        return $this->calendarResponse(
            $this->icsCalendarService->serialize($events, $trip),
            $filename,
        );
    }

    /**
     * @param  array<string, mixed>  $sessionResult
     */
    public function exportCalendarEvent(array $sessionResult, string $eventId): Response
    {
        $event = $this->findEventOrAbort($sessionResult['parsed']['calendar_events'], $eventId);
        $trip = is_array($sessionResult['parsed']['trip'] ?? null) ? $sessionResult['parsed']['trip'] : [];
        $tripNumber = $trip['trip_number'] ?? null;
        $filename = 'crew-compass'.($tripNumber ? "-{$tripNumber}" : '')."-event-{$eventId}.ics";

        return $this->calendarResponse(
            $this->icsCalendarService->serialize([$event], $trip),
            $filename,
        );
    }

    /**
     * @param  array<string, mixed>  $sessionResult
     */
    public function exportFlightDutyCalendarEvent(array $sessionResult, string $eventId): Response
    {
        $event = $this->findEventOrAbort($sessionResult['parsed']['calendar_events'], $eventId);
        $trip = is_array($sessionResult['parsed']['trip'] ?? null) ? $sessionResult['parsed']['trip'] : [];
        $ics = $this->exportFlightDutyCalendarEvent->handle($event, $trip);

        if ($ics === null) {
            abort(404);
        }

        $tripNumber = $trip['trip_number'] ?? null;
        $filename = 'crew-compass'.($tripNumber ? "-{$tripNumber}" : '')."-duty-{$eventId}.ics";

        return $this->calendarResponse($ics, $filename);
    }

    /**
     * @param  array<int, mixed>  $events
     */
    private function findEventOrAbort(array $events, string $eventId): mixed
    {
        foreach ($events as $event) {
            if ($event instanceof ParsedEventDTO && $event->downloadId === $eventId) {
                return $event;
            }

            if (is_array($event) && ($event[MetadataKey::DownloadId->value] ?? null) === $eventId) {
                return $event;
            }
        }

        abort(404);
    }

    private function eventType(mixed $event): string
    {
        $eventType = $event instanceof ParsedEventDTO
            ? ParserEventType::fromValue($event->type)
            : (is_array($event) ? ParserEventType::fromEvent($event) : ParserEventType::Unknown);

        if ($eventType->isFlightLike()) {
            return ParserEventType::Flight->value;
        }

        if ($event instanceof ParsedEventDTO) {
            return $event->type;
        }

        return is_array($event) ? (string) ($event['type'] ?? '') : '';
    }

    private function calendarResponse(string $contents, string $filename): Response
    {
        return response($contents, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
