<?php

namespace App\Reports;

use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class JoinersLeaversReport extends Report
{
    public static function key(): string
    {
        return 'joiners-leavers';
    }

    public function title(): string
    {
        return 'Joiners and leavers';
    }

    public function description(): string
    {
        return 'Employees who joined or left during a month.';
    }

    public function permission(): string
    {
        return 'reports.headcount';
    }

    public function filters(): array
    {
        return [['name' => 'month', 'label' => 'Month', 'type' => 'month', 'required' => true, 'default' => today()->format('Y-m')]];
    }

    public function headings(): array
    {
        return ['Movement', 'Employee No', 'Name', 'Department', 'Designation', 'Date'];
    }

    public function rows(array $filters, User $user): Collection
    {
        $start = Carbon::parse($filters['month'].'-01');
        $range = [$start, $start->copy()->endOfMonth()];
        $base = fn () => Employee::visibleTo($user)->with(['department', 'designation']);

        $joined = $base()->whereBetween('join_date', $range)->get()->map(fn ($e) => ['Joined', $e, $e->join_date]);
        $left = $base()->whereBetween('resignation_date', $range)->get()->map(fn ($e) => ['Left', $e, $e->resignation_date]);

        return $joined->concat($left)->sortBy(fn ($r) => $r[2]->timestamp)->values()
            ->map(fn ($r) => [$r[0], $r[1]->employee_no, $r[1]->full_name, $r[1]->department->name, $r[1]->designation->name, $r[2]->format('Y-m-d')]);
    }
}
