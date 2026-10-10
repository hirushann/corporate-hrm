<?php

namespace App\Livewire\Leave;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Services\LeaveService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Leave balances')]
class Balances extends Component
{
    use AuthorizesPermission, WithPagination;

    public int $year;

    public string $search = '';

    public function mount(): void
    {
        $this->allow('leave.view');
        $this->year = (int) today()->year;
    }

    public function updated($property): void
    {
        $this->resetPage();
    }

    public function generate(LeaveService $leave): void
    {
        $this->allow('leave.manage');
        $this->validate(['year' => 'required|integer|min:2000|max:2100']);

        $created = $leave->generateBalances($this->year);
        session()->flash('status', "Created {$created} balance rows for {$this->year}. Existing balances were left untouched.");
    }

    public function render(LeaveService $leave)
    {
        $types = LeaveType::active()->whereNotNull('annual_days')->orderBy('name')->get();

        $employees = Employee::visibleTo(auth()->user())->working()->search($this->search)->orderBy('employee_no')->paginate(20);

        $balances = LeaveBalance::whereIn('employee_id', $employees->pluck('id'))->where('year', $this->year)->get()
            ->groupBy('employee_id');

        $grid = $employees->mapWithKeys(fn ($e) => [$e->id => $types->mapWithKeys(function ($t) use ($e, $balances, $leave) {
            $b = $balances->get($e->id)?->firstWhere('leave_type_id', $t->id);

            return [$t->id => $b ? [
                'allowed' => (float) $b->entitled + (float) $b->carried_forward,
                'used' => $leave->used($e, $t, $this->year),
                'pending' => $leave->used($e, $t, $this->year, true) - $leave->used($e, $t, $this->year),
            ] : null];
        })]);

        return view('livewire.leave.balances', [
            'employees' => $employees, 'types' => $types, 'grid' => $grid,
            'canManage' => auth()->user()->can('leave.manage'),
        ]);
    }
}
