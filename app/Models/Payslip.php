<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded([])]
class Payslip extends Model
{
    protected function casts(): array
    {
        return [
            'bank_account_number' => 'encrypted',
            'basic_salary' => 'decimal:2', 'no_pay_days' => 'decimal:2', 'epf_base' => 'decimal:2', 'gross' => 'decimal:2',
            'total_deductions' => 'decimal:2', 'net_salary' => 'decimal:2', 'employer_contributions' => 'decimal:2',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class)->orderBy('id');
    }

    /** Recompute the stored totals from the lines (the lines are the source of truth). */
    public function refreshTotals(): void
    {
        $sum = fn (string $type) => round((float) $this->lines()->where('type', $type)->sum('amount'), 2);

        $gross = $sum('earning');
        $deductions = $sum('deduction');

        $this->update([
            'gross' => $gross, 'total_deductions' => $deductions,
            'net_salary' => round($gross - $deductions, 2), 'employer_contributions' => $sum('employer'),
        ]);
    }
}
