<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Shift;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    public function __construct(private AttendanceProcessor $attendance) {}

    /** Working days in the range (excludes the employee's days off and holidays). Half day = 0.5 for a single day. */
    public function countDays(Employee $employee, CarbonInterface $from, CarbonInterface $to, bool $halfDay = false): float
    {
        $shift = $this->shiftFor($employee, $from);
        $holidays = Holiday::whereBetween('date', [$from, $to])->pluck('date')->map->toDateString()->all();
        $days = 0.0;

        for ($d = Carbon::parse($from); $d->lte($to); $d->addDay()) {
            $working = $shift ? $shift->isWorkingDay($d) : $d->isWeekday();
            if ($working && ! in_array($d->toDateString(), $holidays, true)) {
                $days++;
            }
        }

        return $halfDay && $days === 1.0 ? 0.5 : $days;
    }

    private function shiftFor(Employee $employee, CarbonInterface $date): ?Shift
    {
        $assignment = $employee->shiftAssignments()
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->latest('effective_from')->first();

        return $assignment?->shift ?? Shift::where('is_default', true)->first();
    }

    /** Days taken (approved) in a calendar year for one leave type. */
    public function used(Employee $employee, LeaveType $type, int $year, bool $includePending = false): float
    {
        return (float) LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->whereIn('status', $includePending ? ['approved', 'pending'] : ['approved'])
            ->whereYear('from_date', $year)
            ->sum('days');
    }

    /** @return float|null null = unlimited */
    public function remaining(Employee $employee, LeaveType $type, int $year, bool $includePending = true): ?float
    {
        if ($type->annual_days === null) {
            return null;
        }

        $balance = LeaveBalance::where(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year])->first();
        $allowed = $balance ? (float) $balance->entitled + (float) $balance->carried_forward : 0.0;

        return $allowed - $this->used($employee, $type, $year, $includePending);
    }

    /** @throws ValidationException */
    public function request(Employee $employee, LeaveType $type, CarbonInterface $from, CarbonInterface $to, bool $halfDay, ?string $reason, ?int $requestedBy): LeaveRequest
    {
        $field = 'leave';
        if ($to->lt($from)) {
            throw ValidationException::withMessages([$field => 'The end date is before the start date.']);
        }
        if ($from->year !== $to->year) {
            throw ValidationException::withMessages([$field => 'A request cannot span two calendar years; submit one request per year.']);
        }
        if ($halfDay && ! $from->isSameDay($to)) {
            throw ValidationException::withMessages([$field => 'Half-day leave must be a single date.']);
        }

        $days = $this->countDays($employee, $from, $to, $halfDay);
        if ($days <= 0) {
            throw ValidationException::withMessages([$field => 'The selected dates contain no working days.']);
        }

        $overlap = LeaveRequest::where('employee_id', $employee->id)->whereIn('status', ['pending', 'approved'])
            ->whereDate('from_date', '<=', $to)->whereDate('to_date', '>=', $from)->exists();
        if ($overlap) {
            throw ValidationException::withMessages([$field => 'This overlaps an existing leave request.']);
        }

        $remaining = $this->remaining($employee, $type, $from->year);
        if ($remaining !== null && $days > $remaining) {
            throw ValidationException::withMessages([$field => "Insufficient {$type->name} balance (remaining: {$remaining})."]);
        }

        return LeaveRequest::create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'from_date' => $from, 'to_date' => $to, 'days' => $days, 'half_day' => $halfDay && $days === 0.5,
            'reason' => $reason, 'status' => 'pending', 'requested_by' => $requestedBy,
        ]);
    }

    public function decide(LeaveRequest $request, bool $approve, int $userId, ?string $note = null): void
    {
        abort_unless($request->status === 'pending', 422, 'Only pending requests can be decided.');

        $request->update([
            'status' => $approve ? 'approved' : 'rejected',
            'decided_by' => $userId, 'decided_at' => now(), 'decision_note' => $note,
        ]);

        if ($approve) {
            $this->refreshAttendance($request);
        }
    }

    public function cancel(LeaveRequest $request): void
    {
        $wasApproved = $request->status === 'approved';
        $request->update(['status' => 'cancelled']);

        if ($wasApproved) {
            $this->refreshAttendance($request);
        }
    }

    /** Re-derive attendance for the leave dates (those already past). */
    private function refreshAttendance(LeaveRequest $request): void
    {
        $to = $request->to_date->isFuture() ? today() : $request->to_date;
        if ($request->from_date->lte($to)) {
            $this->attendance->processRange($request->from_date, $to, [$request->employee]);
        }
    }

    /** Create balances for every working employee for the year from leave-type defaults, carrying forward unused days. */
    public function generateBalances(int $year): int
    {
        $created = 0;
        $types = LeaveType::active()->whereNotNull('annual_days')->get();

        foreach (Employee::working()->get() as $employee) {
            foreach ($types as $type) {
                $balance = LeaveBalance::firstOrNew(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year]);
                if ($balance->exists) {
                    continue;
                }

                $carry = 0.0;
                if ((float) $type->carry_forward_max > 0) {
                    $previous = $this->remaining($employee, $type, $year - 1, includePending: false) ?? 0;
                    $carry = max(0, min($previous, (float) $type->carry_forward_max));
                }

                $balance->fill(['entitled' => $type->annual_days, 'carried_forward' => $carry])->save();
                $created++;
            }
        }

        return $created;
    }
}
