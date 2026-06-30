<?php

namespace App\Http\Controllers;

use App\View\Models\FleetTimetableViewModel;
use Illuminate\View\View;

class FleetTimetableController extends Controller
{
    public function __invoke(): View
    {
        return view('fleet-timetable', [
            'viewModel' => FleetTimetableViewModel::make(),
        ]);
    }
}
