<?php

namespace App\Livewire;

use App\View\Models\FleetTimetableViewModel;
use Livewire\Attributes\Computed;
use Livewire\Component;

class FleetTimeBoard extends Component
{
    #[Computed]
    public function viewModel(): FleetTimetableViewModel
    {
        return FleetTimetableViewModel::make();
    }

    public function render()
    {
        return view('livewire.fleet-time-board');
    }
}
