<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WelcomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('welcome', [
            'scheduleAction' => $this->featureAction(
                $user,
                (bool) config('features.schedule_extractor.enabled', true),
                $user?->canUseScheduleExtractor() ?? false,
                'parse.index',
                'Schedule Extractor',
            ),
            'flightPlanAction' => $this->featureAction(
                $user,
                (bool) config('features.flight_release.enabled', true),
                $user?->canUseFlightRelease() ?? false,
                'flight-release.index',
                'Flight Plan Brief',
            ),
        ]);
    }

    /** @return array{url: ?string, label: string} */
    private function featureAction(?User $user, bool $enabled, bool $canUse, string $route, string $name): array
    {
        if (! $enabled) {
            return ['url' => null, 'label' => 'Temporarily unavailable'];
        }

        if ($user === null) {
            return ['url' => route('login'), 'label' => "Log in for {$name}"];
        }

        if ($canUse) {
            return ['url' => route($route), 'label' => "Open {$name}"];
        }

        if (! $user->hasVerifiedEmail()) {
            return ['url' => route('verification.notice'), 'label' => "Verify email for {$name}"];
        }

        return ['url' => null, 'label' => 'Not available for your account'];
    }
}
