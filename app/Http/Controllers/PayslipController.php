<?php

namespace App\Http\Controllers;

use App\Exports\SalarySheetExport;
use App\Models\Company;
use App\Models\PayrollRun;
use App\Models\Payslip;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class PayslipController extends Controller
{
    /** Payroll staff may open any payslip; an employee only their own, and only once the run is locked. */
    public function pdf(Payslip $payslip)
    {
        $user = auth()->user();
        $payslip->load(['run', 'lines', 'employee']);

        $isOwn = $payslip->employee?->user_id === $user->id && $payslip->run->status === 'locked';
        abort_unless($user->can('payroll.view') || $isOwn, 403);

        activity()->performedOn($payslip->run)->causedBy($user)->withProperties(['employee' => $payslip->employee_no])->log('payslip downloaded');

        return Pdf::loadView('payslips.pdf', ['payslip' => $payslip, 'company' => Company::first()])
            ->download("payslip-{$payslip->employee_no}-{$payslip->run->period}.pdf");
    }

    public function salarySheet(PayrollRun $run)
    {
        abort_unless(auth()->user()->can('payroll.view'), 403);

        return Excel::download(new SalarySheetExport($run), "salary-sheet-{$run->period}.xlsx");
    }
}
