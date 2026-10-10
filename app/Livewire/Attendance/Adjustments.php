<?php

namespace App\Livewire\Attendance;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\AttendanceAdjustment;
use App\Models\Employee;
use App\Services\AttendanceProcessor;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Attendance adjustments')]
class Adjustments extends Component
{
    use AuthorizesPermission, WithPagination;

    public string $statusFilter = 'pending';

    public ?int $employeeId = null;

    public string $date = '';

    public ?string $inTime = null;

    public ?string $outTime = null;

    public string $reason = '';

    public ?int $reviewingId = null;

    public string $reviewNote = '';

    public function mount(): void
    {
        $this->allow('attendance.adjust');
        $this->date = today()->toDateString();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function submit(): void
    {
        $this->allow('attendance.adjust');

        $this->validate([
            'employeeId' => 'required|integer',
            'date' => 'required|date|before_or_equal:today',
            'inTime' => 'nullable|date_format:H:i|required_without:outTime',
            'outTime' => 'nullable|date_format:H:i|required_without:inTime',
            'reason' => 'required|string|min:5|max:255',
        ], ['inTime.required_without' => 'Enter an in time, an out time, or both.', 'outTime.required_without' => 'Enter an in time, an out time, or both.']);

        // Managers may only adjust their own team.
        $employee = Employee::visibleTo(auth()->user())->working()->findOrFail($this->employeeId);

        $in = $this->inTime ? Carbon::parse("{$this->date} {$this->inTime}") : null;
        $out = $this->outTime ? Carbon::parse("{$this->date} {$this->outTime}") : null;
        if ($in && $out && $out->lte($in)) {
            $this->addError('outTime', 'Out time must be after in time.');

            return;
        }

        AttendanceAdjustment::create([
            'employee_id' => $employee->id, 'date' => $this->date, 'in_at' => $in, 'out_at' => $out,
            'reason' => $this->reason, 'requested_by' => auth()->id(),
        ]);

        $this->reset('employeeId', 'inTime', 'outTime', 'reason');
        session()->flash('status', 'Adjustment submitted for approval.');
    }

    public function review(int $id, bool $approve, AttendanceProcessor $processor): void
    {
        $this->allow('attendance.approve-adjustment');

        $adjustment = AttendanceAdjustment::with('employee')->findOrFail($id);
        abort_unless($adjustment->status === 'pending', 422);
        // Separation of duties: nobody approves their own request.
        abort_if($adjustment->requested_by === auth()->id(), 403, 'You cannot review your own request.');

        $adjustment->update([
            'status' => $approve ? 'approved' : 'rejected',
            'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'review_note' => $this->reviewNote ?: null,
        ]);

        if ($approve) {
            $processor->processRange($adjustment->date, $adjustment->date, [$adjustment->employee]);
        }

        $this->reset('reviewingId', 'reviewNote');
        session()->flash('status', $approve ? 'Adjustment approved and attendance updated.' : 'Adjustment rejected.');
    }

    public function render()
    {
        $adjustments = AttendanceAdjustment::with(['employee', 'requester', 'reviewer'])
            ->whereIn('employee_id', Employee::visibleTo(auth()->user())->select('id'))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->latest('id')->paginate(15);

        return view('livewire.attendance.adjustments', [
            'adjustments' => $adjustments,
            'employees' => Employee::visibleTo(auth()->user())->working()->orderBy('full_name')->get(['id', 'employee_no', 'full_name']),
            'canReview' => auth()->user()->can('attendance.approve-adjustment'),
        ]);
    }
}
