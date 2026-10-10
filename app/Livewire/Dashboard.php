<?php

namespace App\Livewire;

use App\Models\AttendanceAdjustment;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Notice;
use App\Models\PayrollRun;
use App\Models\Payslip;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        $user = auth()->user();
        $employee = $user->can('portal.use') ? Employee::with('department', 'designation')->where('user_id', $user->id)->first() : null;

        return view('livewire.dashboard', [
            'stats' => $this->stats(),
            'employee' => $employee,
            'notices' => $user->can('notices.view')
                ? Notice::visibleTo($employee?->department_id)->orderByDesc('is_pinned')->latest('published_at')->limit(3)->get()
                : collect(),
            'latestPayslip' => $employee
                ? Payslip::where('employee_id', $employee->id)->whereHas('run', fn ($q) => $q->where('status', 'locked'))->with('run')->latest('id')->first()
                : null,
        ]);
    }

    /** @return array<string, array{label: string, value: int, route: ?string}> */
    private function stats(): array
    {
        $user = auth()->user();
        $stats = [];
        $visible = fn () => Employee::visibleTo($user)->select('id');

        if ($user->can('reports.headcount') || $user->can('employees.view')) {
            $stats['headcount'] = ['label' => 'Employees', 'value' => Employee::visibleTo($user)->working()->count(), 'route' => $user->can('employees.view') ? route('employees.index') : null];
        }
        if ($user->can('attendance.view')) {
            $today = fn (string $status) => AttendanceRecord::whereDate('date', today())->where('status', $status)->whereIn('employee_id', $visible())->count();
            $stats['present'] = ['label' => 'Present today', 'value' => $today('present'), 'route' => route('attendance.index')];
            $stats['absent'] = ['label' => 'Absent today', 'value' => $today('absent'), 'route' => route('attendance.index')];
            $stats['leave'] = ['label' => 'On leave today', 'value' => $today('leave'), 'route' => route('attendance.index')];
        }
        if ($user->can('leave.approve')) {
            $stats['leave_pending'] = ['label' => 'Leave requests to review', 'value' => LeaveRequest::where('status', 'pending')->whereIn('employee_id', $visible())->count(), 'route' => route('leave.requests')];
        }
        if ($user->can('attendance.approve-adjustment')) {
            $stats['adjust_pending'] = ['label' => 'Adjustments to review', 'value' => AttendanceAdjustment::where('status', 'pending')->whereIn('employee_id', $visible())->count(), 'route' => route('attendance.adjustments')];
        }
        if ($user->can('payroll.approve')) {
            $stats['payroll_pending'] = ['label' => 'Payroll awaiting approval', 'value' => PayrollRun::where('status', 'submitted')->count(), 'route' => route('payroll.index')];
        }

        return $stats;
    }
}
