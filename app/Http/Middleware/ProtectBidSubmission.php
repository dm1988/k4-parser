<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectBidSubmission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('crew-bids/submissions*', 'crew-bids/employees*')) {
            return $next($request);
        }
        abort_unless($request->isSecure() || app()->environment('local', 'testing'), 403);
        config(['debugbar.enabled' => false, 'app.debug' => false]);
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }
        try {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Referrer-Policy', 'no-referrer');

            return $response;
        } finally {
            $request->request->remove('employee');
            $request->request->remove('portal_password');
        }
    }
}
