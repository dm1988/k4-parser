<?php

use App\Http\Controllers\ParserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/parse', [ParserController::class, 'index'])->name('parse.index');
    Route::post('/parse/roster', [ParserController::class, 'parseRoster'])->name('parse.roster');
    Route::get('/parse/export', [ParserController::class, 'exportCalendar'])->name('parse.export');
    Route::get('/parse/export/event/{eventIndex}', [ParserController::class, 'exportCalendarEvent'])
        ->name('parse.export.event')
        ->whereNumber('eventIndex');
    Route::post('/parse/flight', [ParserController::class, 'parseFlight'])->name('parse.flight');
    Route::post('/parse/hotel', [ParserController::class, 'parseHotel'])->name('parse.hotel');
});

require __DIR__.'/auth.php';
