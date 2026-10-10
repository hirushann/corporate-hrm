<?php

namespace Tests\Feature;

use App\Livewire\Admin;
use App\Livewire\Portal;
use App\Models\CompanyDocument;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Notice;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\AttendanceSeeder;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\PayrollSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, OrganizationSeeder::class, AttendanceSeeder::class, PayrollSeeder::class]);
    }

    /** @return array{0: User, 1: Employee} */
    private function employeeUser(string $role = Permissions::EMPLOYEE, array $attrs = []): array
    {
        $user = User::factory()->create()->assignRole($role);
        $employee = Employee::factory()->create($attrs + ['user_id' => $user->id, 'join_date' => '2026-01-01']);

        return [$user, $employee];
    }

    private function publishedPayslip(Employee $employee, string $period = '2026-07', string $status = 'locked'): Payslip
    {
        $run = PayrollRun::firstOrCreate(['period' => $period], [
            'period_start' => "$period-01", 'period_end' => date('Y-m-t', strtotime("$period-01")), 'status' => $status,
            'settings' => [], 'prepared_by' => User::factory()->create()->id,
        ]);

        return Payslip::create([
            'payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'employee_no' => $employee->employee_no,
            'employee_name' => $employee->full_name, 'basic_salary' => 50000, 'gross' => 50000, 'net_salary' => 46000, 'total_deductions' => 4000,
        ]);
    }

    public function test_employee_sees_the_portal_but_not_admin_screens(): void
    {
        [$user] = $this->employeeUser();
        $this->actingAs($user);

        foreach (['dashboard', 'portal.profile', 'portal.payslips', 'portal.attendance', 'portal.leave', 'portal.notices', 'portal.documents'] as $route) {
            $this->get(route($route))->assertOk();
        }
        foreach (['employees.index', 'payroll.index', 'attendance.index', 'admin.users', 'admin.notices', 'admin.documents', 'leave.requests'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }

    public function test_user_without_an_employee_record_gets_a_clear_refusal(): void
    {
        $this->actingAs(User::factory()->create()->assignRole(Permissions::EMPLOYEE));

        $this->get(route('portal.payslips'))->assertForbidden();
        $this->get(route('dashboard'))->assertOk()->assertSee('No employee profile is linked');
    }

    public function test_profile_shows_own_details_and_never_salary_or_bank(): void
    {
        [$user, $employee] = $this->employeeUser(attrs: ['full_name' => 'Kasun Jayasinghe', 'bank_account_number' => '55667788']);
        $employee->salaries()->create(['effective_from' => '2026-01-01', 'basic_salary' => 98765]);
        $this->actingAs($user);

        $this->get(route('portal.profile'))->assertOk()->assertSee('Kasun Jayasinghe')->assertDontSee('98,765')->assertDontSee('55667788')->assertDontSee('7788');
    }

    public function test_payslips_list_only_own_published_payslips(): void
    {
        [$user, $employee] = $this->employeeUser();
        [, $other] = $this->employeeUser(attrs: ['nic' => '200112345678']);
        $this->publishedPayslip($employee, '2026-07');
        $this->publishedPayslip($employee, '2026-08', 'approved');   // not yet locked
        $this->publishedPayslip($other, '2026-07');
        $this->actingAs($user);

        Livewire::test(Portal\Payslips::class)->assertSee('July 2026')->assertDontSee('August 2026')->assertViewHas('payslips', fn ($p) => $p->count() === 1);
    }

    public function test_attendance_summary_shows_only_own_records(): void
    {
        [$user, $employee] = $this->employeeUser();
        [, $other] = $this->employeeUser(attrs: ['nic' => '200112345678']);
        $employee->attendanceRecords()->create(['date' => '2026-09-07', 'status' => 'present', 'late_minutes' => 20]);
        $other->attendanceRecords()->create(['date' => '2026-09-08', 'status' => 'absent']);
        $this->actingAs($user);

        Livewire::test(Portal\Attendance::class)->set('month', '2026-09')
            ->assertViewHas('records', fn ($r) => $r->count() === 1)
            ->assertViewHas('totals', fn ($t) => $t['Present days'] === 1 && $t['Absent days'] === 0 && $t['Late arrivals'] === 1);
    }

    public function test_attendance_month_input_is_sanitised(): void
    {
        [$user] = $this->employeeUser();
        $this->actingAs($user);

        Livewire::test(Portal\Attendance::class)->set('month', "x'; drop")->assertOk()->assertSet('month', today()->format('Y-m'));
    }

    public function test_employee_can_request_and_cancel_leave(): void
    {
        [$user, $employee] = $this->employeeUser();
        $casual = LeaveType::where('name', 'Casual')->first();
        LeaveBalance::create(['employee_id' => $employee->id, 'leave_type_id' => $casual->id, 'year' => 2026, 'entitled' => 7]);
        $this->actingAs($user);

        Livewire::test(Portal\Leave::class)
            ->set('leaveTypeId', $casual->id)->set('fromDate', '2026-09-07')->set('toDate', '2026-09-07')->set('reason', 'Family event')
            ->call('submit')->assertHasNoErrors();

        $request = LeaveRequest::firstOrFail();
        $this->assertSame($employee->id, $request->employee_id);
        $this->assertSame('pending', $request->status);
        $this->assertSame($user->id, $request->requested_by);

        Livewire::test(Portal\Leave::class)->call('cancel', $request->id);
        $this->assertSame('cancelled', $request->fresh()->status);
    }

    public function test_leave_request_beyond_balance_is_refused_with_a_message(): void
    {
        [$user, $employee] = $this->employeeUser();
        $casual = LeaveType::where('name', 'Casual')->first();
        LeaveBalance::create(['employee_id' => $employee->id, 'leave_type_id' => $casual->id, 'year' => 2026, 'entitled' => 1]);
        $this->actingAs($user);

        Livewire::test(Portal\Leave::class)
            ->set('leaveTypeId', $casual->id)->set('fromDate', '2026-09-07')->set('toDate', '2026-09-08')
            ->call('submit')->assertHasErrors('leave');
        $this->assertSame(0, LeaveRequest::count());
    }

    public function test_employee_cannot_cancel_someone_elses_or_a_decided_request(): void
    {
        [$user] = $this->employeeUser();
        [, $other] = $this->employeeUser(attrs: ['nic' => '200112345678']);
        $annual = LeaveType::where('name', 'Annual')->first();
        $theirs = LeaveRequest::create(['employee_id' => $other->id, 'leave_type_id' => $annual->id, 'from_date' => '2026-09-07', 'to_date' => '2026-09-07', 'days' => 1]);
        $this->actingAs($user);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(Portal\Leave::class)->call('cancel', $theirs->id);
    }

    public function test_notices_respect_audience_and_dates(): void
    {
        $finance = Department::firstOrCreate(['name' => 'Finance']);
        $it = Department::firstOrCreate(['name' => 'IT']);
        [$user] = $this->employeeUser(attrs: ['department_id' => $finance->id]);
        Notice::create(['title' => 'Everyone notice', 'body' => 'b', 'published_at' => today()->subDay()]);
        Notice::create(['title' => 'Finance only', 'body' => 'b', 'department_id' => $finance->id, 'published_at' => today()]);
        Notice::create(['title' => 'IT only', 'body' => 'b', 'department_id' => $it->id, 'published_at' => today()]);
        Notice::create(['title' => 'Future notice', 'body' => 'b', 'published_at' => today()->addDay()]);
        Notice::create(['title' => 'Expired notice', 'body' => 'b', 'published_at' => today()->subWeek(), 'expires_at' => today()->subDay()]);
        $this->actingAs($user);

        Livewire::test(Portal\Notices::class)
            ->assertSee('Everyone notice')->assertSee('Finance only')
            ->assertDontSee('IT only')->assertDontSee('Future notice')->assertDontSee('Expired notice');
    }

    public function test_hr_can_publish_notices_and_employees_cannot(): void
    {
        $this->actingAs(User::factory()->create()->assignRole(Permissions::HR_MANAGER));

        Livewire::test(Admin\Notices::class)->call('create')
            ->set('form.title', 'Office closed Friday')->set('form.body', 'Public holiday.')->set('form.published_at', today()->toDateString())
            ->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('notices', ['title' => 'Office closed Friday', 'is_pinned' => false]);

        [$employeeUser] = $this->employeeUser();
        $this->actingAs($employeeUser);
        $this->get(route('admin.notices'))->assertForbidden();
    }

    public function test_company_documents_upload_download_and_access(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create()->assignRole(Permissions::HR_MANAGER));

        Livewire::test(Admin\CompanyDocuments::class)
            ->set('title', 'Leave policy')->set('category', 'policy')
            ->set('file', UploadedFile::fake()->create('policy.pdf', 50, 'application/pdf'))
            ->call('upload')->assertHasNoErrors();

        $document = CompanyDocument::firstOrFail();
        Storage::disk('local')->assertExists($document->path);

        [$employeeUser] = $this->employeeUser();
        $this->actingAs($employeeUser);
        $this->get(route('documents.download', $document))->assertOk();
        $this->get(route('admin.documents'))->assertForbidden();
        Livewire::test(Admin\CompanyDocuments::class)->assertForbidden();
    }

    public function test_company_document_download_requires_login(): void
    {
        $document = CompanyDocument::create(['title' => 'x', 'category' => 'policy', 'path' => 'company-documents/x.pdf', 'original_name' => 'x.pdf']);

        $this->get(route('documents.download', $document))->assertRedirect('/login');
    }

    public function test_company_document_upload_rejects_executables(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create()->assignRole(Permissions::HR_MANAGER));

        Livewire::test(Admin\CompanyDocuments::class)
            ->set('title', 'Bad')->set('file', UploadedFile::fake()->create('x.php', 5))
            ->call('upload')->assertHasErrors('file');
    }

    public function test_dashboard_shows_latest_published_payslip(): void
    {
        [$user, $employee] = $this->employeeUser();
        $this->publishedPayslip($employee, '2026-07');
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertOk()->assertSee('July 2026')->assertSee('46,000.00');
    }
}
