<?php

use App\Http\Controllers\FlightReleaseController;
use App\Http\Controllers\ParserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy.policy');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [ParserController::class, 'dashboard'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/parse', [ParserController::class, 'index'])->name('parse.index');

    Route::middleware(['feature:schedule_parser', 'can:use-schedule-parser'])->group(function () {
        Route::post('/parse/roster', [ParserController::class, 'parseRoster'])->name('parse.roster');
        Route::get('/parse/export', [ParserController::class, 'exportCalendar'])->name('parse.export');
        Route::get('/parse/export/event/{eventId}', [ParserController::class, 'exportCalendarEvent'])
            ->name('parse.export.event')
            ->whereAlphaNumeric('eventId');
        Route::post('/parse/flight', [ParserController::class, 'parseFlight'])->name('parse.flight');
        Route::post('/parse/hotel', [ParserController::class, 'parseHotel'])->name('parse.hotel');
    });

    Route::get('/parse/export/event/{eventId}/duty', [ParserController::class, 'exportFlightDutyCalendarEvent'])
        ->name('parse.export.event.duty')
        ->whereAlphaNumeric('eventId')
        ->middleware(['feature:schedule_parser', 'can:export-schedule-parser-duty']);

    Route::middleware(['feature:flight_release', 'can:use-flight-release'])->group(function () {
        Route::get('/flight-route-extractor', [FlightReleaseController::class, 'index'])->name('flight-release.index');
        Route::post('/flight-route-extractor', [FlightReleaseController::class, 'store'])->name('flight-release.store');
    });
});

require __DIR__.'/auth.php';
