<?php

namespace App\Livewire\Concerns;

use App\Models\Employee;

trait ResolvesEmployee
{
    /** The employee record linked to the signed-in user; self-service never takes an employee ID from the browser. */
    protected function myEmployee(): Employee
    {
        abort_unless(auth()->user()?->can('portal.use'), 403);

        $employee = Employee::with(['department', 'designation', 'branch', 'category', 'manager'])->where('user_id', auth()->id())->first();
        abort_unless($employee, 403, 'No employee profile is linked to your account. Please contact HR.');

        return $employee;
    }
}
