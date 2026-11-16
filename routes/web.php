<?php

use App\Enums\FlightPlanTask;
use App\Http\Controllers\CrewBidEmployeeController;
use App\Http\Controllers\CrewBidSubmissionController;
use App\Http\Controllers\CrewBidSyncController;
use App\Http\Controllers\ExtractController;
use App\Http\Controllers\FlightDelayController;
use App\Http\Controllers\FlightReleaseController;
use App\Http\Controllers\OfflineFuelScoreController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WelcomeController;
use App\Livewire\CrewBids;
use App\Livewire\FlightPlanBrief;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('welcome');

Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy.policy');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/crew-bids', CrewBids::class)
        ->middleware(['feature:crew_bids', 'can:use-crew-bids'])
        ->name('crew-bids.index');
    Route::prefix('crew-bids/employees')->name('crew-bids.employees.')
        ->middleware(['feature:crew_bids', 'can:use-crew-bids'])->group(function () {
            Route::get('/package/{package}', [CrewBidEmployeeController::class, 'index'])->middleware('throttle:10,1')->whereNumber('package')->name('index');
            Route::post('/package/{package}', [CrewBidEmployeeController::class, 'read'])->middleware('throttle:10,1')->whereNumber('package')->name('read');
            Route::get('/package/{package}/refresh/{refresh}', [CrewBidEmployeeController::class, 'status'])->middleware('throttle:60,1')->whereNumber('package')->whereUuid('refresh')->name('refresh.status');
            Route::get('/package/{package}/refresh/{refresh}/download', [CrewBidEmployeeController::class, 'download'])->middleware('throttle:10,1')->whereNumber('package')->whereUuid('refresh')->name('refresh.download');
        });
    Route::prefix('crew-bids/submissions')->name('crew-bids.submissions.')
        ->middleware(['feature:crew_bids', 'can:use-crew-bids', 'throttle:10,1'])->group(function () {
            Route::get('/package/{package}/{seat}/sync', [CrewBidSyncController::class, 'create'])->whereNumber('package')->whereIn('seat', ['CA', 'FO'])->name('sync.create');
            Route::post('/package/{package}/{seat}/sync', [CrewBidSyncController::class, 'store'])->whereNumber('package')->whereIn('seat', ['CA', 'FO'])->name('sync.store');
            Route::get('/package/{package}/{seat}', [CrewBidSubmissionController::class, 'create'])->whereNumber('package')->whereIn('seat', ['CA', 'FO'])->name('create');
            Route::post('/package/{package}/{seat}', [CrewBidSubmissionController::class, 'review'])->whereNumber('package')->whereIn('seat', ['CA', 'FO'])->name('review');
            Route::get('/{submission}', [CrewBidSubmissionController::class, 'show'])->whereUlid('submission')->name('show');
            Route::post('/{submission}', [CrewBidSubmissionController::class, 'submit'])->whereUlid('submission')->name('submit');
            Route::post('/{submission}/reconcile', [CrewBidSubmissionController::class, 'reconcile'])->whereUlid('submission')->name('reconcile');
        });
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
        Route::get('/flight-plan-brief/delays', [FlightDelayController::class, 'index'])
            ->name('flight-delays.index');
        Route::get('/flight-plan-brief/delays/{flightDelay}', [FlightDelayController::class, 'show'])
            ->whereNumber('flightDelay')
            ->name('flight-delays.show');
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
