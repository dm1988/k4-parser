<?php

namespace App\Http\Controllers;

use App\Actions\FlightPlan\BuildFlightPlanPageData;
use App\Models\User;
use App\Services\Infrastructure\FlightPlanResultStore;
use App\View\Presenters\FlightRelease\FuelPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfflineFuelScoreController extends Controller
{
    public function __invoke(
        Request $request,
        FlightPlanResultStore $resultStore,
        BuildFlightPlanPageData $buildFlightPlanPageData,
        string $flightPlanKey,
    ): View {
        [$user, $result] = $this->authorizedResult($request, $resultStore, $flightPlanKey);

        $pageData = $buildFlightPlanPageData->handle($result);
        abort_if($pageData === null, 404);

        return view('flight-release.offline-fuel-score', [
            'flightNumber' => $pageData->flightPlan->identity->flightNumber,
            'flightDate' => $pageData->flightPlan->identity->flightDate?->format('M j, Y'),
            'calculator' => (new FuelPresenter($pageData))->calculatorData(),
            'draftScope' => [
                'ownerId' => (string) $user->getKey(),
                'flightPlanKey' => $flightPlanKey,
            ],
        ]);
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
