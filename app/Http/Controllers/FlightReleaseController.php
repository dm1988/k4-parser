<?php

namespace App\Http\Controllers;

use App\Enums\FlightPlanTask;
use Illuminate\Http\RedirectResponse;

class FlightReleaseController extends Controller
{
    public function redirectLegacyIndex(): RedirectResponse
    {
        return redirect()->route('flight-release.index');
    }

    public function redirectLegacyTask(string $task): RedirectResponse
    {
        $selectedTask = FlightPlanTask::fromRouteSlug($task);
        abort_if($selectedTask === null, 404);

        return redirect()->route('flight-release.task', [
            'task' => $selectedTask->routeSlug(),
        ]);
    }
}
