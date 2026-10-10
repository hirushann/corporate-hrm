<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\PayrollSetting;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public function __construct(private AttendanceProcessor $attendance, private PayrollCalculator $calculator) {}

    /** @throws ValidationException */
    public function createRun(string $period, User $by): PayrollRun
    {
        $start = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();

        if ($end->isFuture() && ! $end->isToday()) {
            throw ValidationException::withMessages(['period' => 'That month has not ended yet.']);
        }
        if (PayrollRun::where('period', $period)->exists()) {
            throw ValidationException::withMessages(['period' => 'A payroll run for that month already exists.']);
        }

        $run = PayrollRun::create([
            'period' => $period, 'period_start' => $start, 'period_end' => $end, 'status' => 'draft',
            'settings' => PayrollSetting::current()->only([
                'working_days_divisor', 'ot_hour_divisor', 'ot_weekday_rate', 'ot_holiday_rate',
                'epf_employee_rate', 'epf_employer_rate', 'etf_rate', 'epf_includes_allowances',
            ]),
            'prepared_by' => $by->id,
        ]);

        $this->generate($run);
        activity()->performedOn($run)->causedBy($by)->log('payroll run created');

        return $run;
    }

    /** (Re)build all payslips of a draft run from attendance, leave and salary data. Manual lines are kept. */
    public function generate(PayrollRun $run): void
    {
        abort_unless($run->isEditable(), 422, 'Only draft runs can be recalculated.');

        $start = $run->period_start;
        $end = $run->period_end;
        $workingStatuses = ['active', 'probation', 'suspended'];

        $employees = Employee::query()
            ->with(['department', 'designation'])
            ->whereDate('join_date', '<=', $end)
            ->where(fn ($q) => $q
                ->whereDate('resignation_date', '>=', $start)
                ->orWhere(fn ($q) => $q->whereNull('resignation_date')->whereIn('status', $workingStatuses)))
            ->orderBy('employee_no')->get();

        // Make sure attendance reflects the latest punches, leave and adjustments.
        $this->attendance->processRange($start, $end, $employees);

        $warnings = [];
        $kept = [];

        DB::transaction(function () use ($run, $employees, $start, $end, &$warnings, &$kept) {
            foreach ($employees as $employee) {
                $records = $employee->attendanceRecords()->whereBetween('date', [$start, $end])->get();
                $leaves = LeaveRequest::with('leaveType')->where('employee_id', $employee->id)->where('status', 'approved')
                    ->whereDate('from_date', '<=', $end)->whereDate('to_date', '>=', $start)->get();

                $result = $this->calculator->calculate($employee, $start, $end, $run->settings, $records, $leaves);
                if (! $result) {
                    $warnings[] = "{$employee->employee_no} {$employee->full_name}: no salary on file, skipped.";

                    continue;
                }
                array_push($warnings, ...$result['warnings']);

                $payslip = Payslip::updateOrCreate(['payroll_run_id' => $run->id, 'employee_id' => $employee->id], $result['payslip']);
                $payslip->lines()->where('is_manual', false)->delete();
                foreach ($result['lines'] as $line) {
                    $payslip->lines()->create($line);
                }
                $payslip->refreshTotals();
                $kept[] = $payslip->id;
            }

            // Anyone no longer eligible drops out of the draft.
            Payslip::where('payroll_run_id', $run->id)->whereNotIn('id', $kept)->delete();
            $run->update(['warnings' => $warnings]);
        });
    }

    public function submit(PayrollRun $run, User $by): void
    {
        abort_unless($run->status === 'draft', 422, 'Only draft runs can be submitted.');
        abort_if($run->payslips()->doesntExist(), 422, 'The run has no payslips.');

        $run->update(['status' => 'submitted', 'submitted_by' => $by->id, 'submitted_at' => now(), 'review_note' => null]);
        activity()->performedOn($run)->causedBy($by)->log('payroll submitted');
    }

    public function reject(PayrollRun $run, User $by, ?string $note): void
    {
        abort_unless(in_array($run->status, ['submitted', 'approved'], true), 422, 'Nothing to reject.');

        $run->update(['status' => 'draft', 'review_note' => $note, 'approved_by' => null, 'approved_at' => null]);
        activity()->performedOn($run)->causedBy($by)->withProperties(['note' => $note])->log('payroll returned to draft');
    }

    public function approve(PayrollRun $run, User $by): void
    {
        abort_unless($run->status === 'submitted', 422, 'Only submitted runs can be approved.');
        // Separation of duties: the person who prepared or submitted it cannot approve it.
        abort_if(in_array($by->id, [$run->prepared_by, $run->submitted_by], true), 403, 'A different user must approve this payroll.');

        $run->update(['status' => 'approved', 'approved_by' => $by->id, 'approved_at' => now()]);
        activity()->performedOn($run)->causedBy($by)->log('payroll approved');
    }

    public function lock(PayrollRun $run, User $by): void
    {
        abort_unless($run->status === 'approved', 422, 'Only approved runs can be locked.');

        $run->update(['status' => 'locked', 'locked_by' => $by->id, 'locked_at' => now()]);
        activity()->performedOn($run)->causedBy($by)->log('payroll locked and payslips published');
    }

    public function deleteDraft(PayrollRun $run, User $by): void
    {
        abort_unless($run->status === 'draft', 422, 'Only draft runs can be deleted.');

        activity()->causedBy($by)->withProperties(['period' => $run->period])->log('payroll draft deleted');
        $run->delete();
    }

    public function addManualLine(Payslip $payslip, string $type, string $description, float $amount): void
    {
        abort_unless($payslip->run->isEditable(), 422, 'The run is no longer editable.');
        abort_unless(in_array($type, ['earning', 'deduction'], true), 422);

        $payslip->lines()->create(['type' => $type, 'code' => 'MANUAL', 'description' => $description, 'amount' => round($amount, 2), 'is_manual' => true]);
        $payslip->refreshTotals();
        activity()->performedOn($payslip->run)->causedBy(auth()->user())->withProperties(['employee' => $payslip->employee_no, 'type' => $type, 'description' => $description, 'amount' => $amount])->log('manual payslip line added');
    }

    public function removeManualLine(PayslipLine $line): void
    {
        $payslip = $line->payslip_id ? Payslip::with('run')->findOrFail($line->payslip_id) : abort(404);
        abort_unless($payslip->run->isEditable() && $line->is_manual, 422);

        $line->delete();
        $payslip->refreshTotals();
        activity()->performedOn($payslip->run)->causedBy(auth()->user())->withProperties(['employee' => $payslip->employee_no, 'description' => $line->description, 'amount' => $line->amount])->log('manual payslip line removed');
    }
}
