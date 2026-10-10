<?php

namespace App\Reports;

use App\Models\User;
use App\Reports\Concerns\UsesPayrollRuns;
use Illuminate\Support\Collection;

class EpfEtfReport extends Report
{
    use UsesPayrollRuns;

    public static function key(): string
    {
        return 'epf-etf';
    }

    public function title(): string
    {
        return 'EPF / ETF contributions';
    }

    public function description(): string
    {
        return 'Member-wise EPF (employee and employer) and ETF contributions for approved or locked payroll.';
    }

    public function permission(): string
    {
        return 'reports.statutory';
    }

    public function filters(): array
    {
        return [$this->runFilter(['approved', 'locked'])];
    }

    public function headings(): array
    {
        return ['EPF No', 'Employee No', 'Name', 'NIC', 'Contribution base', 'EPF employee', 'EPF employer', 'EPF total', 'ETF employer', 'ETF No'];
    }

    public function rows(array $filters, User $user): Collection
    {
        $run = $this->findRun($filters, ['approved', 'locked']);
        if (! $run) {
            return collect();
        }

        return $run->payslips()->with(['lines', 'employee:id,nic'])->orderBy('employee_no')->get()
            ->filter(fn ($p) => $p->lines->whereIn('code', ['EPF_EE', 'EPF_ER', 'ETF_ER'])->isNotEmpty())
            ->map(function ($p) {
                $line = fn (string $code) => (float) $p->lines->where('code', $code)->sum('amount');

                return [
                    (string) $p->epf_number, $p->employee_no, $p->employee_name, (string) $p->employee?->nic,
                    (float) $p->epf_base, $line('EPF_EE'), $line('EPF_ER'), round($line('EPF_EE') + $line('EPF_ER'), 2), $line('ETF_ER'), (string) $p->etf_number,
                ];
            })->values();
    }

    public function totals(Collection $rows): ?array
    {
        return $rows->isEmpty() ? null : ['Total', $rows->count().' members', '', '', ...collect(range(4, 8))->map(fn ($i) => round($rows->sum($i), 2))->all(), ''];
    }
}
