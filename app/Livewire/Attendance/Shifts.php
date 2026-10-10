<?php

namespace App\Livewire\Attendance;

use App\Livewire\Organization\LookupCrud;
use App\Models\Shift;

class Shifts extends LookupCrud
{
    protected function model(): string
    {
        return Shift::class;
    }

    protected function heading(): string
    {
        return 'Shifts';
    }

    protected function viewPermission(): string
    {
        return 'shifts.view';
    }

    protected function managePermission(): string
    {
        return 'shifts.manage';
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Name'],
            'start_time' => ['label' => 'Start', 'type' => 'time'],
            'end_time' => ['label' => 'End', 'type' => 'time'],
            'break_minutes' => ['label' => 'Break (min)', 'type' => 'number', 'default' => 60],
            'late_grace_minutes' => ['label' => 'Late grace (min)', 'type' => 'number', 'default' => 15],
            'early_grace_minutes' => ['label' => 'Early-leave grace (min)', 'type' => 'number', 'default' => 15],
            'ot_after_minutes' => ['label' => 'OT starts after shift end (min)', 'type' => 'number', 'default' => 30],
            'working_days' => ['label' => 'Working days', 'type' => 'weekdays'],
            'is_active' => ['label' => 'Active', 'type' => 'checkbox'],
        ];
    }

    protected function fieldRules(): array
    {
        $minutes = ['required', 'integer', 'min:0', 'max:600'];

        return [
            'name' => ['required', 'string', 'max:255', $this->unique('name')],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:form.start_time'],
            'break_minutes' => $minutes,
            'late_grace_minutes' => $minutes,
            'early_grace_minutes' => $minutes,
            'ot_after_minutes' => $minutes,
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7'],
            'is_active' => ['boolean'],
        ];
    }
}
