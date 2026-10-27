<?php

namespace App\Livewire\Employees;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeCategory;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
#[Title('Employee')]
class Form extends Component
{
    use AuthorizesPermission;

    public ?Employee $employee = null;

    public array $f = [];

    // Optional on create: login account + opening salary
    public bool $createLogin = false;

    public string $loginEmail = '';

    public string $loginRole = Permissions::EMPLOYEE;

    public string $basicSalary = '';

    public string $fixedAllowances = '0';

    private const FIELDS = [
        'full_name', 'biometric_id', 'nic', 'date_of_birth', 'gender', 'marital_status', 'phone', 'personal_email', 'address',
        'emergency_contact_name', 'emergency_contact_phone',
        'branch_id', 'department_id', 'designation_id', 'employee_category_id', 'manager_id',
        'join_date', 'confirmation_date', 'resignation_date', 'status',
        'bank_name', 'bank_branch', 'bank_account_number', 'bank_account_name',
        'epf_number', 'etf_number', 'tax_tin', 'epf_applicable', 'etf_applicable', 'apit_applicable', 'ot_eligible',
    ];

    public function mount(?Employee $employee = null): void
    {
        if ($employee?->exists) {
            $this->allow('employees.update');
            $this->employee = $employee;
            foreach (self::FIELDS as $field) {
                $value = $employee->{$field};
                $this->f[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
            }
        } else {
            $this->allow('employees.create');
            $this->employee = null;
            $this->f = array_fill_keys(self::FIELDS, '');
            $this->f['status'] = 'active';
            $this->f['join_date'] = now()->toDateString();
            $this->f['epf_applicable'] = $this->f['etf_applicable'] = true;
            $this->f['apit_applicable'] = false;
            $this->f['ot_eligible'] = true;
        }
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        $attributes = [];
        foreach (self::FIELDS as $field) {
            $attributes['f.'.$field] = str_replace('_', ' ', preg_replace('/_id$/', '', $field));
        }

        return $attributes;
    }

    protected function rules(): array
    {
        $id = $this->employee?->id;
        $exists = fn (string $table) => Rule::exists($table, 'id');

        $rules = [
            'f.full_name' => 'required|string|max:255',
            'f.biometric_id' => ['nullable', 'string', 'max:30', Rule::unique('employees', 'biometric_id')->ignore($id)],
            'f.nic' => ['nullable', 'string', 'max:20', Rule::unique('employees', 'nic')->ignore($id)],
            'f.date_of_birth' => 'nullable|date|before:today',
            'f.gender' => 'nullable|in:male,female,other',
            'f.marital_status' => 'nullable|in:single,married,divorced,widowed',
            'f.phone' => 'nullable|string|max:30',
            'f.personal_email' => 'nullable|email|max:255',
            'f.address' => 'nullable|string|max:255',
            'f.emergency_contact_name' => 'nullable|string|max:255',
            'f.emergency_contact_phone' => 'nullable|string|max:30',
            'f.branch_id' => ['required', $exists('branches')],
            'f.department_id' => ['required', $exists('departments')],
            'f.designation_id' => ['required', $exists('designations')],
            'f.employee_category_id' => ['required', $exists('employee_categories')],
            'f.manager_id' => array_filter(['nullable', $exists('employees'), $id ? Rule::notIn([$id]) : null]),
            'f.join_date' => 'required|date',
            'f.confirmation_date' => 'nullable|date|after_or_equal:f.join_date',
            'f.resignation_date' => 'nullable|date|after_or_equal:f.join_date',
            'f.status' => ['required', Rule::in(Employee::STATUSES)],
            'f.bank_name' => 'nullable|string|max:255',
            'f.bank_branch' => 'nullable|string|max:255',
            'f.bank_account_number' => 'nullable|string|max:50',
            'f.bank_account_name' => 'nullable|string|max:255',
            'f.epf_number' => 'nullable|string|max:30',
            'f.etf_number' => 'nullable|string|max:30',
            'f.tax_tin' => 'nullable|string|max:30',
            'f.epf_applicable' => 'boolean',
            'f.etf_applicable' => 'boolean',
            'f.apit_applicable' => 'boolean',
            'f.ot_eligible' => 'boolean',
        ];

        if (! $this->employee && $this->canCreateLogin()) {
            $rules['loginEmail'] = ['exclude_unless:createLogin,true', 'required', 'email', Rule::unique('users', 'email')];
            $rules['loginRole'] = ['exclude_unless:createLogin,true', 'required', Rule::in($this->assignableRoles())];
        }

        if (! $this->employee && $this->canSeeSalary()) {
            $rules['basicSalary'] = 'nullable|numeric|min:0|max:99999999';
            $rules['fixedAllowances'] = 'nullable|numeric|min:0|max:99999999';
        }

        return $rules;
    }

    /** Only a Super Admin may hand out the Super Admin role. */
    private function assignableRoles(): array
    {
        return Role::orderBy('name')
            ->pluck('name')
            ->reject(fn ($r) => $r === Permissions::SUPER_ADMIN && ! auth()->user()->hasRole(Permissions::SUPER_ADMIN))
            ->values()->all();
    }

    private function canCreateLogin(): bool
    {
        return auth()->user()->can('users.manage');
    }

    private function canSeeSalary(): bool
    {
        return auth()->user()->can('employees.view-salary');
    }

    public function save()
    {
        $this->allow($this->employee ? 'employees.update' : 'employees.create');

        $this->validate();

        $data = collect(Arr::only($this->f, self::FIELDS))->map(fn ($v) => $v === '' ? null : $v)->all();
        // Required columns must keep real values; booleans must not become null.
        foreach (['epf_applicable', 'etf_applicable', 'apit_applicable', 'ot_eligible'] as $flag) {
            $data[$flag] = (bool) $data[$flag];
        }
        // Bank/TIN are only editable by people who can see salary data.
        if (! $this->canSeeSalary()) {
            unset($data['bank_name'], $data['bank_branch'], $data['bank_account_number'], $data['bank_account_name'], $data['tax_tin']);
        }

        if ($this->employee) {
            $this->employee->update($data);
            $employee = $this->employee;
            $message = 'Employee updated.';
        } else {
            $employee = DB::transaction(function () use ($data) {
                $employee = Employee::create($data + ['employee_no' => Employee::nextEmployeeNo()]);

                if ($this->canSeeSalary() && $this->basicSalary !== '') {
                    $employee->salaries()->create([
                        'effective_from' => $data['join_date'],
                        'basic_salary' => $this->basicSalary,
                        'fixed_allowances' => $this->fixedAllowances ?: 0,
                        'reason' => 'Opening salary',
                        'created_by' => auth()->id(),
                    ]);
                }

                if ($this->createLogin && $this->canCreateLogin()) {
                    $user = User::create([
                        'name' => $employee->full_name,
                        'email' => $this->loginEmail,
                        'password' => Str::password(32),
                        'email_verified_at' => now(),
                    ]);
                    $user->assignRole($this->loginRole);
                    $employee->update(['user_id' => $user->id]);
                }

                return $employee;
            });

            if ($employee->user_id) {
                Password::sendResetLink(['email' => $this->loginEmail]);
            }
            $message = 'Employee created.'.($employee->user_id ? ' A password-setup link was emailed to the new user.' : '');
        }

        session()->flash('status', $message);

        return $this->redirectRoute('employees.show', $employee, navigate: true);
    }

    public function render()
    {
        return view('livewire.employees.form', [
            'branches' => Branch::active()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::active()->orderBy('name')->get(['id', 'name']),
            'designations' => Designation::active()->orderBy('name')->get(['id', 'name']),
            'categories' => EmployeeCategory::active()->orderBy('name')->get(['id', 'name']),
            'managers' => Employee::working()->when($this->employee, fn ($q) => $q->whereKeyNot($this->employee->id))->orderBy('full_name')->get(['id', 'employee_no', 'full_name']),
            'roles' => $this->assignableRoles(),
            'statuses' => Employee::STATUSES,
            'canCreateLogin' => $this->canCreateLogin(),
            'canSeeSalary' => $this->canSeeSalary(),
        ]);
    }
}
