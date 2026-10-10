<?php

namespace App\Reports;

use App\Models\Employee;
use App\Models\EmployeeCategory;
use App\Models\User;
use Illuminate\Support\Collection;

class HeadcountReport extends Report
{
    public static function key(): string
    {
        return 'headcount';
    }

    public function title(): string
    {
        return 'Headcount by department';
    }

    public function description(): string
    {
        return 'Current employees per department, split by gender and employee category.';
    }

    public function permission(): string
    {
        return 'reports.headcount';
    }

    public function headings(): array
    {
        return ['Department', 'Total', 'Male', 'Female', 'Other / not stated', ...EmployeeCategory::orderBy('name')->pluck('name')->all()];
    }

    public function rows(array $filters, User $user): Collection
    {
        $categories = EmployeeCategory::orderBy('name')->get();

        return Employee::visibleTo($user)->working()->with('department')->get()
            ->groupBy(fn ($e) => $e->department->name)->sortKeys()
            ->map(fn ($group, $name) => [
                $name, $group->count(),
                $group->where('gender', 'male')->count(), $group->where('gender', 'female')->count(),
                $group->filter(fn ($e) => ! in_array($e->gender, ['male', 'female'], true))->count(),
                ...$categories->map(fn ($c) => $group->where('employee_category_id', $c->id)->count())->all(),
            ])->values();
    }

    public function totals(Collection $rows): ?array
    {
        return $rows->isEmpty() ? null : ['Total', ...collect(range(1, count($this->headings()) - 1))->map(fn ($i) => $rows->sum($i))->all()];
    }
}
