<?php

namespace App\Services;

use App\Models\AttendanceAdjustment;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Punch;
use App\Models\Shift;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Turns raw punches + shifts + holidays + leave + approved adjustments into one
 * attendance_records row per employee per day. Safe to re-run: it upserts.
 */
class AttendanceProcessor
{
    /** @return int number of records written */
    public function processRange(CarbonInterface $from, CarbonInterface $to, ?iterable $employees = null): int
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();
        $employees ??= Employee::working()->orWhereDate('resignation_date', '>=', $from)->get();

        $holidays = Holiday::whereBetween('date', [$from, $to])->pluck('date')->map(fn ($d) => $d->toDateString())->flip();
        $shifts = Shift::all()->keyBy('id');
        $defaultShift = $shifts->firstWhere('is_default', true);
        $written = 0;

        foreach ($employees as $employee) {
            $assignments = $employee->shiftAssignments()->get();
            $punches = Punch::where('employee_id', $employee->id)
                ->whereBetween('punched_at', [$from->copy(), $to->copy()->endOfDay()])
                ->orderBy('punched_at')->get()
                ->groupBy(fn ($p) => $p->punched_at->toDateString());
            $leaves = LeaveRequest::where('employee_id', $employee->id)->where('status', 'approved')
                ->whereDate('from_date', '<=', $to)->whereDate('to_date', '>=', $from)->get();
            $adjustments = AttendanceAdjustment::where('employee_id', $employee->id)->where('status', 'approved')
                ->whereBetween('date', [$from, $to])->orderBy('id')->get()->keyBy(fn ($a) => $a->date->toDateString());

            for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                $key = $day->toDateString();
                $data = $this->computeDay(
                    $employee, $day, $this->shiftFor($assignments, $shifts, $defaultShift, $day),
                    isset($holidays[$key]), $punches->get($key, collect()), $leaves, $adjustments->get($key),
                );

                if ($data === null) {
                    AttendanceRecord::where('employee_id', $employee->id)->whereDate('date', $key)->delete();

                    continue;
                }

                // whereDate keeps the lookup independent of how the DB stores the date column.
                $record = AttendanceRecord::where('employee_id', $employee->id)->whereDate('date', $key)->first();
                $record ? $record->update($data) : AttendanceRecord::create($data + ['employee_id' => $employee->id, 'date' => $key]);
                $written++;
            }
        }

        return $written;
    }

    private function shiftFor(Collection $assignments, Collection $shifts, ?Shift $default, CarbonInterface $day): ?Shift
    {
        $match = $assignments
            ->filter(fn ($a) => $a->effective_from->lte($day) && ($a->effective_to === null || $a->effective_to->gte($day)))
            ->sortByDesc('effective_from')->first();

        return $match ? $shifts->get($match->shift_id) : $default;
    }

    /** @return array<string, mixed>|null null = no record for this day */
    private function computeDay(Employee $employee, Carbon $day, ?Shift $shift, bool $isHoliday, Collection $punches, Collection $leaves, ?AttendanceAdjustment $adjustment): ?array
    {
        $now = now();
        if ($day->isFuture() && ! $day->isToday()) {
            return null;
        }
        if ($day->lt($employee->join_date) || ($employee->resignation_date && $day->gt($employee->resignation_date))) {
            return null;
        }
        if (! $shift) {
            return null;
        }

        $leave = $leaves->first(fn ($l) => $l->from_date->lte($day) && $l->to_date->gte($day));
        $leaveFraction = $leave ? ($leave->half_day ? 0.5 : 1.0) : 0.0;

        // Times: an approved adjustment wins over raw punches, field by field.
        $in = $punches->first()?->punched_at;
        $out = $punches->count() > 1 ? $punches->last()->punched_at : null;
        if ($adjustment) {
            $in = $adjustment->in_at ?? $in;
            $out = $adjustment->out_at ?? $out;
        }

        $base = [
            'shift_id' => $shift->id,
            'first_in' => $in, 'last_out' => $out,
            'worked_minutes' => 0, 'late_minutes' => 0, 'early_minutes' => 0, 'ot_minutes' => 0,
            'leave_fraction' => $leaveFraction, 'missing_punch' => false, 'is_adjusted' => $adjustment !== null,
        ];

        $span = ($in && $out) ? max(0, $in->diffInMinutes($out)) : 0;
        $isWorkingDay = $shift->isWorkingDay($day) && ! $isHoliday;

        if (! $isWorkingDay) {
            // Work on a day off / holiday is all overtime.
            return array_merge($base, [
                'status' => $isHoliday ? 'holiday' : 'off',
                'worked_minutes' => $span,
                'ot_minutes' => $span,
                'missing_punch' => $in && ! $out,
            ]);
        }

        if ($leaveFraction === 1.0) {
            return array_merge($base, ['status' => 'leave']);
        }

        if (! $in) {
            // Today and the shift is still running: not absent yet.
            if ($day->isToday() && $now->lt($shift->endOn($day))) {
                return null;
            }

            return array_merge($base, ['status' => $leaveFraction > 0 ? 'leave' : 'absent']);
        }

        $late = $early = $ot = $worked = 0;
        $start = $shift->startOn($day);
        $end = $shift->endOn($day);

        if ($leaveFraction === 0.0 && $in->gt($start->copy()->addMinutes($shift->late_grace_minutes))) {
            $late = (int) $start->diffInMinutes($in);
        }
        if ($out) {
            $worked = max(0, $span - $shift->break_minutes);
            if ($leaveFraction === 0.0 && $out->lt($end->copy()->subMinutes($shift->early_grace_minutes))) {
                $early = (int) $out->diffInMinutes($end);
            }
            if ($leaveFraction === 0.0 && $out->gte($end->copy()->addMinutes($shift->ot_after_minutes))) {
                $ot = (int) $end->diffInMinutes($out);
            }
        }

        return array_merge($base, [
            'status' => 'present',
            'worked_minutes' => $worked, 'late_minutes' => $late, 'early_minutes' => $early, 'ot_minutes' => $ot,
            'missing_punch' => ! $out,
        ]);
    }
}
