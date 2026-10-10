<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Guarded([])]
class Shift extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return ['working_days' => 'array', 'is_default' => 'boolean', 'is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function isWorkingDay(CarbonInterface $date): bool
    {
        return in_array($date->dayOfWeekIso, array_map('intval', $this->working_days), true);
    }

    /** Shift start/end as full datetimes on the given date. */
    public function startOn(CarbonInterface $date): Carbon
    {
        return Carbon::parse($date->toDateString().' '.$this->start_time);
    }

    public function endOn(CarbonInterface $date): Carbon
    {
        return Carbon::parse($date->toDateString().' '.$this->end_time);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
