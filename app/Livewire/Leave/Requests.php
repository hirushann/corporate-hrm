<?php

namespace App\Livewire\Leave;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Leave requests')]
class Requests extends Component
{
    use AuthorizesPermission, WithPagination;

    public string $statusFilter = 'pending';

    public ?int $employeeId = null;

    public ?int $leaveTypeId = null;

    public string $fromDate = '';

    public string $toDate = '';

    public bool $halfDay = false;

    public string $reason = '';

    public ?int $decidingId = null;

    public string $decisionNote = '';

    public function mount(): void
    {
        $this->allow('leave.view');
        $this->fromDate = $this->toDate = today()->toDateString();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function submit(LeaveService $leave): void
    {
        $this->allow('leave.manage');

        $this->validate([
            'employeeId' => 'required|integer',
            'leaveTypeId' => 'required|exists:leave_types,id',
            'fromDate' => 'required|date',
            'toDate' => 'required|date|after_or_equal:fromDate',
            'halfDay' => 'boolean',
            'reason' => 'nullable|string|max:255',
        ]);

        $employee = Employee::visibleTo(auth()->user())->working()->findOrFail($this->employeeId);
        $type = LeaveType::active()->findOrFail($this->leaveTypeId);

        $leave->request($employee, $type, Carbon::parse($this->fromDate), Carbon::parse($this->toDate), $this->halfDay, $this->reason ?: null, auth()->id());

        $this->reset('employeeId', 'reason', 'halfDay');
        session()->flash('status', 'Leave request submitted.');
    }

    private function authorizedRequest(int $id): LeaveRequest
    {
        return LeaveRequest::with('employee')
            ->whereIn('employee_id', Employee::visibleTo(auth()->user())->select('id'))
            ->findOrFail($id);
    }

    public function decide(int $id, bool $approve, LeaveService $leave): void
    {
        $this->allow('leave.approve');

        $request = $this->authorizedRequest($id);
        // Nobody approves their own leave.
        abort_if($request->employee->user_id === auth()->id(), 403, 'You cannot decide your own leave request.');

        $leave->decide($request, $approve, auth()->id(), $this->decisionNote ?: null);

        $this->reset('decidingId', 'decisionNote');
        session()->flash('status', $approve ? 'Leave approved.' : 'Leave rejected.');
    }

    public function cancel(int $id, LeaveService $leave): void
    {
        $this->allow('leave.manage');

        $request = $this->authorizedRequest($id);
        abort_unless(in_array($request->status, ['pending', 'approved'], true), 422);
        $leave->cancel($request);
        session()->flash('status', 'Leave request cancelled.');
    }

    public function render(LeaveService $leave)
    {
        $requests = LeaveRequest::with(['employee', 'leaveType', 'decider'])
            ->whereIn('employee_id', Employee::visibleTo(auth()->user())->select('id'))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->latest('id')->paginate(15);

        $preview = null;
        if ($this->employeeId && $this->leaveTypeId && ($employee = Employee::find($this->employeeId)) && ($type = LeaveType::find($this->leaveTypeId))) {
            $remaining = $leave->remaining($employee, $type, (int) substr($this->fromDate ?: today()->toDateString(), 0, 4));
            $preview = $remaining === null ? 'Unlimited' : $remaining.' days remaining (including pending)';
        }

        return view('livewire.leave.requests', [
            'requests' => $requests,
            'employees' => Employee::visibleTo(auth()->user())->working()->orderBy('full_name')->get(['id', 'employee_no', 'full_name']),
            'types' => LeaveType::active()->orderBy('name')->get(),
            'preview' => $preview,
            'canManage' => auth()->user()->can('leave.manage'),
            'canApprove' => auth()->user()->can('leave.approve'),
        ]);
    }
}
