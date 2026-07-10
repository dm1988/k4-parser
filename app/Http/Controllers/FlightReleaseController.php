<?php

namespace App\Http\Controllers;

use App\Exceptions\FlightRouteNotFoundException;
use App\Http\Requests\StoreFlightReleaseRequest;
use App\Services\FlightRouteExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class FlightReleaseController extends Controller
{
    public function index(): View
    {
        return view('flight-release.index');
    }

    public function store(
        StoreFlightReleaseRequest $request,
        FlightRouteExtractor $extractor,
    ): RedirectResponse {
        $uploadedFile = $request->file('flight_release');
        $path = $uploadedFile->store('flight-releases');

        try {
            $route = $extractor->extractRoute(Storage::disk('local')->path($path));
            $formattedRoute = $extractor->formatForIcaoDisplay($route);
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
            Storage::disk('local')->delete($path);
        }

        return redirect()
            ->route('flight-release.index')
            ->with('flight_route', $formattedRoute);
    }
}
