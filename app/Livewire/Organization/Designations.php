<?php

namespace App\Livewire\Organization;

use App\Models\Designation;

class Designations extends LookupCrud
{
    protected function model(): string
    {
        return Designation::class;
    }

    protected function heading(): string
    {
        return 'Designations';
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Name'],
            'is_active' => ['label' => 'Active', 'type' => 'checkbox'],
        ];
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->unique('name')],
            'is_active' => ['boolean'],
        ];
    }
}
