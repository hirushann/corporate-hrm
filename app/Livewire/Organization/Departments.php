<?php

namespace App\Livewire\Organization;

use App\Models\Department;

class Departments extends LookupCrud
{
    protected function model(): string
    {
        return Department::class;
    }

    protected function heading(): string
    {
        return 'Departments';
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Name'],
            'code' => ['label' => 'Code'],
            'is_active' => ['label' => 'Active', 'type' => 'checkbox'],
        ];
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->unique('name')],
            'code' => ['nullable', 'string', 'max:20', $this->unique('code')],
            'is_active' => ['boolean'],
        ];
    }
}
