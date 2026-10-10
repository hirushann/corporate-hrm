<?php

namespace Tests\Feature;

use App\Exports\ReportExport;
use App\Livewire\Reports;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\User;
use App\Reports\EmployeeListReport;
use App\Reports\EpfEtfReport;
use App\Reports\HeadcountReport;
use App\Reports\MonthlyAttendanceReport;
use App\Reports\PayrollSummaryReport;
use App\Reports\ReportRegistry;
use App\Reports\SalaryTransferReport;
use App\Services\PayrollService;
use App\Support\Permissions;
use Carbon\Carbon;
use Database\Seeders\AttendanceSeeder;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\PayrollSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, OrganizationSeeder::class, AttendanceSeeder::class, PayrollSeeder::class]);
    }

    private function as(string $role): User
    {
        $user = User::factory()->create()->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    /** One employee on a 90,000 basic with perfect August 2026 attendance and a payroll run for it. */
    private function payrollFixture(string $status = 'approved', array $employee = []): array
    {
        $e = Employee::factory()->create($employee + [
            'join_date' => '2026-01-01', 'bank_name' => 'Test Bank', 'bank_branch' => 'Colombo', 'bank_account_number' => '0012345678',
            'bank_account_name' => 'N Perera', 'epf_number' => 'EPF-77', 'etf_number' => 'ETF-77', 'gender' => 'female',
        ]);
        $e->salaries()->create(['effective_from' => '2026-01-01', 'basic_salary' => 90000]);
        for ($d = Carbon::parse('2026-08-03'); $d->lte('2026-08-31'); $d->addDay()) {
            if (! $d->isWeekend()) {
                Punch::create(['device_user_id' => $e->employee_no, 'employee_id' => $e->id, 'punched_at' => $d->toDateString().' 08:30']);
                Punch::create(['device_user_id' => $e->employee_no, 'employee_id' => $e->id, 'punched_at' => $d->toDateString().' 17:30']);
            }
        }
        $run = app(PayrollService::class)->createRun('2026-08', User::factory()->create());
        $run->update(['status' => $status]);

        return [$e, $run];
    }

    public function test_every_registered_report_runs_with_default_filters(): void
    {
        $this->payrollFixture();
        $user = $this->as(Permissions::SUPER_ADMIN);

        foreach (ReportRegistry::all() as $key => $report) {
            $filters = $report->resolveFilters([]);
            $rows = $report->rows($filters, $user);
            foreach ($rows as $row) {
                $this->assertCount(count($report->headings()), $row, "$key row width");
            }
            $totals = $report->totals($rows);
            $totals && $this->assertCount(count($report->headings()), $totals, "$key totals width");
        }
    }

    public function test_payroll_summary_totals_match_the_payslips(): void
    {
        [, $run] = $this->payrollFixture();
        $user = $this->as(Permissions::PAYROLL_OFFICER);
        $report = new PayrollSummaryReport;

        $rows = $report->rows(['run' => (string) $run->id], $user);

        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows[0][1]);
        $this->assertSame(90000.0, $rows[0][3]);       // gross
        $this->assertSame(82800.0, $rows[0][5]);       // net
        $this->assertSame(7200.0, $rows[0][6]);        // EPF employee
        $this->assertSame(10800.0, $rows[0][7]);       // EPF employer
        $this->assertSame(2700.0, $rows[0][8]);        // ETF
    }

    public function test_epf_report_lists_members_with_contributions(): void
    {
        [, $run] = $this->payrollFixture();
        $report = new EpfEtfReport;
        $rows = $report->rows(['run' => (string) $run->id], $this->as(Permissions::PAYROLL_OFFICER));

        $this->assertSame('EPF-77', $rows[0][0]);
        $this->assertSame([90000.0, 7200.0, 10800.0, 18000.0, 2700.0], array_slice($rows[0], 4, 5));
        $this->assertSame(18000.0, $report->totals($rows)[7]);
    }

    public function test_statutory_and_transfer_reports_exclude_unapproved_runs(): void
    {
        [, $run] = $this->payrollFixture('draft');
        $user = $this->as(Permissions::PAYROLL_OFFICER);

        $this->assertCount(0, (new SalaryTransferReport)->rows(['run' => (string) $run->id], $user));
        $this->assertCount(0, (new EpfEtfReport)->rows(['run' => (string) $run->id], $user));
        $this->assertSame([], (new SalaryTransferReport)->filters()[0]['options']);
    }

    public function test_salary_transfer_shows_bank_details_and_flags_missing_ones(): void
    {
        $other = Employee::factory()->create(['join_date' => '2026-01-01', 'bank_name' => null]);
        $other->salaries()->create(['effective_from' => '2026-01-01', 'basic_salary' => 50000]);
        [$mine, $run] = $this->payrollFixture();

        $rows = (new SalaryTransferReport)->rows(['run' => (string) $run->id], $this->as(Permissions::PAYROLL_OFFICER));

        $row = $rows->firstWhere(0, $mine->employee_no);
        $this->assertSame(['Test Bank', 'Colombo', '0012345678', 'N Perera', 82800.0], array_slice($row, 2, 5));
        $this->assertSame('MISSING BANK DETAILS', $rows->firstWhere(0, $other->employee_no)[2]);
    }

    public function test_monthly_attendance_summary_counts_days(): void
    {
        [$e] = $this->payrollFixture();

        $rows = (new MonthlyAttendanceReport)->rows(['month' => '2026-08'], $this->as(Permissions::HR_MANAGER));

        $this->assertSame($e->employee_no, $rows[0][0]);
        $this->assertSame(21, $rows[0][3]);   // present
        $this->assertSame(0, $rows[0][4]);    // absent
    }

    public function test_headcount_splits_by_gender_and_category(): void
    {
        $dept = Department::firstOrCreate(['name' => 'Finance']);
        Employee::factory()->count(2)->create(['department_id' => $dept->id, 'gender' => 'male']);
        Employee::factory()->create(['department_id' => $dept->id, 'gender' => 'female']);

        $report = new HeadcountReport;
        $rows = $report->rows([], $this->as(Permissions::HR_MANAGER));

        $row = $rows->firstWhere(0, 'Finance');
        $this->assertSame([3, 2, 1, 0], array_slice($row, 1, 4));
        $this->assertCount(count($report->headings()), $report->totals($rows));
    }

    public function test_manager_reports_are_limited_to_their_team(): void
    {
        $manager = $this->as(Permissions::MANAGER);
        $boss = Employee::factory()->create(['user_id' => $manager->id]);
        Employee::factory()->create(['full_name' => 'My Report', 'manager_id' => $boss->id]);
        Employee::factory()->create(['full_name' => 'Somebody Else']);

        $rows = (new EmployeeListReport)->rows([], $manager);

        $this->assertSame(['My Report'], $rows->pluck(1)->all());
    }

    public function test_report_access_follows_permissions(): void
    {
        [, $run] = $this->payrollFixture();

        $this->as(Permissions::EMPLOYEE);
        $this->get(route('reports.index'))->assertForbidden();

        $this->as(Permissions::MANAGER);
        $this->get(route('reports.show', 'attendance-daily'))->assertOk();
        $this->get(route('reports.show', 'payroll-summary'))->assertForbidden();
        $this->get(route('reports.show', 'nope'))->assertForbidden();
        // Managers can view but not export.
        $this->get(route('reports.export', ['key' => 'attendance-daily', 'format' => 'xlsx']))->assertForbidden();

        $this->as(Permissions::PAYROLL_OFFICER);
        $this->get(route('reports.show', 'payroll-summary').'?f[run]='.$run->id)->assertOk()->assertSee('Payroll summary by department');
        $this->get(route('reports.show', 'headcount'))->assertForbidden();
    }

    public function test_exports_work_in_all_formats_and_are_audited(): void
    {
        [, $run] = $this->payrollFixture();
        $this->as(Permissions::PAYROLL_OFFICER);

        $this->get(route('reports.export', ['key' => 'salary-transfer', 'format' => 'csv', 'run' => $run->id]))->assertOk();
        $this->get(route('reports.export', ['key' => 'salary-transfer', 'format' => 'xlsx', 'run' => $run->id]))->assertOk();
        $this->get(route('reports.export', ['key' => 'salary-transfer', 'format' => 'pdf', 'run' => $run->id]))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('reports.export', ['key' => 'salary-transfer', 'format' => 'exe', 'run' => $run->id]))->assertNotFound();

        $this->assertDatabaseHas('activity_log', ['description' => 'report exported']);
    }

    public function test_csv_export_neutralises_spreadsheet_formulas(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://evil\")", ReportExport::safe('=HYPERLINK("http://evil")'));
        $this->assertSame("'+94771234567", ReportExport::safe('+94771234567'));
        $this->assertSame(-5.5, ReportExport::safe(-5.5));
        $this->assertSame('Kamal', ReportExport::safe('Kamal'));
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->as(Permissions::HR_MANAGER);

        $this->get(route('reports.export', ['key' => 'attendance-monthly', 'format' => 'csv', 'month' => 'garbage']))->assertStatus(302);
    }

    public function test_report_screen_filters_update_the_table(): void
    {
        $dept = Department::firstOrCreate(['name' => 'Finance']);
        Employee::factory()->create(['full_name' => 'Finance Person', 'department_id' => $dept->id]);
        Employee::factory()->create(['full_name' => 'Other Person']);
        $this->as(Permissions::HR_MANAGER);

        Livewire::test(Reports\Show::class, ['key' => 'employee-list'])
            ->assertSee('Finance Person')->assertSee('Other Person')
            ->set('f.department', (string) $dept->id)
            ->assertSee('Finance Person')->assertDontSee('Other Person');
    }

    public function test_dashboard_shows_role_appropriate_stats(): void
    {
        $this->as(Permissions::HR_MANAGER);
        Employee::factory()->count(3)->create();

        $this->get(route('dashboard'))->assertOk()->assertSee('Leave requests to review')->assertSee('Payroll awaiting approval');

        $this->as(Permissions::MANAGER);
        $this->get(route('dashboard'))->assertOk()->assertSee('Leave requests to review')->assertDontSee('Payroll awaiting approval');
    }
}
