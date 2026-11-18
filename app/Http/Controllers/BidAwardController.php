<?php

namespace App\Http\Controllers;

use App\Models\BidAwardReport;
use App\Services\Calendar\ExportPayload;
use App\Services\CrewBids\AwardedBidSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BidAwardController extends Controller
{
    public function __construct(private AwardedBidSchedule $schedule, private ExportPayload $export) {}

    public function calendar(Request $request, BidAwardReport $report): Response
    {
        $data = $this->schedule->forUser($report, $request->user());

        return $this->export->exportCalendar($data->result);
    }

    public function event(Request $request, BidAwardReport $report, string $eventId): Response
    {
        $data = $this->schedule->forUser($report, $request->user());

        return $this->export->exportCalendarEvent($data->result, $eventId);
    }
}
