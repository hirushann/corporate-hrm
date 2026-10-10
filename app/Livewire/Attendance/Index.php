<?php

namespace App\Livewire\Attendance;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Department;
use App\Models\Employee;
use App\Services\AttendanceProcessor;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Attendance')]
class Index extends Component
{
    use AuthorizesPermission, WithPagination;

    #[Url]
    public string $tab = 'daily';

    #[Url]
    public string $date = '';

    #[Url]
    public string $month = '';

    #[Url]
    public ?int $employeeId = null;

    public string $search = '';

    public string $department = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->allow('attendance.view');
        $this->date = $this->date ?: today()->toDateString();
        $this->month = $this->month ?: today()->format('Y-m');
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'department', 'statusFilter', 'date'], true)) {
            $this->resetPage();
        }
    }

    public function processDate(AttendanceProcessor $processor): void
    {
        $this->allow('attendance.manage');
        $this->validate(['date' => 'required|date']);

        $count = $processor->processRange(Carbon::parse($this->date), Carbon::parse($this->date));
        session()->flash('status', "Processed {$count} attendance records for {$this->date}.");
    }

    private function visibleEmployees()
    {
        return Employee::query()->visibleTo(auth()->user());
    }

    public function render()
    {
        $this->allow('attendance.view');

        $data = ['departments' => Department::orderBy('name')->get(['id', 'name']), 'canProcess' => auth()->user()->can('attendance.manage')];

        if ($this->tab === 'monthly') {
            $employees = $this->visibleEmployees()->orderBy('full_name')->get(['id', 'employee_no', 'full_name']);
            $employee = $employees->firstWhere('id', $this->employeeId);
            $records = collect();
            $totals = null;

            if ($employee) {
                $start = Carbon::parse($this->month.'-01');
                $records = $employee->attendanceRecords()->whereBetween('date', [$start, $start->copy()->endOfMonth()])->orderBy('date')->get();
                $totals = [
                    'present' => $records->where('status', 'present')->count(),
                    'absent' => $records->where('status', 'absent')->count(),
                    'leave' => $records->where('status', 'leave')->sum('leave_fraction'),
                    'late' => $records->where('late_minutes', '>', 0)->count(),
                    'late_minutes' => $records->sum('late_minutes'),
                    'early' => $records->where('early_minutes', '>', 0)->count(),
                    'ot_minutes' => $records->sum('ot_minutes'),
                ];
            }

            return view('livewire.attendance.index', $data + compact('employees', 'employee', 'records', 'totals'));
        }

        $date = Carbon::parse($this->date);
        $rows = $this->visibleEmployees()->working()
            ->whereDate('join_date', '<=', $date)
            ->search($this->search)
            ->when($this->department, fn ($q) => $q->where('department_id', $this->department))
            ->with(['department', 'attendanceRecords' => fn ($q) => $q->whereDate('date', $date)])
            ->when($this->statusFilter, function ($q) use ($date) {
                $filter = $this->statusFilter;
                $q->whereHas('attendanceRecords', function ($r) use ($date, $filter) {
                    $r->whereDate('date', $date);
                    match ($filter) {
                        'late' => $r->where('late_minutes', '>', 0),
                        'early' => $r->where('early_minutes', '>', 0),
                        'ot' => $r->where('ot_minutes', '>', 0),
                        'missing' => $r->where('missing_punch', true),
                        default => $r->where('status', $filter),
                    };
                });
            })
            ->orderBy('employee_no')
            ->paginate(25);

        return view('livewire.attendance.index', $data + ['rows' => $rows]);
    }
}
