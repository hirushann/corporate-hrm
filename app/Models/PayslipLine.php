<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Guarded([])]
class PayslipLine extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'is_manual' => 'boolean'];
    }
}
