<?php

namespace App\Http\Controllers;

use App\Actions\FlightPlan\BuildFlightPlanPageData;
use App\Models\User;
use App\Services\Infrastructure\FlightPlanResultStore;
use App\Services\Infrastructure\OfflineFuelScoreAssets;
use App\View\Presenters\FlightRelease\FuelPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OfflineFuelScoreController extends Controller
{
    public function __invoke(
        Request $request,
        FlightPlanResultStore $resultStore,
        BuildFlightPlanPageData $buildFlightPlanPageData,
        OfflineFuelScoreAssets $offlineAssets,
        string $flightPlanKey,
    ): Response {
        [$user, $result] = $this->authorizedResult($request, $resultStore, $flightPlanKey);

        $pageData = $buildFlightPlanPageData->handle($result);
        abort_if($pageData === null, 404);
        $assets = $offlineAssets->handle();

        return response()->view('flight-release.offline-fuel-score', [
            'flightNumber' => $pageData->flightPlan->identity->flightNumber,
            'flightDate' => $pageData->flightPlan->identity->flightDate?->format('M j, Y'),
            'calculator' => (new FuelPresenter($pageData))->calculatorData(),
            'draftScope' => [
                'ownerId' => (string) $user->getKey(),
                'flightPlanKey' => $flightPlanKey,
            ],
            'calculatorAssets' => $offlineAssets->tags(),
            'offlineRecovery' => [
                'workerUrl' => asset('offline-fuel-worker.js'),
                'assets' => $assets,
            ],
        ])->header('Cache-Control', 'private, no-store')
            ->header('X-Offline-Fuel-Page', '1')
            ->header('X-Offline-Fuel-Assets', json_encode($assets, JSON_THROW_ON_ERROR));
    }

    public function redirectLegacy(
        Request $request,
        FlightPlanResultStore $resultStore,
        string $flightPlanKey,
    ): RedirectResponse {
        $this->authorizedResult($request, $resultStore, $flightPlanKey);

        return redirect()->route('flight-release.fuel-score', [
            'flightPlanKey' => $flightPlanKey,
        ]);
    }

    /** @return array{User, array<string, mixed>} */
    private function authorizedResult(
        Request $request,
        FlightPlanResultStore $resultStore,
        string $flightPlanKey,
    ): array {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $result = $resultStore->get($user, $flightPlanKey);
        abort_if($result === null, 404);

        return [$user, $result];
    }
}
