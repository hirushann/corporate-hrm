<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\ResolvesEmployee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('My leave')]
class Leave extends Component
{
    use ResolvesEmployee;

    public ?int $leaveTypeId = null;

    public string $fromDate = '';

    public string $toDate = '';

    public bool $halfDay = false;

    public string $reason = '';

    public function mount(): void
    {
        $this->myEmployee();
        $this->fromDate = $this->toDate = today()->toDateString();
    }

    public function submit(LeaveService $leave): void
    {
        $employee = $this->myEmployee();

        $this->validate([
            'leaveTypeId' => 'required|exists:leave_types,id',
            'fromDate' => 'required|date',
            'toDate' => 'required|date|after_or_equal:fromDate',
            'halfDay' => 'boolean',
            'reason' => 'nullable|string|max:255',
        ]);

        $leave->request(
            $employee, LeaveType::active()->findOrFail($this->leaveTypeId),
            Carbon::parse($this->fromDate), Carbon::parse($this->toDate), $this->halfDay, $this->reason ?: null, auth()->id(),
        );

        $this->reset('reason', 'halfDay');
        session()->flash('status', 'Leave request submitted for approval.');
    }

    public function cancel(int $id, LeaveService $leave): void
    {
        $request = LeaveRequest::where('employee_id', $this->myEmployee()->id)->findOrFail($id);
        abort_unless($request->status === 'pending', 422, 'Only pending requests can be cancelled here.');

        $leave->cancel($request);
        session()->flash('status', 'Leave request cancelled.');
    }

    public function render(LeaveService $leave)
    {
        $employee = $this->myEmployee();
        $year = (int) today()->year;

        $balances = LeaveType::active()->orderBy('name')->get()->map(function ($type) use ($leave, $employee, $year) {
            $left = $leave->remaining($employee, $type, $year);

            return ['name' => $type->name, 'left' => $left, 'taken' => $leave->used($employee, $type, $year)];
        });

        return view('livewire.portal.leave', [
            'balances' => $balances,
            'types' => LeaveType::active()->orderBy('name')->get(),
            'requests' => LeaveRequest::with('leaveType')->where('employee_id', $employee->id)->latest('id')->limit(30)->get(),
            'year' => $year,
        ]);
    }
}
