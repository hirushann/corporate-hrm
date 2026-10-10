<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use App\Models\Shift;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        Shift::firstOrCreate(['name' => 'General'], [
            'start_time' => '08:30', 'end_time' => '17:30', 'break_minutes' => 60,
            'late_grace_minutes' => 15, 'early_grace_minutes' => 15, 'ot_after_minutes' => 30,
            'working_days' => [1, 2, 3, 4, 5], 'is_default' => true,
        ]);

        foreach ([
            ['Annual', 14, true, 0],
            ['Casual', 7, true, 0],
            ['Medical', 7, true, 0],
            ['No Pay', null, false, 0],
        ] as [$name, $days, $paid, $carry]) {
            LeaveType::firstOrCreate(['name' => $name], ['annual_days' => $days, 'is_paid' => $paid, 'carry_forward_max' => $carry]);
        }
    }
}
