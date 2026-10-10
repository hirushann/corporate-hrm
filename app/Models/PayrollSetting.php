<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Guarded([])]
class PayrollSetting extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'working_days_divisor' => 'float', 'ot_hour_divisor' => 'float', 'ot_weekday_rate' => 'float', 'ot_holiday_rate' => 'float',
            'epf_employee_rate' => 'float', 'epf_employer_rate' => 'float', 'etf_rate' => 'float', 'epf_includes_allowances' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::create([]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
