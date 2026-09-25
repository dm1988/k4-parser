<?php

namespace App\Http\Controllers;

use App\Actions\FlightPlan\BuildFlightPlanPageData;
use App\Models\User;
use App\Services\Infrastructure\FlightPlanResultStore;
use App\View\Presenters\FlightRelease\FuelPresenter;
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
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $result = $resultStore->get($user, $flightPlanKey);
        abort_if($result === null, 404);

        $pageData = $buildFlightPlanPageData->handle($result);
        abort_if($pageData === null, 404);

        return view('flight-release.offline-fuel-score', [
            'flightNumber' => $pageData->flightPlan->identity->flightNumber,
            'flightDate' => $pageData->flightPlan->identity->flightDate?->format('M j, Y'),
            'calculator' => (new FuelPresenter($pageData))->calculatorData(),
        ]);
    }
}
