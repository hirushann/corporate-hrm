<?php

namespace App\Console\Commands;

use App\Services\AttendanceProcessor;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('attendance:process {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--days=1 : Process the last N days up to today instead}')]
#[Description('Recalculate daily attendance records from punches, shifts, holidays and leave')]
class ProcessAttendance extends Command
{
    public function handle(AttendanceProcessor $processor): int
    {
        $to = $this->option('to') ? Carbon::parse($this->option('to')) : today();
        $from = $this->option('from') ? Carbon::parse($this->option('from')) : $to->copy()->subDays(max(0, (int) $this->option('days') - 1));

        $count = $processor->processRange($from, $to);
        $this->info("Processed {$count} records ({$from->toDateString()} to {$to->toDateString()}).");

        return self::SUCCESS;
    }
}
