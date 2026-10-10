<?php

namespace App\Livewire\Reports;

use App\Reports\ReportRegistry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Reports')]
class Index extends Component
{
    public function mount(): void
    {
        abort_if(ReportRegistry::for(auth()->user()) === [], 403);
    }

    public function render()
    {
        return view('livewire.reports.index', ['reports' => ReportRegistry::for(auth()->user())]);
    }
}
