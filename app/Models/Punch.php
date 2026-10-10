<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Guarded([])]
class Punch extends Model
{
    protected function casts(): array
    {
        return ['punched_at' => 'datetime'];
    }
}
