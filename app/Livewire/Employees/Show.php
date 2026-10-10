<?php

namespace App\Livewire\Employees;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\SalaryComponent;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Employee profile')]
class Show extends Component
{
    use AuthorizesPermission, WithFileUploads;

    public Employee $employee;

    #[Url]
    public string $tab = 'profile';

    // Salary form
    public string $effectiveFrom = '';

    public string $basicSalary = '';

    public string $fixedAllowances = '0';

    public string $salaryReason = '';

    // Recurring component form
    public ?int $componentId = null;

    public string $componentAmount = '';

    public string $componentFrom = '';

    public ?string $componentTo = null;

    // Document form
    public string $docType = 'other';

    public string $docTitle = '';

    public ?string $docExpiresOn = null;

    public $docFile = null;

    public function mount(Employee $employee): void
    {
        $this->allow('employees.view');
        abort_unless(Employee::visibleTo(auth()->user())->whereKey($employee->id)->exists(), 403);
        $this->employee = $employee;
        $this->effectiveFrom = now()->toDateString();
        $this->componentFrom = now()->startOfMonth()->toDateString();

        if ($this->tab === 'salary' && ! $this->canSeeSalary()) {
            $this->tab = 'profile';
        }
    }

    private function canSeeSalary(): bool
    {
        return auth()->user()->can('employees.view-salary');
    }

    public function setTab(string $tab): void
    {
        $this->tab = match (true) {
            $tab === 'salary' && $this->canSeeSalary() => 'salary',
            $tab === 'documents' => 'documents',
            default => 'profile',
        };
    }

    public function addSalary(): void
    {
        $this->allow('employees.update');
        $this->allow('employees.view-salary');

        $this->validate([
            'effectiveFrom' => ['required', 'date', Rule::unique('employee_salaries', 'effective_from')->where('employee_id', $this->employee->id)],
            'basicSalary' => 'required|numeric|min:0|max:99999999',
            'fixedAllowances' => 'nullable|numeric|min:0|max:99999999',
            'salaryReason' => 'nullable|string|max:255',
        ]);

        $this->employee->salaries()->create([
            'effective_from' => $this->effectiveFrom,
            'basic_salary' => $this->basicSalary,
            'fixed_allowances' => $this->fixedAllowances ?: 0,
            'reason' => $this->salaryReason ?: null,
            'created_by' => auth()->id(),
        ]);

        $this->reset('basicSalary', 'salaryReason');
        $this->fixedAllowances = '0';
        session()->flash('status', 'Salary revision added.');
    }

    public function addComponent(): void
    {
        $this->allow('employees.update');
        $this->allow('employees.view-salary');

        $this->validate([
            'componentId' => ['required', Rule::exists('salary_components', 'id')->where('is_active', true)],
            'componentAmount' => 'required|numeric|gt:0|max:99999999',
            'componentFrom' => 'required|date',
            'componentTo' => 'nullable|date|after_or_equal:componentFrom',
        ]);

        $this->employee->components()->create([
            'salary_component_id' => $this->componentId, 'amount' => $this->componentAmount,
            'effective_from' => $this->componentFrom, 'effective_to' => $this->componentTo ?: null,
        ]);

        $this->reset('componentId', 'componentAmount', 'componentTo');
        session()->flash('status', 'Salary component added.');
    }

    public function removeComponent(int $id): void
    {
        $this->allow('employees.update');
        $this->allow('employees.view-salary');

        $this->employee->components()->findOrFail($id)->delete();
        session()->flash('status', 'Salary component removed.');
    }

    public function uploadDocument(): void
    {
        $this->allow('employees.manage-documents');

        $this->validate([
            'docType' => ['required', Rule::in(array_keys(EmployeeDocument::TYPES))],
            'docTitle' => 'required|string|max:255',
            'docExpiresOn' => 'nullable|date',
            'docFile' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx',
        ]);

        $path = $this->docFile->store("employee-documents/{$this->employee->id}", 'local');

        $this->employee->documents()->create([
            'type' => $this->docType,
            'title' => $this->docTitle,
            'path' => $path,
            'original_name' => $this->docFile->getClientOriginalName(),
            'mime_type' => $this->docFile->getMimeType(),
            'size' => $this->docFile->getSize(),
            'expires_on' => $this->docExpiresOn ?: null,
            'uploaded_by' => auth()->id(),
        ]);

        $this->reset('docTitle', 'docExpiresOn', 'docFile');
        $this->docType = 'other';
        session()->flash('status', 'Document uploaded.');
    }

    public function deleteDocument(int $id): void
    {
        $this->allow('employees.manage-documents');

        $document = $this->employee->documents()->findOrFail($id);
        Storage::disk('local')->delete($document->path);
        $document->delete();
        session()->flash('status', 'Document deleted.');
    }

    public function deleteEmployee()
    {
        $this->allow('employees.delete');

        $this->employee->delete();
        session()->flash('status', 'Employee removed.');

        return $this->redirectRoute('employees.index', navigate: true);
    }

    public function render()
    {
        $this->employee->load(['branch', 'department', 'designation', 'category', 'manager', 'user.roles']);
        $canSeeSalary = $this->canSeeSalary();

        return view('livewire.employees.show', [
            'canSeeSalary' => $canSeeSalary,
            'canUpdate' => auth()->user()->can('employees.update'),
            'canDelete' => auth()->user()->can('employees.delete'),
            'canManageDocs' => auth()->user()->can('employees.manage-documents'),
            'salaries' => $canSeeSalary && $this->tab === 'salary' ? $this->employee->salaries()->get() : collect(),
            'components' => $canSeeSalary && $this->tab === 'salary' ? $this->employee->components()->with('component')->get() : collect(),
            'componentOptions' => $canSeeSalary && $this->tab === 'salary' ? SalaryComponent::active()->orderBy('name')->get() : collect(),
            'documents' => $this->tab === 'documents' ? $this->employee->documents()->get() : collect(),
            'docTypes' => EmployeeDocument::TYPES,
        ]);
    }
}
