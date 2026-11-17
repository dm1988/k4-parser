<?php

use App\Http\Middleware\EnsureFeatureIsEnabled;
use App\Http\Middleware\ManageOfflineFuelScore;
use App\Http\Middleware\ProtectBidSubmission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ProtectBidSubmission::class);
        $middleware->trimStrings(except: ['portal_password', 'app_password']);
        $middleware->web(append: [ManageOfflineFuelScore::class]);

        $middleware->alias([
            'feature' => EnsureFeatureIsEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['employee', 'portal_password', 'app_password']);
        $exceptions->respond(function (Response $response): Response {
            if (request()->is('crew-bids/submissions*', 'crew-bids/employees*', 'crew-bids/connection*', 'password', 'login', 'reset-password')) {
                $response->headers->set('Cache-Control', 'no-store, private');
                $response->headers->set('Referrer-Policy', 'no-referrer');
            }

            return $response;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
