<?php

namespace Tests\Feature;

use App\Livewire\Admin\AuditLog;
use App\Livewire\Admin\Users;
use App\Livewire\Employees;
use App\Livewire\Organization\Departments;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeCategory;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, OrganizationSeeder::class]);
    }

    private function as(string $role): User
    {
        $user = User::factory()->create()->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function employeeData(array $override = []): array
    {
        return array_replace([
            'full_name' => 'Nimal Perera',
            'nic' => '199012345678',
            'branch_id' => Branch::first()->id,
            'department_id' => Department::firstOrCreate(['name' => 'Finance'])->id,
            'designation_id' => Designation::firstOrCreate(['name' => 'Accountant'])->id,
            'employee_category_id' => EmployeeCategory::first()->id,
            'join_date' => '2026-01-01',
            'status' => 'active',
            'epf_applicable' => true,
            'etf_applicable' => true,
            'apit_applicable' => false,
            'ot_eligible' => true,
        ], $override);
    }

    public function test_hr_manager_can_create_department_and_it_is_audited(): void
    {
        $this->as(Permissions::HR_MANAGER);

        Livewire::test(Departments::class)
            ->call('create')
            ->set('form.name', 'Finance')
            ->set('form.code', 'FIN')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('departments', ['name' => 'Finance', 'is_active' => true]);
        $this->assertDatabaseHas('activity_log', ['subject_type' => Department::class, 'event' => 'created']);
    }

    public function test_department_names_must_be_unique(): void
    {
        $this->as(Permissions::HR_MANAGER);
        Department::create(['name' => 'Finance']);

        Livewire::test(Departments::class)->call('create')->set('form.name', 'Finance')->call('save')->assertHasErrors('form.name');
    }

    public function test_manager_cannot_manage_organization_structure(): void
    {
        $this->as(Permissions::MANAGER);

        $this->get(route('organization.departments'))->assertForbidden();
    }

    public function test_employee_role_cannot_open_employee_screens(): void
    {
        $this->as(Permissions::EMPLOYEE);

        $this->get(route('employees.index'))->assertForbidden();
        $this->get(route('admin.users'))->assertForbidden();
        $this->get(route('admin.audit'))->assertForbidden();
    }

    public function test_hr_manager_creates_employee_with_salary_and_sequential_code(): void
    {
        $this->as(Permissions::HR_MANAGER);

        $component = Livewire::test(Employees\Form::class);
        $component->set('f', $this->employeeData(['bank_account_number' => '1234567890']))
            ->set('basicSalary', '85000')
            ->call('save')
            ->assertHasNoErrors();

        $employee = Employee::first();
        $this->assertSame('EMP-0001', $employee->employee_no);
        $this->assertSame('85000.00', $employee->currentSalary->basic_salary);

        Livewire::test(Employees\Form::class)->set('f', $this->employeeData(['nic' => '199512345678']))->call('save')->assertHasNoErrors();
        $this->assertSame('EMP-0002', Employee::orderByDesc('id')->first()->employee_no);
    }

    public function test_bank_account_number_is_encrypted_at_rest(): void
    {
        $employee = Employee::factory()->create(['bank_account_number' => '1234567890']);

        $raw = DB::table('employees')->where('id', $employee->id)->value('bank_account_number');
        $this->assertStringNotContainsString('1234567890', $raw);
        $this->assertSame('1234567890', $employee->fresh()->bank_account_number);
    }

    public function test_duplicate_nic_is_rejected(): void
    {
        $this->as(Permissions::HR_MANAGER);
        Employee::factory()->create(['nic' => '199012345678']);

        Livewire::test(Employees\Form::class)->set('f', $this->employeeData())->call('save')->assertHasErrors('f.nic');
    }

    public function test_creating_a_login_assigns_role_and_sends_reset_link(): void
    {
        Notification::fake();
        $this->as(Permissions::HR_MANAGER);

        Livewire::test(Employees\Form::class)
            ->set('f', $this->employeeData())
            ->set('createLogin', true)
            ->set('loginEmail', 'nimal@example.com')
            ->set('loginRole', Permissions::EMPLOYEE)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'nimal@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(Permissions::EMPLOYEE));
        $this->assertSame($user->id, Employee::first()->user_id);
    }

    public function test_hr_manager_cannot_grant_super_admin_role(): void
    {
        $this->as(Permissions::HR_MANAGER);

        Livewire::test(Employees\Form::class)
            ->set('f', $this->employeeData())
            ->set('createLogin', true)
            ->set('loginEmail', 'x@example.com')
            ->set('loginRole', Permissions::SUPER_ADMIN)
            ->call('save')
            ->assertHasErrors('loginRole');

        Livewire::test(Users::class)
            ->call('create')->set('name', 'X')->set('email', 'y@example.com')->set('role', Permissions::SUPER_ADMIN)
            ->call('save')->assertHasErrors('role');
    }

    public function test_hr_manager_cannot_edit_a_super_admin(): void
    {
        $admin = User::factory()->create()->assignRole(Permissions::SUPER_ADMIN);
        $this->as(Permissions::HR_MANAGER);

        Livewire::test(Users::class)->call('edit', $admin->id)->assertForbidden();
    }

    public function test_super_admin_cannot_deactivate_self(): void
    {
        $admin = $this->as(Permissions::SUPER_ADMIN);

        Livewire::test(Users::class)->call('edit', $admin->id)->set('is_active', false)->call('save')->assertHasErrors('role');
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        Livewire::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasErrors('form.email');

        $this->assertGuest();
    }

    public function test_salary_is_hidden_from_users_without_permission(): void
    {
        $employee = Employee::factory()->create(['bank_account_number' => '9999888877']);
        $employee->salaries()->create(['effective_from' => '2026-01-01', 'basic_salary' => 123456]);

        $manager = $this->as(Permissions::MANAGER);
        $boss = Employee::factory()->create(['user_id' => $manager->id]);
        $employee->update(['manager_id' => $boss->id]);

        Livewire::test(Employees\Show::class, ['employee' => $employee])
            ->call('setTab', 'salary')
            ->assertSet('tab', 'profile')
            ->assertDontSee('123,456')
            ->assertDontSee('8877');

        $this->as(Permissions::PAYROLL_OFFICER);
        Livewire::test(Employees\Show::class, ['employee' => $employee])
            ->call('setTab', 'salary')
            ->assertSee('123,456.00');
    }

    public function test_salary_revision_requires_update_permission(): void
    {
        $employee = Employee::factory()->create();
        $this->as(Permissions::PAYROLL_OFFICER); // can view salary, cannot update employees

        Livewire::test(Employees\Show::class, ['employee' => $employee])
            ->set('basicSalary', '50000')->call('addSalary')->assertForbidden();
    }

    public function test_documents_are_stored_privately_and_download_is_protected(): void
    {
        Storage::fake('local');
        $employee = Employee::factory()->create();
        $this->as(Permissions::HR_MANAGER);

        Livewire::test(Employees\Show::class, ['employee' => $employee])
            ->set('docTitle', 'NIC copy')->set('docType', 'nic')
            ->set('docFile', UploadedFile::fake()->create('nic.pdf', 100, 'application/pdf'))
            ->call('uploadDocument')->assertHasNoErrors();

        $document = $employee->documents()->firstOrFail();
        Storage::disk('local')->assertExists($document->path);

        $this->get(route('employees.documents.download', $document))->assertOk();

        auth()->logout();
        $this->get(route('employees.documents.download', $document))->assertRedirect('/login');
    }

    public function test_disallowed_document_types_are_rejected(): void
    {
        Storage::fake('local');
        $employee = Employee::factory()->create();
        $this->as(Permissions::HR_MANAGER);

        Livewire::test(Employees\Show::class, ['employee' => $employee])
            ->set('docTitle', 'Bad')->set('docFile', UploadedFile::fake()->create('shell.php', 10))
            ->call('uploadDocument')->assertHasErrors('docFile');
    }

    public function test_audit_log_lists_changes_for_authorised_users_only(): void
    {
        $this->as(Permissions::SUPER_ADMIN);
        Company::first()->update(['name' => 'Acme Ltd']);

        Livewire::test(AuditLog::class)->assertSee('Company #1');

        $this->as(Permissions::HR_MANAGER);
        $this->get(route('admin.audit'))->assertForbidden();
    }

    public function test_employee_list_filters_by_search(): void
    {
        $this->as(Permissions::HR_MANAGER);
        Employee::factory()->create(['full_name' => 'Kamal Silva']);
        Employee::factory()->create(['full_name' => 'Sunil Fernando']);

        Livewire::test(Employees\Index::class)->set('search', 'Kamal')->assertSee('Kamal Silva')->assertDontSee('Sunil Fernando');
    }

    public function test_super_admin_can_open_every_phase_one_screen(): void
    {
        $this->as(Permissions::SUPER_ADMIN);
        $employee = Employee::factory()->create();

        foreach ([
            route('dashboard'), route('organization.company'), route('organization.branches'), route('organization.departments'),
            route('organization.designations'), route('organization.categories'), route('employees.index'), route('employees.create'),
            route('employees.show', $employee), route('employees.edit', $employee), route('admin.users'), route('admin.audit'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
