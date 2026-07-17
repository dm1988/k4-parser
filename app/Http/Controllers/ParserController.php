<?php

namespace App\Http\Controllers;

use App\Actions\HandleParseExecution;
use App\Exceptions\ParseSourceResolutionException;
use App\Http\Requests\ParseFlightRequest;
use App\Http\Requests\ParseHotelRequest;
use App\Http\Requests\ParseRosterRequest;
use App\Services\ParserCalendarExportService;
use App\Services\ParserResultCache;
use App\Services\ScheduleParserService;
use App\View\Models\Parser\ParserPageViewModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ParserController extends Controller
{
    public function __construct(
        private readonly HandleParseExecution $handleParseExecution,
        private readonly ParserCalendarExportService $parserCalendarExportService,
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

        return $this->parserCalendarExportService->exportCalendar(
            $this->resolveCachedEventsOrAbort($request),
            $request->query('event_types', []),
        );
    }

    public function exportCalendarEvent(Request $request, string $eventId)
    {
        $this->authorizeScheduleParser($request);

        return $this->parserCalendarExportService->exportCalendarEvent(
            $this->resolveCachedEventsOrAbort($request),
            $eventId,
        );
    }

    public function exportFlightDutyCalendarEvent(
        Request $request,
        string $eventId,
    ): Response {
        $this->authorizeScheduleParserDutyExport($request);

        return $this->parserCalendarExportService->exportFlightDutyCalendarEvent(
            $this->resolveCachedEventsOrAbort($request),
            $eventId,
        );
    }

    private function resolveCachedEventsOrAbort(Request $request): array
    {
        $sessionResult = $this->parserResultCache->resolveForRequest($request);

        if (! is_array($sessionResult) || ! isset($sessionResult['parsed']['calendar_events'])) {
            abort(404);
        }

        return $sessionResult;
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
