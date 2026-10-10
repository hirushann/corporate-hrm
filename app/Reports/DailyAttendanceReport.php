<?php

namespace App\Reports;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Collection;

class DailyAttendanceReport extends Report
{
    public static function key(): string
    {
        return 'attendance-daily';
    }

    public function title(): string
    {
        return 'Daily attendance';
    }

    public function description(): string
    {
        return 'Status, in/out times, late, early departure and overtime for every employee on one day.';
    }

    public function permission(): string
    {
        return 'reports.attendance';
    }

    public function filters(): array
    {
        return [
            ['name' => 'date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'default' => today()->toDateString()],
            ['name' => 'department', 'label' => 'Department', 'type' => 'select', 'options' => Department::orderBy('name')->pluck('name', 'id')->all()],
        ];
    }

    public function headings(): array
    {
        return ['Employee No', 'Name', 'Department', 'Status', 'In', 'Out', 'Late (min)', 'Early (min)', 'OT (min)', 'Notes'];
    }

    public function rows(array $filters, User $user): Collection
    {
        $date = $filters['date'];

        return Employee::visibleTo($user)->working()
            ->whereDate('join_date', '<=', $date)
            ->when($filters['department'] ?? null, fn ($q, $d) => $q->where('department_id', $d))
            ->with(['department', 'attendanceRecords' => fn ($q) => $q->whereDate('date', $date)])
            ->orderBy('employee_no')->get()
            ->map(function ($e) {
                $r = $e->attendanceRecords->first();

                return [
                    $e->employee_no, $e->full_name, $e->department->name,
                    $r ? ucfirst($r->status).($r->leave_fraction > 0 && $r->leave_fraction < 1 ? ' (half)' : '') : 'Not processed',
                    $r?->first_in?->format('H:i') ?? '', $r?->last_out?->format('H:i') ?? '',
                    (int) $r?->late_minutes, (int) $r?->early_minutes, (int) $r?->ot_minutes,
                    trim(($r?->missing_punch ? 'Missing punch. ' : '').($r?->is_adjusted ? 'Adjusted.' : '')),
                ];
            });
    }
}
