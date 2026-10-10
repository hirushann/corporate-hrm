<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\ResolvesEmployee;
use App\Models\Payslip;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('My payslips')]
class Payslips extends Component
{
    use ResolvesEmployee;

    public function render()
    {
        $employee = $this->myEmployee();

        return view('livewire.portal.payslips', [
            // Only payslips from locked (published) runs are ever shown to the employee.
            'payslips' => Payslip::with('run')->where('employee_id', $employee->id)
                ->whereHas('run', fn ($q) => $q->where('status', 'locked'))
                ->get()->sortByDesc(fn ($p) => $p->run->period)->values(),
        ]);
    }
}
