<?php

namespace App\Http\Controllers;

use App\Actions\HandleParseExecution;
use App\DTOs\ParserResultData;
use App\Exceptions\ParseSourceResolutionException;
use App\Http\Requests\ParseRosterRequest;
use App\Services\JcaScheduleParsingService;
use App\Services\ParserCalendarExportService;
use App\Services\ParserResultCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class ParserController extends Controller
{
    public function __construct(
        private readonly HandleParseExecution $handleParseExecution,
        private readonly ParserCalendarExportService $parserCalendarExportService,
        private readonly ParserResultCache $parserResultCache,
        private readonly JcaScheduleParsingService $jcaScheduleParsingService,
    ) {}

    public function index(): View
    {
        return $this->parserPage();
    }

    public function dashboard(): View
    {
        return $this->parserPage();
    }

    private function parserPage(): View
    {
        return view('dashboard');
    }

    public function parseRoster(ParseRosterRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $file = $request->file('file');
        // Use ScheduleDocumentType enum?
        // Consider refactor - relies on validation to fall back to image type
        $sourceType = $file === null
            ? 'pasted_text'
            : ($file->getMimeType() === 'application/pdf' ? 'pdf' : 'image');

        return $this->handleParseAction(
            userId: $request->user()?->id,
            sourceType: $sourceType,
            parserType: $sourceType === 'image' ? 'screenshot' : 'unknown',
            file: $file,
            operation: fn (): array => $this->jcaScheduleParsingService->parseRoster(
                $file,
                $data['text'] ?? null,
                $data['event_types'] ?? [],
            ),
        );
    }

    public function exportCalendar(Request $request): Response
    {
        return $this->parserCalendarExportService->exportCalendar(
            $this->resolveCachedEventsOrAbort($request),
            $request->query('event_types', []),
        );
    }

    public function exportCalendarEvent(Request $request, string $eventId): Response
    {
        return $this->parserCalendarExportService->exportCalendarEvent(
            $this->resolveCachedEventsOrAbort($request),
            $eventId,
        );
    }

    public function exportFlightDutyCalendarEvent(
        Request $request,
        string $eventId,
    ): Response {
        return $this->parserCalendarExportService->exportFlightDutyCalendarEvent(
            $this->resolveCachedEventsOrAbort($request),
            $eventId,
        );
    }

    private function resolveCachedEventsOrAbort(Request $request): ParserResultData
    {
        $sessionResult = $this->parserResultCache->resolveForRequest($request);

        if ($sessionResult === null || ! isset($sessionResult->parsed['calendar_events'])) {
            abort(404);
        }

        return $sessionResult;
    }

    private function handleParseAction(
        ?int $userId,
        string $sourceType,
        string $parserType,
        ?UploadedFile $file,
        callable $operation,
    ): RedirectResponse {
        try {
            $this->handleParseExecution->handle(
                userId: $userId,
                sourceType: $sourceType,
                parserType: $parserType,
                file: $file,
                operation: $operation,
            );
        } catch (ParseSourceResolutionException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return back();
    }
}
