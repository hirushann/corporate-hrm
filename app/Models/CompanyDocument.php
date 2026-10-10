<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Guarded([])]
class CompanyDocument extends Model
{
    use LogsActivity;

    public const CATEGORIES = ['policy' => 'Policy', 'form' => 'Form', 'handbook' => 'Handbook', 'circular' => 'Circular', 'other' => 'Other'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'category', 'original_name'])->dontLogEmptyChanges();
    }
}
