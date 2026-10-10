<?php

namespace App\Livewire;

use App\Models\Employee;
use App\Models\Notice;
use App\Models\Payslip;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        $user = auth()->user();
        $employee = $user->can('portal.use') ? Employee::with('department', 'designation')->where('user_id', $user->id)->first() : null;

        return view('livewire.dashboard', [
            'employee' => $employee,
            'notices' => $user->can('notices.view')
                ? Notice::visibleTo($employee?->department_id)->orderByDesc('is_pinned')->latest('published_at')->limit(3)->get()
                : collect(),
            'latestPayslip' => $employee
                ? Payslip::where('employee_id', $employee->id)->whereHas('run', fn ($q) => $q->where('status', 'locked'))->with('run')->latest('id')->first()
                : null,
        ]);
    }
}
