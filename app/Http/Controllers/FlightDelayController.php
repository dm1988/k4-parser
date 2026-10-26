<?php

namespace App\Http\Controllers;

use App\Models\FlightDelay;
use App\Models\User;
use App\Services\FlightPlan\FlightDelayReportFormatter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FlightDelayController extends Controller
{
    public function index(Request $request, FlightDelayReportFormatter $formatter): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $delays = $user->flightDelays()->orderByDesc('updated_at')->orderByDesc('id')->paginate(15);

        return view('flight-release.delay-history', [
            'delays' => $delays,
            'summaries' => $delays->getCollection()->mapWithKeys(
                fn (FlightDelay $delay): array => [$delay->getKey() => [
                    ...$formatter->context($delay),
                    'Saved at (UTC)' => $delay->updated_at?->utc()->format('Y-m-d H:i:s'),
                ]],
            ),
        ]);
    }

    public function show(Request $request, FlightDelay $flightDelay, FlightDelayReportFormatter $formatter): View
    {
        abort_unless($flightDelay->user_id === $request->user()?->getAuthIdentifier(), 404);

        return view('flight-release.delay-detail', [
            'flightDetails' => $formatter->context($flightDelay),
            'reportText' => $formatter->format($flightDelay),
            'savedAt' => $flightDelay->updated_at?->utc()->format('Y-m-d H:i:s'),
        ]);
    }
}
