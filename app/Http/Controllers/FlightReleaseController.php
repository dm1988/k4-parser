<?php

namespace App\Http\Controllers;

use App\Exceptions\FlightRouteNotFoundException;
use App\Http\Requests\StoreFlightReleaseRequest;
use App\Services\FlightRouteExtractor;
use App\View\Models\FlightReleasePageViewModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class FlightReleaseController extends Controller
{
    public function index(): View
    {
        return view('flight-release.index', [
            'model' => FlightReleasePageViewModel::fromCurrentSession(),
        ]);
    }

    public function store(
        StoreFlightReleaseRequest $request,
        FlightRouteExtractor $extractor,
    ): RedirectResponse {
        $uploadedFile = $request->file('flight_release');
        $disk = Storage::disk('user_flight_releases');
        $path = $uploadedFile->store('', 'user_flight_releases');

        try {
            $flightPlan = $extractor->extractFlightPlanData($disk->path($path));
            $flightPlan['route'] = $extractor->formatForIcaoDisplay($flightPlan['route']);
        } catch (FlightRouteNotFoundException $exception) {
            Log::warning('Flight release route extraction failed', [
                'filename' => $uploadedFile->getClientOriginalName(),
                'mime_type' => $uploadedFile->getMimeType(),
                'size' => $uploadedFile->getSize(),
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('flight-release.index')
                ->withErrors(['flight_release' => $exception->getMessage()]);
        } finally {
            $disk->delete($path);
        }

        return redirect()
            ->route('flight-release.index')
            ->with('flight_plan', $flightPlan);
    }
}
