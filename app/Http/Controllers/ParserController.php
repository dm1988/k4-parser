<?php

namespace App\Http\Controllers;

use App\Actions\ExportFlightDutyCalendarEvent;
use App\Actions\HandleParseExecution;
use App\DTOs\ParsedEventDTO;
use App\Enums\MetadataKey;
use App\Enums\ParserEventType;
use App\Exceptions\ParseSourceResolutionException;
use App\Http\Requests\ParseFlightRequest;
use App\Http\Requests\ParseHotelRequest;
use App\Http\Requests\ParseRosterRequest;
use App\Services\IcsCalendarService;
use App\Services\ParserResultCache;
use App\Services\ScheduleParserService;
use App\View\Models\Parser\ParserPageViewModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ParserController extends Controller
{
    public function __construct(
        private readonly HandleParseExecution $handleParseExecution,
        private readonly IcsCalendarService $icsCalendarService,
        private readonly ParserResultCache $parserResultCache,
        private readonly ScheduleParserService $scheduleParserService,
    ) {}

    public function index()
    {
        return view('parse', [
            'viewModel' => ParserPageViewModel::fromCurrentSession(session()->getOldInput()),
        ]);
    }

    public function parseFlight(ParseFlightRequest $request)
    {
        $text = $request->validated()['text'];

        $this->handleParseExecution->handle(
            userId: $request->user()?->id,
            sourceType: 'pasted_text',
            parserType: 'unknown',
            file: null,
            operation: fn (): array => $this->scheduleParserService->parseFlight($text),
        );

        return back();
    }

    public function parseHotel(ParseHotelRequest $request)
    {
        $text = $request->validated()['text'];

        $this->handleParseExecution->handle(
            userId: $request->user()?->id,
            sourceType: 'pasted_text',
            parserType: 'unknown',
            file: null,
            operation: fn (): array => $this->scheduleParserService->parseHotel($text),
        );

        return back();
    }

    public function parseRoster(ParseRosterRequest $request)
    {
        $data = $request->validated();
        $file = $request->file('file');
        $sourceType = $file === null
            ? 'pasted_text'
            : ($file->getMimeType() === 'application/pdf' ? 'pdf' : 'image');

        try {
            $this->handleParseExecution->handle(
                userId: $request->user()?->id,
                sourceType: $sourceType,
                parserType: $sourceType === 'image' ? 'screenshot' : 'unknown',
                file: $file,
                operation: fn (): array => $this->scheduleParserService->parseRoster(
                    $file,
                    $data['text'] ?? null,
                    $data['event_types'] ?? [],
                ),
            );
        } catch (ParseSourceResolutionException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return back();
    }

    public function exportCalendar(Request $request)
    {
        $this->authorizeScheduleParser($request);

        $sessionResult = $this->resolveCachedEventsOrAbort($request);

        $eventTypes = $request->query('event_types', $sessionResult['filters'] ?? []);
        $events = $sessionResult['parsed']['calendar_events'];

        if ($eventTypes !== []) {
            $events = array_values(array_filter(
                $events,
                fn (mixed $event) => in_array($this->eventType($event), $eventTypes, true),
            ));
        }

        if (count($events) === 0) {
            abort(404);
        }

        $tripNumber = $sessionResult['parsed']['trip']['trip_number'] ?? null;
        $filename = 'crew-compass'.($tripNumber ? "-{$tripNumber}" : '').'.ics';

        return response($this->icsCalendarService->serialize($events, $sessionResult['parsed']['trip'] ?? []), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportCalendarEvent(Request $request, string $eventId)
    {
        $this->authorizeScheduleParser($request);

        $sessionResult = $this->resolveCachedEventsOrAbort($request);

        $event = $this->findEventByDownloadId($sessionResult['parsed']['calendar_events'], $eventId);

        if ($event === null) {
            abort(404);
        }

        $trip = $sessionResult['parsed']['trip'] ?? [];
        $tripNumber = $trip['trip_number'] ?? null;
        $slug = 'event-'.$eventId;
        $filename = 'crew-compass'.($tripNumber ? "-{$tripNumber}" : '').'-'.$slug.'.ics';

        return response($this->icsCalendarService->serialize([$event], $trip), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportFlightDutyCalendarEvent(
        Request $request,
        string $eventId,
        ExportFlightDutyCalendarEvent $exportFlightDutyCalendarEvent,
    ): Response {
        $this->authorizeScheduleParserDutyExport($request);

        $sessionResult = $this->resolveCachedEventsOrAbort($request);

        $event = $this->findEventByDownloadId($sessionResult['parsed']['calendar_events'], $eventId);

        if ($event === null) {
            abort(404);
        }

        $trip = $sessionResult['parsed']['trip'] ?? [];
        $ics = $exportFlightDutyCalendarEvent->handle($event, $trip);

        if ($ics === null) {
            abort(404);
        }

        $tripNumber = $trip['trip_number'] ?? null;
        $filename = 'crew-compass'.($tripNumber ? "-{$tripNumber}" : '').'-duty-'.$eventId.'.ics';

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function resolveCachedEventsOrAbort(Request $request): array
    {
        $sessionResult = $this->parserResultCache->resolveForRequest($request);

        if (! is_array($sessionResult) || ! isset($sessionResult['parsed']['calendar_events'])) {
            abort(404);
        }

        return $sessionResult;
    }

    private function findEventByDownloadId(array $events, string $eventId): mixed
    {
        foreach ($events as $event) {
            // Use abstract parent properties or ArrayAccess fallbacks smoothly
            if ($event instanceof ParsedEventDTO && $event->downloadId === $eventId) {
                return $event;
            }

            if (is_array($event) && ($event[MetadataKey::DownloadId->value] ?? null) === $eventId) {
                return $event;
            }
        }

        return null;
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

    private function authorizeScheduleParser(Request $request): void
    {
        if (! config('features.schedule_parser.enabled', true)) {
            abort(404);
        }

        if (! $request->user()?->canUseScheduleParser()) {
            abort(403);
        }
    }

    private function authorizeScheduleParserDutyExport(Request $request): void
    {
        if (! config('features.schedule_parser.enabled', true)) {
            abort(404);
        }

        if (! $request->user()?->canExportScheduleParserDuty()) {
            abort(403);
        }
    }
}
