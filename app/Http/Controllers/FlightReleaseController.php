<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class FlightReleaseController extends Controller
{
    public function index(): View
    {
        return view('flight-release.index');
    }
}
