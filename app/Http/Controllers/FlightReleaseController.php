<?php

namespace App\Http\Controllers;

use App\Actions\ShouldPromptForCoffee;
use App\Exceptions\FlightRouteNotFoundException;
use App\Http\Requests\StoreFlightReleaseRequest;
use App\Models\User;
use App\Services\FlightPlan\Extractor\FlightRouteExtractor;
use App\Services\Infrastructure\ExtractRequestLogger;
use App\View\Models\FlightReleasePageViewModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

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
        ExtractRequestLogger $extractRequestLogger,
        ShouldPromptForCoffee $shouldPromptForCoffee,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $uploadedFile = $request->file('flight_release');
        $disk = Storage::disk('user_flight_releases');
        $path = $uploadedFile->store('', 'user_flight_releases');
        $startedAt = hrtime(true);
        $extractRequest = null;

        try {
            $extractRequest = $extractRequestLogger->start(
                $user->getKey(),
                'pdf',
                'flight_plan',
                $uploadedFile,
            );
            $flightPlan = $extractor->extractFlightPlanData($disk->path($path));
            $flightPlan['route'] = $extractor->formatForIcaoDisplay($flightPlan['route']);

            $extractRequestLogger->complete(
                $extractRequest,
                $startedAt,
                detectedEventCount: 1,
                detectedFlightCount: 1,
                detectedHotelCount: 0,
            );
        } catch (FlightRouteNotFoundException $exception) {
            $extractRequestLogger->error($extractRequest, $startedAt, $exception);

            Log::warning('Flight release route extraction failed', [
                'filename' => $uploadedFile->getClientOriginalName(),
                'mime_type' => $uploadedFile->getMimeType(),
                'size' => $uploadedFile->getSize(),
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('flight-release.index')
                ->withErrors(['flight_release' => $exception->getMessage()]);
        } catch (Throwable $throwable) {
            if ($extractRequest !== null) {
                $extractRequestLogger->error($extractRequest, $startedAt, $throwable);
            }

            throw $throwable;
        } finally {
            $disk->delete($path);
        }

        return redirect()
            ->route('flight-release.index')
            ->with('flight_plan', $flightPlan)
            ->with('show_coffee_prompt', $shouldPromptForCoffee->handle($user));
    }
}
