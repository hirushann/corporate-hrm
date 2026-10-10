<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Guarded([])]
class EmployeeDocument extends Model
{
    use LogsActivity;

    public const TYPES = [
        'nic' => 'NIC / ID copy',
        'contract' => 'Employment contract',
        'appointment' => 'Appointment letter',
        'certificate' => 'Certificate / qualification',
        'resume' => 'CV / Resume',
        'other' => 'Other',
    ];

    protected function casts(): array
    {
        return ['expires_on' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['type', 'title', 'original_name', 'expires_on'])->dontLogEmptyChanges();
    }
}
