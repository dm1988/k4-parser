<?php

use App\Http\Controllers\ParserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect('/parse'));

Route::get('/parse', [ParserController::class, 'index'])->name('parse.index');
Route::post('/parse/roster', [ParserController::class, 'parseRoster'])->name('parse.roster');
Route::post('/parse/flight', [ParserController::class, 'parseFlight'])->name('parse.flight');
Route::post('/parse/hotel', [ParserController::class, 'parseHotel'])->name('parse.hotel');
