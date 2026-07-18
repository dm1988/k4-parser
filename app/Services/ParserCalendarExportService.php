<?php

namespace App\Services;

use App\DTOs\ParsedEventDTO;
use App\DTOs\ParserResultData;
use App\Enums\MetadataKey;
use App\Enums\ParserEventType;
use App\Exports\ExportFlightDutyCalendarEvent;
use Illuminate\Http\Response;

class ParserCalendarExportService
{
    public function __construct(
        private readonly ExportFlightDutyCalendarEvent $exportFlightDutyCalendarEvent,
        private readonly IcsCalendarService $icsCalendarService,
    ) {}

    /**
     * @param  list<string>  $eventTypes
     */
    public function exportCalendar(ParserResultData $sessionResult, array $eventTypes = []): Response
    {
        $events = $sessionResult->parsed['calendar_events'];

        if ($eventTypes !== []) {
            $events = array_values(array_filter(
                $events,
                fn (mixed $event): bool => in_array($this->eventType($event), $eventTypes, true),
            ));
        }

        if ($events === []) {
            abort(404);
        }

        $trip = is_array($sessionResult->parsed['trip'] ?? null) ? $sessionResult->parsed['trip'] : [];
        $filename = $this->calendarFilename($trip);

        return $this->calendarResponse(
            $this->icsCalendarService->serialize($events, $trip),
            $filename,
        );
    }

    public function exportCalendarEvent(ParserResultData $sessionResult, string $eventId): Response
    {
        $event = $this->findEventOrAbort($sessionResult->parsed['calendar_events'], $eventId);
        $trip = is_array($sessionResult->parsed['trip'] ?? null) ? $sessionResult->parsed['trip'] : [];
        $filename = $this->eventFilename($trip, $eventId);

        return $this->calendarResponse(
            $this->icsCalendarService->serialize([$event], $trip),
            $filename,
        );
    }

    public function exportFlightDutyCalendarEvent(ParserResultData $sessionResult, string $eventId): Response
    {
        $event = $this->findEventOrAbort($sessionResult->parsed['calendar_events'], $eventId);
        $trip = is_array($sessionResult->parsed['trip'] ?? null) ? $sessionResult->parsed['trip'] : [];
        $ics = $this->exportFlightDutyCalendarEvent->handle($event, $trip);

        if ($ics === null) {
            abort(404);
        }

        $filename = $this->dutyEventFilename($trip, $eventId);

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

    /**
     * @param  array<string, mixed>  $trip
     */
    private function calendarFilename(array $trip): string
    {
        return $this->tripFilenamePrefix($trip).'.ics';
    }

    /**
     * @param  array<string, mixed>  $trip
     */
    private function eventFilename(array $trip, string $eventId): string
    {
        return $this->tripFilenamePrefix($trip)."-event-{$eventId}.ics";
    }

    /**
     * @param  array<string, mixed>  $trip
     */
    private function dutyEventFilename(array $trip, string $eventId): string
    {
        return $this->tripFilenamePrefix($trip)."-duty-{$eventId}.ics";
    }

    /**
     * @param  array<string, mixed>  $trip
     */
    private function tripFilenamePrefix(array $trip): string
    {
        $tripNumber = $trip['trip_number'] ?? null;
        $tripSuffix = $tripNumber ? "-{$tripNumber}" : '';

        return "crew-compass{$tripSuffix}";
    }
}
