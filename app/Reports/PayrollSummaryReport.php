<?php

namespace App\Reports;

use App\Models\User;
use App\Reports\Concerns\UsesPayrollRuns;
use Illuminate\Support\Collection;

class PayrollSummaryReport extends Report
{
    use UsesPayrollRuns;

    public static function key(): string
    {
        return 'payroll-summary';
    }

    public function title(): string
    {
        return 'Payroll summary by department';
    }

    public function description(): string
    {
        return 'Headcount, gross pay, deductions, net pay and statutory contributions per department for a payroll month.';
    }

    public function permission(): string
    {
        return 'reports.payroll';
    }

    public function filters(): array
    {
        return [$this->runFilter(['draft', 'submitted', 'approved', 'locked'])];
    }

    public function headings(): array
    {
        return ['Department', 'Employees', 'Basic (monthly)', 'Gross', 'Total deductions', 'Net pay', 'EPF employee', 'EPF employer', 'ETF employer'];
    }

    public function rows(array $filters, User $user): Collection
    {
        $run = $this->findRun($filters, ['draft', 'submitted', 'approved', 'locked']);
        if (! $run) {
            return collect();
        }

        return $run->payslips()->with('lines')->get()
            ->groupBy(fn ($p) => $p->department ?: 'Unassigned')->sortKeys()
            ->map(function ($group, $department) {
                $line = fn (string $code) => round($group->sum(fn ($p) => (float) $p->lines->where('code', $code)->sum('amount')), 2);

                return [
                    $department, $group->count(),
                    round($group->sum('basic_salary'), 2), round($group->sum('gross'), 2), round($group->sum('total_deductions'), 2), round($group->sum('net_salary'), 2),
                    $line('EPF_EE'), $line('EPF_ER'), $line('ETF_ER'),
                ];
            })->values();
    }

    public function totals(Collection $rows): ?array
    {
        return $rows->isEmpty() ? null : ['Total', $rows->sum(1), ...collect(range(2, 8))->map(fn ($i) => round($rows->sum($i), 2))->all()];
    }
}
