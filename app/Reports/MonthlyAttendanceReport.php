<?php

namespace App\Reports;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MonthlyAttendanceReport extends Report
{
    public static function key(): string
    {
        return 'attendance-monthly';
    }

    public function title(): string
    {
        return 'Monthly attendance summary';
    }

    public function description(): string
    {
        return 'Per employee: days present, absent and on leave, late and early counts, overtime and missing punches.';
    }

    public function permission(): string
    {
        return 'reports.attendance';
    }

    public function filters(): array
    {
        return [
            ['name' => 'month', 'label' => 'Month', 'type' => 'month', 'required' => true, 'default' => today()->format('Y-m')],
            ['name' => 'department', 'label' => 'Department', 'type' => 'select', 'options' => Department::orderBy('name')->pluck('name', 'id')->all()],
        ];
    }

    public function headings(): array
    {
        return ['Employee No', 'Name', 'Department', 'Present', 'Absent', 'Leave', 'Late (days)', 'Late (min)', 'Early (days)', 'Early (min)', 'OT (hours)', 'Missing punches'];
    }

    public function rows(array $filters, User $user): Collection
    {
        $start = Carbon::parse($filters['month'].'-01');
        $range = [$start, $start->copy()->endOfMonth()];

        return Employee::visibleTo($user)
            ->whereHas('attendanceRecords', fn ($q) => $q->whereBetween('date', $range))
            ->when($filters['department'] ?? null, fn ($q, $d) => $q->where('department_id', $d))
            ->with(['department', 'attendanceRecords' => fn ($q) => $q->whereBetween('date', $range)])
            ->orderBy('employee_no')->get()
            ->map(function ($e) {
                $r = $e->attendanceRecords;

                return [
                    $e->employee_no, $e->full_name, $e->department->name,
                    $r->where('status', 'present')->count(),
                    $r->where('status', 'absent')->count(),
                    (float) $r->where('status', 'leave')->sum('leave_fraction'),
                    $r->where('late_minutes', '>', 0)->count(), (int) $r->sum('late_minutes'),
                    $r->where('early_minutes', '>', 0)->count(), (int) $r->sum('early_minutes'),
                    round($r->sum('ot_minutes') / 60, 2),
                    $r->where('missing_punch', true)->count(),
                ];
            });
    }

    public function totals(Collection $rows): ?array
    {
        if ($rows->isEmpty()) {
            return null;
        }

        return ['Total', '', '', ...collect(range(3, 11))->map(fn ($i) => $rows->sum($i))->all()];
    }
}
