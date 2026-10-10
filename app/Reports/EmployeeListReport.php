<?php

namespace App\Reports;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Collection;

class EmployeeListReport extends Report
{
    public static function key(): string
    {
        return 'employee-list';
    }

    public function title(): string
    {
        return 'Employee list';
    }

    public function description(): string
    {
        return 'Employee directory with department, designation, branch, category, join date and status.';
    }

    public function permission(): string
    {
        return 'reports.headcount';
    }

    public function filters(): array
    {
        return [
            ['name' => 'department', 'label' => 'Department', 'type' => 'select', 'options' => Department::orderBy('name')->pluck('name', 'id')->all()],
            ['name' => 'branch', 'label' => 'Branch', 'type' => 'select', 'options' => Branch::orderBy('name')->pluck('name', 'id')->all()],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => array_combine(Employee::STATUSES, array_map('ucfirst', Employee::STATUSES))],
        ];
    }

    public function headings(): array
    {
        return ['Employee No', 'Name', 'NIC', 'Department', 'Designation', 'Branch', 'Category', 'Join date', 'Status', 'Phone'];
    }

    public function rows(array $filters, User $user): Collection
    {
        return Employee::visibleTo($user)->with(['department', 'designation', 'branch', 'category'])
            ->when($filters['department'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($filters['branch'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('employee_no')->get()
            ->map(fn ($e) => [
                $e->employee_no, $e->full_name, (string) $e->nic, $e->department->name, $e->designation->name, $e->branch->name,
                $e->category->name, $e->join_date->format('Y-m-d'), ucfirst($e->status), (string) $e->phone,
            ]);
    }

    public function totals(Collection $rows): ?array
    {
        return $rows->isEmpty() ? null : ['Total', $rows->count().' employees', '', '', '', '', '', '', '', ''];
    }
}
