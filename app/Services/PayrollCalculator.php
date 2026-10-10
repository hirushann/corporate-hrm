<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Pure calculation of one employee's payslip for a period. Does not write anything.
 *
 * Rules (all rates come from PayrollSetting):
 *  - basic is prorated by calendar days employed when joining/leaving mid-period
 *  - no-pay day = basic / working_days_divisor (absent days, unpaid leave, the absent half of a half-day leave)
 *  - OT hour = basic / ot_hour_divisor x weekday or holiday multiplier (only for ot_eligible employees)
 *  - EPF/ETF are on (earned basic - no-pay), plus EPF-applicable allowances when configured
 */
class PayrollCalculator
{
    /**
     * @param  Collection<int, AttendanceRecord>  $records  attendance in the period
     * @param  Collection<int, LeaveRequest>  $leaves  approved leave overlapping the period (with leaveType)
     * @return array{payslip: array<string, mixed>, lines: list<array<string, mixed>>, warnings: list<string>}|null null = no salary on file
     */
    public function calculate(Employee $employee, CarbonInterface $start, CarbonInterface $end, array $settings, Collection $records, Collection $leaves): ?array
    {
        $salary = $employee->salaryOn($end);
        if (! $salary) {
            return null;
        }

        $warnings = [];
        $basic = (float) $salary->basic_salary;

        // Proration for part-month employment
        $daysInPeriod = (int) $start->diffInDays($end) + 1;
        $from = $employee->join_date->gt($start) ? $employee->join_date : $start;
        $to = ($employee->resignation_date && $employee->resignation_date->lt($end)) ? $employee->resignation_date : $end;
        $employedDays = max(0, (int) $from->diffInDays($to) + 1);
        $proration = min(1, $employedDays / $daysInPeriod);

        $basicEarned = $this->r($basic * $proration);
        $lines = [$this->line('earning', 'BASIC', $proration < 1 ? "Basic salary ({$employedDays}/{$daysInPeriod} days)" : 'Basic salary', $basicEarned)];

        // Allowances
        $epfAllowances = 0.0;
        if ((float) $salary->fixed_allowances > 0) {
            $amount = $this->r((float) $salary->fixed_allowances * $proration);
            $lines[] = $this->line('earning', 'ALLOWANCE', 'Fixed allowances', $amount);
        }
        foreach ($employee->components()->with('component')->get() as $ec) {
            $active = $ec->effective_from->lte($end) && ($ec->effective_to === null || $ec->effective_to->gte($start));
            if (! $active || ! $ec->component?->is_active) {
                continue;
            }
            if ($ec->component->type === 'earning') {
                $amount = $this->r((float) $ec->amount * $proration);
                $lines[] = $this->line('earning', 'COMP', $ec->component->name, $amount);
                $epfAllowances += $ec->component->epf_applicable ? $amount : 0;
            } else {
                $lines[] = $this->line('deduction', 'DED', $ec->component->name, $this->r((float) $ec->amount));
            }
        }

        // No-pay
        $noPayDays = $this->noPayDays($records, $leaves);
        $noPay = min($basicEarned, $this->r($basic / $settings['working_days_divisor'] * $noPayDays));
        if ($noPay > 0) {
            $lines[] = $this->line('deduction', 'NOPAY', "No-pay ({$noPayDays} days)", $noPay);
        }

        // Overtime
        $wdMinutes = (int) $records->where('status', 'present')->sum('ot_minutes');
        $hdMinutes = (int) $records->whereIn('status', ['off', 'holiday'])->sum('ot_minutes');
        if ($employee->ot_eligible) {
            $hourly = $basic / $settings['ot_hour_divisor'];
            if ($wdMinutes > 0) {
                $lines[] = $this->line('earning', 'OT_WD', sprintf('Overtime %s h @ %sx', $this->hours($wdMinutes), $settings['ot_weekday_rate']), $this->r($wdMinutes / 60 * $hourly * $settings['ot_weekday_rate']));
            }
            if ($hdMinutes > 0) {
                $lines[] = $this->line('earning', 'OT_HD', sprintf('Holiday/off-day overtime %s h @ %sx', $this->hours($hdMinutes), $settings['ot_holiday_rate']), $this->r($hdMinutes / 60 * $hourly * $settings['ot_holiday_rate']));
            }
        } elseif ($wdMinutes + $hdMinutes > 0) {
            $warnings[] = "{$employee->employee_no}: overtime recorded but employee is not OT eligible.";
        }

        // Statutory
        $epfBase = max(0, $this->r($basicEarned - $noPay + ($settings['epf_includes_allowances'] ? $epfAllowances : 0)));
        if ($employee->epf_applicable && $epfBase > 0) {
            $lines[] = $this->line('deduction', 'EPF_EE', sprintf('EPF employee %s%%', $settings['epf_employee_rate']), $this->r($epfBase * $settings['epf_employee_rate'] / 100));
            $lines[] = $this->line('employer', 'EPF_ER', sprintf('EPF employer %s%%', $settings['epf_employer_rate']), $this->r($epfBase * $settings['epf_employer_rate'] / 100));
        }
        if ($employee->etf_applicable && $epfBase > 0) {
            $lines[] = $this->line('employer', 'ETF_ER', sprintf('ETF employer %s%%', $settings['etf_rate']), $this->r($epfBase * $settings['etf_rate'] / 100));
        }

        $sum = fn (string $type) => $this->r(collect($lines)->where('type', $type)->sum('amount'));
        $gross = $sum('earning');
        $deductions = $sum('deduction');

        if ($gross - $deductions < 0) {
            $warnings[] = "{$employee->employee_no}: net salary is negative.";
        }
        if ($records->where('missing_punch', true)->count() > 0) {
            $warnings[] = "{$employee->employee_no}: {$records->where('missing_punch', true)->count()} day(s) with a missing punch.";
        }

        return [
            'payslip' => [
                'employee_no' => $employee->employee_no, 'employee_name' => $employee->full_name,
                'department' => $employee->department?->name, 'designation' => $employee->designation?->name,
                'bank_name' => $employee->bank_name, 'bank_branch' => $employee->bank_branch,
                'bank_account_number' => $employee->bank_account_number, 'bank_account_name' => $employee->bank_account_name,
                'epf_number' => $employee->epf_number, 'etf_number' => $employee->etf_number,
                'basic_salary' => $basic, 'no_pay_days' => $noPayDays, 'ot_weekday_minutes' => $wdMinutes, 'ot_holiday_minutes' => $hdMinutes,
                'epf_base' => $epfBase,
            ],
            'lines' => $lines,
            'warnings' => $warnings,
        ];
    }

    /** Days of pay lost: absent days, unpaid leave, and the absent half of a half-day leave. */
    public function noPayDays(Collection $records, Collection $leaves): float
    {
        $days = 0.0;

        foreach ($records as $record) {
            $fraction = (float) $record->leave_fraction;
            $unpaid = $fraction > 0 && $leaves->contains(
                fn ($l) => $l->from_date->lte($record->date) && $l->to_date->gte($record->date) && ! $l->leaveType->is_paid
            );

            if ($record->status === 'absent') {
                $days += 1;
            } elseif ($record->status === 'leave') {
                $days += $fraction < 1 ? (1 - $fraction) : 0; // the half not covered by leave and with no punches
                $days += $unpaid ? $fraction : 0;
            } elseif ($record->status === 'present' && $unpaid) {
                $days += $fraction;
            }
        }

        return round($days, 2);
    }

    private function line(string $type, string $code, string $description, float $amount): array
    {
        return ['type' => $type, 'code' => $code, 'description' => $description, 'amount' => $amount, 'is_manual' => false];
    }

    private function hours(int $minutes): string
    {
        return rtrim(rtrim(number_format($minutes / 60, 2, '.', ''), '0'), '.');
    }

    private function r(float $value): float
    {
        return round($value, 2);
    }
}
