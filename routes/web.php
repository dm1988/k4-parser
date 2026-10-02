<?php

use App\Enums\FlightPlanTask;
use App\Http\Controllers\ExtractController;
use App\Http\Controllers\FlightReleaseController;
use App\Http\Controllers\OfflineFuelScoreController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WelcomeController;
use App\Livewire\FlightPlanBrief;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('welcome');

Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy.policy');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [ExtractController::class, 'dashboard'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/parse', [ExtractController::class, 'index'])->name('parse.index');

    Route::middleware(['feature:schedule_extractor', 'can:use-schedule-extractor'])->group(function () {
        Route::get('/parse/export', [ExtractController::class, 'exportCalendar'])->name('parse.export');
        Route::get('/parse/export/event/{eventId}', [ExtractController::class, 'exportCalendarEvent'])
            ->name('parse.export.event')
            ->whereAlphaNumeric('eventId');
    });

    Route::get('/parse/export/event/{eventId}/duty', [ExtractController::class, 'exportFlightDutyCalendarEvent'])
        ->name('parse.export.event.duty')
        ->whereAlphaNumeric('eventId')
        ->middleware(['feature:schedule_extractor', 'can:export-schedule-extractor-duty']);

    Route::middleware(['feature:flight_release', 'can:use-flight-release'])->group(function () {
        Route::livewire('/flight-plan-brief', FlightPlanBrief::class)
            ->name('flight-release.index');
        Route::get('/flight-plan-brief/fuel-score/{flightPlanKey}', OfflineFuelScoreController::class)
            ->whereUlid('flightPlanKey')
            ->name('flight-release.fuel-score');
        Route::livewire('/flight-plan-brief/{task}', FlightPlanBrief::class)
            ->whereIn('task', FlightPlanTask::routeSlugs())
            ->name('flight-release.task');

        Route::get('/flight-route-extractor', [FlightReleaseController::class, 'redirectLegacyIndex'])
            ->name('flight-release.legacy.index');
        Route::get('/flight-route-extractor/fuel-score/{flightPlanKey}', [OfflineFuelScoreController::class, 'redirectLegacy'])
            ->whereUlid('flightPlanKey')
            ->name('flight-release.legacy.fuel-score');
        Route::get('/flight-route-extractor/{task}', [FlightReleaseController::class, 'redirectLegacyTask'])
            ->whereIn('task', FlightPlanTask::routeSlugs())
            ->name('flight-release.legacy.task');
    });
});

require __DIR__.'/auth.php';
