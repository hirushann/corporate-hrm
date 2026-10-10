<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Guarded([])]
class Notice extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean', 'published_at' => 'date', 'expires_at' => 'date'];
    }

    /** Notices an employee in the given department should currently see. */
    public function scopeVisibleTo(Builder $query, ?int $departmentId): void
    {
        $query->whereDate('published_at', '<=', today())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
            ->where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', $departmentId ?? 0));
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'department_id', 'is_pinned', 'published_at', 'expires_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
