<?php

namespace App\Http\Controllers;

use App\DTOs\ParserResultData;
use App\Services\Calendar\ExportPayload;
use App\Services\Infrastructure\EngineResultCache;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ParserController extends Controller
{
    public function __construct(
        private readonly ExportPayload $exportPayload,
        private readonly EngineResultCache $engineResultCache,
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

    public function exportCalendar(Request $request): Response
    {
        return $this->exportPayload->exportCalendar(
            $this->resolveCachedEventsOrAbort($request),
            $request->query('event_types', []),
        );
    }

    public function exportCalendarEvent(Request $request, string $eventId): Response
    {
        return $this->exportPayload->exportCalendarEvent(
            $this->resolveCachedEventsOrAbort($request),
            $eventId,
        );
    }

    public function exportFlightDutyCalendarEvent(
        Request $request,
        string $eventId,
    ): Response {
        return $this->exportPayload->exportFlightDutyCalendarEvent(
            $this->resolveCachedEventsOrAbort($request),
            $eventId,
        );
    }

    private function resolveCachedEventsOrAbort(Request $request): ParserResultData
    {
        $sessionResult = $this->engineResultCache->resolveForRequest($request);

        if ($sessionResult === null || ! isset($sessionResult->parsed['calendar_events'])) {
            abort(404);
        }

        return $sessionResult;
    }
}
