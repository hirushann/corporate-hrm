<?php

namespace App\Livewire\Attendance;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Shift roster')]
class Roster extends Component
{
    use AuthorizesPermission, WithPagination;

    public string $department = '';

    public string $search = '';

    public ?int $shiftId = null;

    public string $effectiveFrom = '';

    public ?string $effectiveTo = null;

    public array $selected = [];

    public function mount(): void
    {
        $this->allow('shifts.view');
        $this->effectiveFrom = today()->toDateString();
    }

    public function updated($property): void
    {
        if (in_array($property, ['department', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function assign(): void
    {
        $this->allow('shifts.manage');

        $this->validate([
            'shiftId' => 'required|exists:shifts,id',
            'effectiveFrom' => 'required|date',
            'effectiveTo' => 'nullable|date|after_or_equal:effectiveFrom',
            'selected' => 'required|array|min:1',
            'selected.*' => 'exists:employees,id',
        ], ['selected.required' => 'Tick at least one employee.']);

        foreach ($this->selected as $employeeId) {
            // Close any open-ended assignment that this one replaces.
            ShiftAssignment::where('employee_id', $employeeId)->whereNull('effective_to')
                ->whereDate('effective_from', '<', $this->effectiveFrom)
                ->update(['effective_to' => Carbon::parse($this->effectiveFrom)->subDay()]);

            ShiftAssignment::create([
                'employee_id' => $employeeId, 'shift_id' => $this->shiftId,
                'effective_from' => $this->effectiveFrom, 'effective_to' => $this->effectiveTo ?: null,
            ]);
        }

        $count = count($this->selected);
        $this->reset('selected');
        session()->flash('status', "Shift assigned to {$count} employee(s). Re-process attendance for affected dates to apply it.");
    }

    public function render()
    {
        $employees = Employee::working()
            ->search($this->search)
            ->when($this->department, fn ($q) => $q->where('department_id', $this->department))
            ->with('department')
            ->orderBy('employee_no')
            ->paginate(20);

        $current = ShiftAssignment::with('shift')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereDate('effective_from', '<=', today())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()))
            ->orderBy('effective_from')->get()->keyBy('employee_id');

        return view('livewire.attendance.roster', [
            'employees' => $employees,
            'current' => $current,
            'shifts' => Shift::active()->orderBy('name')->get(),
            'defaultShift' => Shift::where('is_default', true)->first(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'canManage' => auth()->user()->can('shifts.manage'),
        ]);
    }
}
