<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded([])]
class PayrollRun extends Model
{
    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date', 'settings' => 'array', 'warnings' => 'array',
            'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'locked_at' => 'datetime',
        ];
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function label(): string
    {
        return $this->period_start->format('F Y');
    }
}
