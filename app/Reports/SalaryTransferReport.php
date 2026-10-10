<?php

namespace App\Reports;

use App\Models\User;
use App\Reports\Concerns\UsesPayrollRuns;
use Illuminate\Support\Collection;

class SalaryTransferReport extends Report
{
    use UsesPayrollRuns;

    public static function key(): string
    {
        return 'salary-transfer';
    }

    public function title(): string
    {
        return 'Salary transfer (bank) list';
    }

    public function description(): string
    {
        return 'Net salary to be paid to each employee with bank details, for approved or locked payroll. Export as CSV for the bank.';
    }

    public function permission(): string
    {
        return 'reports.payroll';
    }

    public function filters(): array
    {
        return [$this->runFilter(['approved', 'locked'])];
    }

    public function headings(): array
    {
        return ['Employee No', 'Name', 'Bank', 'Branch', 'Account No', 'Account Name', 'Net salary', 'Reference'];
    }

    public function rows(array $filters, User $user): Collection
    {
        $run = $this->findRun($filters, ['approved', 'locked']);
        if (! $run) {
            return collect();
        }

        return $run->payslips()->where('net_salary', '>', 0)->orderBy('employee_no')->get()
            ->map(fn ($p) => [
                $p->employee_no, $p->employee_name,
                $p->bank_name ?: 'MISSING BANK DETAILS', (string) $p->bank_branch, (string) $p->bank_account_number,
                $p->bank_account_name ?: $p->employee_name, (float) $p->net_salary, 'SALARY '.strtoupper($run->period_start->format('M Y')),
            ]);
    }

    public function totals(Collection $rows): ?array
    {
        return $rows->isEmpty() ? null : ['Total', $rows->count().' payments', '', '', '', '', round($rows->sum(6), 2), ''];
    }
}
