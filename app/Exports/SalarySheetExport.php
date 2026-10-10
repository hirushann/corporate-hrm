<?php

namespace App\Exports;

use App\Models\PayrollRun;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalarySheetExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function __construct(private PayrollRun $run) {}

    public function headings(): array
    {
        return ['Employee No', 'Name', 'Department', 'Designation', 'Basic (monthly)', 'No-pay days', 'OT hours', 'Gross', 'EPF (employee)', 'Total deductions', 'Net salary', 'EPF (employer)', 'ETF (employer)', 'EPF No', 'Bank', 'Account No'];
    }

    public function collection(): Collection
    {
        return $this->run->payslips()->with('lines')->orderBy('employee_no')->get()->map(fn ($p) => [
            $p->employee_no, $p->employee_name, $p->department, $p->designation,
            (float) $p->basic_salary, (float) $p->no_pay_days, round(($p->ot_weekday_minutes + $p->ot_holiday_minutes) / 60, 2),
            (float) $p->gross,
            (float) $p->lines->firstWhere('code', 'EPF_EE')?->amount,
            (float) $p->total_deductions, (float) $p->net_salary,
            (float) $p->lines->firstWhere('code', 'EPF_ER')?->amount,
            (float) $p->lines->firstWhere('code', 'ETF_ER')?->amount,
            $p->epf_number, $p->bank_name, $p->bank_account_number,
        ]);
    }
}
