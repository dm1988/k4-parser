<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Infrastructure\FlightPlanResultStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ManageOfflineFuelScore
{
    public function __construct(private FlightPlanResultStore $resultStore) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        $response->headers->set('X-Offline-Fuel-Owner', $user instanceof User ? (string) $user->getKey() : '');

        if ($request->is('livewire/*', 'flight-plan-brief*')) {
            $key = $user instanceof User
                ? $this->resultStore->latest($user)?->result_key
                : null;

            $response->headers->set('X-Offline-Fuel-Key', $key ?? '');
        }

        return $response;
    }
}
