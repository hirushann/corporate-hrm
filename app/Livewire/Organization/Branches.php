<?php

namespace App\Livewire\Organization;

use App\Models\Branch;
use App\Models\Company;

class Branches extends LookupCrud
{
    protected function model(): string
    {
        return Branch::class;
    }

    protected function heading(): string
    {
        return 'Branches';
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Name'],
            'code' => ['label' => 'Code'],
            'address' => ['label' => 'Address'],
            'phone' => ['label' => 'Phone'],
            'is_active' => ['label' => 'Active', 'type' => 'checkbox'],
        ];
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', $this->unique('code')],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }

    protected function defaults(): array
    {
        return ['company_id' => Company::firstOrCreate([], ['name' => config('app.name')])->id];
    }
}
