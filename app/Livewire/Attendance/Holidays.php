<?php

namespace App\Livewire\Attendance;

use App\Livewire\Organization\LookupCrud;
use App\Models\Holiday;

class Holidays extends LookupCrud
{
    protected function model(): string
    {
        return Holiday::class;
    }

    protected function heading(): string
    {
        return 'Holidays';
    }

    protected function viewPermission(): string
    {
        return 'shifts.view';
    }

    protected function managePermission(): string
    {
        return 'shifts.manage';
    }

    protected function orderColumn(): string
    {
        return 'date';
    }

    protected function fields(): array
    {
        return [
            'date' => ['label' => 'Date', 'type' => 'date'],
            'name' => ['label' => 'Name'],
            'type' => ['label' => 'Type', 'type' => 'select', 'default' => 'public', 'options' => ['public' => 'Public', 'mercantile' => 'Mercantile', 'company' => 'Company']],
        ];
    }

    protected function fieldRules(): array
    {
        return [
            'date' => ['required', 'date', $this->unique('date')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:public,mercantile,company'],
        ];
    }
}
