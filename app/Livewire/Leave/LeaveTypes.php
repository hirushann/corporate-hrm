<?php

namespace App\Livewire\Leave;

use App\Livewire\Organization\LookupCrud;
use App\Models\LeaveType;

class LeaveTypes extends LookupCrud
{
    protected function model(): string
    {
        return LeaveType::class;
    }

    protected function heading(): string
    {
        return 'Leave types';
    }

    protected function viewPermission(): string
    {
        return 'leave.view';
    }

    protected function managePermission(): string
    {
        return 'leave.manage';
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Name'],
            'annual_days' => ['label' => 'Annual days (blank = unlimited)', 'type' => 'number'],
            'carry_forward_max' => ['label' => 'Max carry-forward days', 'type' => 'number', 'default' => 0],
            'is_paid' => ['label' => 'Paid', 'type' => 'checkbox'],
            'is_active' => ['label' => 'Active', 'type' => 'checkbox'],
        ];
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->unique('name')],
            'annual_days' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'carry_forward_max' => ['required', 'numeric', 'min:0', 'max:366'],
            'is_paid' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
