<?php

namespace App\Http\Middleware;

use App\Exceptions\BidPortalException;
use App\Services\CrewBids\CrewBidOperationGuard;
use App\Services\CrewBids\CrewBidVaultService;
use Closure;
use Illuminate\Http\Request;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Response;

class UseSavedCrewBidCredentials
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(#[SensitiveParameter] Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST')) {
            return $next($request);
        }
        $guard = app(CrewBidOperationGuard::class);
        try {
            $user = $request->user()->fresh();
            $lease = null;
            if ($request->boolean('use_saved_credentials')) {
                $credentials = app(CrewBidVaultService::class)->credentials($user, $request->session());
                $request->merge(['employee' => $credentials['employee'], 'portal_password' => $credentials['password']]);
                unset($credentials);
                $lease = $request->session()->get('crew_bid_vault_handle');
            }
            $guard->begin($user, $request->session(), $lease);

            return $next($request);
        } catch (BidPortalException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }
            $key = $request->routeIs('crew-bids.submissions.sync.*') ? 'crew_bid_import_error'
                : ($request->is('crew-bids/submissions*') ? 'bid_submission_error' : 'crew_bid_sync_error');

            return back()->with($key, $exception->getMessage());
        } finally {
            $guard->clear();
            $request->request->remove('employee');
            $request->request->remove('portal_password');
        }
    }
}
