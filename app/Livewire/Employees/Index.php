<?php

namespace App\Livewire\Employees;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Employees')]
class Index extends Component
{
    use AuthorizesPermission, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $department = '';

    #[Url(except: '')]
    public string $branch = '';

    #[Url(except: '')]
    public string $status = '';

    public function mount(): void
    {
        $this->allow('employees.view');
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'department', 'branch', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $employees = Employee::query()
            ->visibleTo(auth()->user())
            ->with(['department', 'designation', 'branch'])
            ->search($this->search)
            ->when($this->department, fn ($q) => $q->where('department_id', $this->department))
            ->when($this->branch, fn ($q) => $q->where('branch_id', $this->branch))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderBy('employee_no')
            ->paginate(15);

        return view('livewire.employees.index', [
            'employees' => $employees,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
            'statuses' => Employee::STATUSES,
            'canCreate' => auth()->user()->can('employees.create'),
        ]);
    }
}
