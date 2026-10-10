<?php

namespace App\Livewire\Payroll;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\PayrollRun;
use App\Services\PayrollService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Payroll')]
class Index extends Component
{
    use AuthorizesPermission;

    public string $period = '';

    public function mount(): void
    {
        $this->allow('payroll.view');
        $this->period = today()->subMonthNoOverflow()->format('Y-m');
    }

    public function create(PayrollService $payroll)
    {
        $this->allow('payroll.process');
        $this->validate(['period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);

        $run = $payroll->createRun($this->period, auth()->user());

        return $this->redirectRoute('payroll.show', $run, navigate: true);
    }

    public function render()
    {
        return view('livewire.payroll.index', [
            'runs' => PayrollRun::withCount('payslips')->withSum('payslips', 'net_salary')->orderByDesc('period')->get(),
            'canProcess' => auth()->user()->can('payroll.process'),
        ]);
    }
}
