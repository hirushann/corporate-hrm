<?php

namespace App\Livewire\Payroll;

use App\Livewire\Organization\LookupCrud;
use App\Models\SalaryComponent;

class SalaryComponents extends LookupCrud
{
    protected function model(): string
    {
        return SalaryComponent::class;
    }

    protected function heading(): string
    {
        return 'Salary components';
    }

    protected function viewPermission(): string
    {
        return 'payroll.view';
    }

    protected function managePermission(): string
    {
        return 'payroll.settings';
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Name'],
            'type' => ['label' => 'Type', 'type' => 'select', 'default' => 'earning', 'options' => SalaryComponent::TYPES],
            'epf_applicable' => ['label' => 'Counts towards EPF/ETF (earnings only)', 'type' => 'checkbox'],
            'is_active' => ['label' => 'Active', 'type' => 'checkbox'],
        ];
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->unique('name')],
            'type' => ['required', 'in:earning,deduction'],
            'epf_applicable' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
