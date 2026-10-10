<?php

namespace App\Livewire\Organization;

use App\Models\EmployeeCategory;

class EmployeeCategories extends LookupCrud
{
    protected function model(): string
    {
        return EmployeeCategory::class;
    }

    protected function heading(): string
    {
        return 'Employee categories';
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Name'],
            'description' => ['label' => 'Description'],
            'is_active' => ['label' => 'Active', 'type' => 'checkbox'],
        ];
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->unique('name')],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
