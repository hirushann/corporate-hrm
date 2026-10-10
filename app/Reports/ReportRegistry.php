<?php

namespace App\Reports;

use App\Models\User;

class ReportRegistry
{
    /** @var list<class-string<Report>> */
    private const REPORTS = [
        DailyAttendanceReport::class,
        MonthlyAttendanceReport::class,
        PayrollSummaryReport::class,
        SalaryTransferReport::class,
        EpfEtfReport::class,
        HeadcountReport::class,
        EmployeeListReport::class,
        JoinersLeaversReport::class,
    ];

    /** @return array<string, Report> keyed by report key */
    public static function all(): array
    {
        $reports = [];
        foreach (self::REPORTS as $class) {
            $reports[$class::key()] = new $class;
        }

        return $reports;
    }

    /** @return array<string, Report> reports the user may open */
    public static function for(User $user): array
    {
        return array_filter(self::all(), fn (Report $r) => $user->can($r->permission()));
    }

    public static function find(string $key): ?Report
    {
        return self::all()[$key] ?? null;
    }
}
