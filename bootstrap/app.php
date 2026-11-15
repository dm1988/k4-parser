<?php

use App\Http\Middleware\EnsureFeatureIsEnabled;
use App\Http\Middleware\ManageOfflineFuelScore;
use App\Http\Middleware\ProtectBidSubmission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ProtectBidSubmission::class);
        $middleware->trimStrings(except: ['portal_password']);
        $middleware->web(append: [ManageOfflineFuelScore::class]);

        $middleware->alias([
            'feature' => EnsureFeatureIsEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['employee', 'portal_password']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
