<?php

namespace App\Livewire\Payroll;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\PayrollSetting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Payroll settings')]
class Settings extends Component
{
    use AuthorizesPermission;

    public array $s = [];

    private const FIELDS = [
        'working_days_divisor' => 'No-pay day rate: basic ÷ this many days',
        'ot_hour_divisor' => 'OT hourly rate: basic ÷ this many hours',
        'ot_weekday_rate' => 'OT multiplier — working days',
        'ot_holiday_rate' => 'OT multiplier — weekends & holidays',
        'epf_employee_rate' => 'EPF employee rate (%)',
        'epf_employer_rate' => 'EPF employer rate (%)',
        'etf_rate' => 'ETF employer rate (%)',
    ];

    public function mount(): void
    {
        $this->allow('payroll.view');
        $this->s = PayrollSetting::current()->only([...array_keys(self::FIELDS), 'epf_includes_allowances']);
    }

    public function save(): void
    {
        $this->allow('payroll.settings');

        $rules = collect(self::FIELDS)->mapWithKeys(fn ($label, $key) => ["s.$key" => 'required|numeric|min:0|max:100000'])->all();
        $rules['s.working_days_divisor'] = 'required|numeric|min:1|max:31';
        $rules['s.ot_hour_divisor'] = 'required|numeric|min:1|max:744';
        $rules['s.epf_employee_rate'] = $rules['s.epf_employer_rate'] = $rules['s.etf_rate'] = 'required|numeric|min:0|max:100';
        $rules['s.epf_includes_allowances'] = 'boolean';

        PayrollSetting::current()->update($this->validate($rules)['s']);
        session()->flash('status', 'Payroll settings saved. They apply to new payroll runs; existing runs keep the rates they were created with.');
    }

    public function render()
    {
        return view('livewire.payroll.settings', ['fields' => self::FIELDS, 'canManage' => auth()->user()->can('payroll.settings')]);
    }
}
