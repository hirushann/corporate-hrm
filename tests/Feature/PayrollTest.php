<?php

namespace Tests\Feature;

use App\Livewire\Payroll;
use App\Models\Employee;
use App\Models\EmployeeComponent;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Models\PayrollSetting;
use App\Models\Payslip;
use App\Models\Punch;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\PayrollService;
use App\Support\Permissions;
use Carbon\Carbon;
use Database\Seeders\AttendanceSeeder;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\PayrollSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * August 2026: 31 days, Sat/Sun off (Aug 1 is a Saturday), 21 working days.
 * Defaults: no-pay = basic/30 per day, OT hour = basic/240 x 1.5 (weekday) or 2.0 (off day), EPF 8%/12%, ETF 3%.
 */
class PayrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, OrganizationSeeder::class, AttendanceSeeder::class, PayrollSeeder::class]);
    }

    private function employee(float $basic = 90000, array $attrs = [], float $allowances = 0): Employee
    {
        $e = Employee::factory()->create($attrs + ['join_date' => '2026-01-01']);
        $e->salaries()->create(['effective_from' => '2026-01-01', 'basic_salary' => $basic, 'fixed_allowances' => $allowances]);

        return $e;
    }

    private function as(string $role): User
    {
        $user = User::factory()->create()->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    /** Give the employee a normal 08:30-17:30 punch pair on every weekday of August 2026 except the listed dates. */
    private function fullAttendance(Employee $e, array $skip = [], array $extra = []): void
    {
        for ($d = Carbon::parse('2026-08-03'); $d->lte('2026-08-31'); $d->addDay()) {
            if ($d->isWeekend() || in_array($d->toDateString(), $skip, true)) {
                continue;
            }
            Punch::create(['device_user_id' => $e->employee_no, 'employee_id' => $e->id, 'punched_at' => $d->toDateString().' 08:30']);
            Punch::create(['device_user_id' => $e->employee_no, 'employee_id' => $e->id, 'punched_at' => $d->toDateString().' '.($extra[$d->toDateString()] ?? '17:30')]);
        }
    }

    private function line(Payslip $p, string $code): ?float
    {
        $line = $p->lines()->where('code', $code)->first();

        return $line ? (float) $line->amount : null;
    }

    private function makeRun(): PayrollRun
    {
        return app(PayrollService::class)->createRun('2026-08', User::factory()->create());
    }

    public function test_full_attendance_pays_basic_with_epf_and_etf(): void
    {
        $e = $this->employee(90000);
        $this->fullAttendance($e);

        $p = $this->makeRun()->payslips()->firstWhere('employee_id', $e->id);

        $this->assertSame(90000.0, $this->line($p, 'BASIC'));
        $this->assertNull($this->line($p, 'NOPAY'));
        $this->assertSame(7200.0, $this->line($p, 'EPF_EE'));    // 8% of 90,000
        $this->assertSame(10800.0, $this->line($p, 'EPF_ER'));   // 12%
        $this->assertSame(2700.0, $this->line($p, 'ETF_ER'));    // 3%
        $this->assertEquals(90000.00, $p->gross);
        $this->assertEquals(82800.00, $p->net_salary);
        $this->assertEquals(13500.00, $p->employer_contributions);
    }

    public function test_absent_days_reduce_pay_by_basic_over_thirty_and_epf_base(): void
    {
        $e = $this->employee(90000);
        $this->fullAttendance($e, skip: ['2026-08-05', '2026-08-06']);

        $p = $this->makeRun()->payslips()->firstWhere('employee_id', $e->id);

        $this->assertEquals(2.0, $p->no_pay_days);
        $this->assertSame(6000.0, $this->line($p, 'NOPAY'));     // 2 x 3,000
        $this->assertEquals(84000.0, $p->epf_base);              // 90,000 - 6,000
        $this->assertSame(6720.0, $this->line($p, 'EPF_EE'));    // 8% of 84,000
        $this->assertEquals(90000 - 6000 - 6720, $p->net_salary);
    }

    public function test_overtime_uses_weekday_and_off_day_multipliers(): void
    {
        $e = $this->employee(24000);                              // hourly = 24,000 / 240 = 100
        $this->fullAttendance($e, extra: ['2026-08-04' => '19:30']); // 2h OT on a weekday
        // Saturday 3 hours
        Punch::create(['device_user_id' => $e->employee_no, 'employee_id' => $e->id, 'punched_at' => '2026-08-08 09:00']);
        Punch::create(['device_user_id' => $e->employee_no, 'employee_id' => $e->id, 'punched_at' => '2026-08-08 12:00']);

        $p = $this->makeRun()->payslips()->firstWhere('employee_id', $e->id);

        $this->assertSame(300.0, $this->line($p, 'OT_WD'));      // 2 h x 100 x 1.5
        $this->assertSame(600.0, $this->line($p, 'OT_HD'));      // 3 h x 100 x 2.0
        $this->assertEquals(24900.00, $p->gross);
    }

    public function test_ot_ineligible_employee_gets_no_overtime_and_a_warning(): void
    {
        $e = $this->employee(24000, ['ot_eligible' => false]);
        $this->fullAttendance($e, extra: ['2026-08-04' => '19:30']);

        $run = $this->makeRun();
        $p = $run->payslips()->firstWhere('employee_id', $e->id);

        $this->assertNull($this->line($p, 'OT_WD'));
        $this->assertStringContainsString('not OT eligible', implode(' ', $run->warnings));
    }

    public function test_unpaid_leave_is_no_pay_but_paid_leave_is_not(): void
    {
        $e = $this->employee(90000);
        $annual = LeaveType::where('name', 'Annual')->first();
        $noPay = LeaveType::where('name', 'No Pay')->first();
        LeaveRequest::create(['employee_id' => $e->id, 'leave_type_id' => $annual->id, 'from_date' => '2026-08-10', 'to_date' => '2026-08-10', 'days' => 1, 'status' => 'approved']);
        LeaveRequest::create(['employee_id' => $e->id, 'leave_type_id' => $noPay->id, 'from_date' => '2026-08-11', 'to_date' => '2026-08-12', 'days' => 2, 'status' => 'approved']);
        $this->fullAttendance($e, skip: ['2026-08-10', '2026-08-11', '2026-08-12']);

        $p = $this->makeRun()->payslips()->firstWhere('employee_id', $e->id);

        $this->assertEquals(2.0, $p->no_pay_days);
        $this->assertSame(6000.0, $this->line($p, 'NOPAY'));
    }

    public function test_mid_month_joiner_is_prorated_by_calendar_days(): void
    {
        $e = $this->employee(31000, ['join_date' => '2026-08-16']);   // 16 of 31 days
        for ($d = Carbon::parse('2026-08-17'); $d->lte('2026-08-31'); $d->addDay()) {
            if (! $d->isWeekend()) {
                Punch::create(['device_user_id' => $e->employee_no, 'employee_id' => $e->id, 'punched_at' => $d->toDateString().' 08:30']);
                Punch::create(['device_user_id' => $e->employee_no, 'employee_id' => $e->id, 'punched_at' => $d->toDateString().' 17:30']);
            }
        }

        $p = $this->makeRun()->payslips()->firstWhere('employee_id', $e->id);

        $this->assertSame(16000.0, $this->line($p, 'BASIC'));    // 31,000 x 16/31
        $this->assertEquals(0.0, $p->no_pay_days);                // Sat 15 / Sun 16 are off; joined on the 16th
    }

    public function test_allowances_and_components_feed_gross_and_epf_only_when_configured(): void
    {
        $e = $this->employee(90000, allowances: 5000);
        $bra = SalaryComponent::where('name', 'Budgetary Relief Allowance')->first();
        $loan = SalaryComponent::where('name', 'Loan recovery')->first();
        EmployeeComponent::create(['employee_id' => $e->id, 'salary_component_id' => $bra->id, 'amount' => 10000, 'effective_from' => '2026-01-01']);
        EmployeeComponent::create(['employee_id' => $e->id, 'salary_component_id' => $loan->id, 'amount' => 2500, 'effective_from' => '2026-01-01']);
        $this->fullAttendance($e);

        $p = $this->makeRun()->payslips()->firstWhere('employee_id', $e->id);

        $this->assertEquals(105000.0, $p->gross);                 // 90,000 + 5,000 + 10,000
        $this->assertSame(2500.0, $this->line($p, 'DED'));
        $this->assertEquals(90000.0, $p->epf_base);               // default: basic only
        $this->assertEquals(105000 - 2500 - 7200, $p->net_salary);

        // With the setting on, the BRA (EPF-applicable) joins the base; the plain fixed allowance does not.
        PayrollSetting::current()->update(['epf_includes_allowances' => true]);
        PayrollRun::query()->delete();
        $p2 = $this->makeRun()->payslips()->firstWhere('employee_id', $e->id);
        $this->assertEquals(100000.0, $p2->epf_base);
    }

    public function test_employee_without_salary_is_skipped_with_a_warning(): void
    {
        Employee::factory()->create(['join_date' => '2026-01-01']);

        $run = $this->makeRun();

        $this->assertSame(0, $run->payslips()->count());
        $this->assertStringContainsString('no salary on file', implode(' ', $run->warnings));
    }

    public function test_cannot_run_payroll_for_an_unfinished_or_duplicate_month(): void
    {
        $this->expectException(ValidationException::class);
        app(PayrollService::class)->createRun(now()->addMonth()->format('Y-m'), User::factory()->create());
    }

    public function test_duplicate_month_is_rejected(): void
    {
        $this->makeRun();

        $this->expectException(ValidationException::class);
        $this->makeRun();
    }

    public function test_manual_lines_survive_recalculation_and_change_the_net(): void
    {
        $e = $this->employee(90000);
        $this->fullAttendance($e);
        $this->as(Permissions::PAYROLL_OFFICER);
        $run = app(PayrollService::class)->createRun('2026-08', auth()->user());
        $p = $run->payslips()->first();

        app(PayrollService::class)->addManualLine($p, 'earning', 'Performance bonus', 5000);
        $this->assertEquals(95000.0, $p->fresh()->gross);

        app(PayrollService::class)->generate($run->fresh());

        $this->assertEquals(95000.0, $p->fresh()->gross);
        $this->assertSame(1, $p->lines()->where('is_manual', true)->count());
    }

    public function test_approval_workflow_enforces_separation_of_duties_and_permissions(): void
    {
        $e = $this->employee();
        $this->fullAttendance($e);
        $officer = $this->as(Permissions::PAYROLL_OFFICER);
        $run = app(PayrollService::class)->createRun('2026-08', $officer);

        Livewire::test(Payroll\Show::class, ['run' => $run])->call('submit')->assertHasNoErrors();
        $this->assertSame('submitted', $run->fresh()->status);

        // The officer cannot approve (no permission); HR who did not prepare it can.
        Livewire::test(Payroll\Show::class, ['run' => $run])->call('approve')->assertForbidden();

        $this->as(Permissions::HR_MANAGER);
        Livewire::test(Payroll\Show::class, ['run' => $run])->call('lock')->assertForbidden();
        Livewire::test(Payroll\Show::class, ['run' => $run])->call('approve')->assertHasNoErrors();
        $this->assertSame('approved', $run->fresh()->status);

        // Only the Super Admin locks.
        $this->as(Permissions::SUPER_ADMIN);
        Livewire::test(Payroll\Show::class, ['run' => $run])->call('lock')->assertHasNoErrors();
        $this->assertSame('locked', $run->fresh()->status);

        // Locked runs cannot be recalculated or edited.
        $this->expectException(HttpException::class);
        app(PayrollService::class)->generate($run->fresh());
    }

    public function test_preparer_cannot_approve_own_payroll_even_with_permission(): void
    {
        $e = $this->employee();
        $this->fullAttendance($e);
        $admin = $this->as(Permissions::SUPER_ADMIN);
        $run = app(PayrollService::class)->createRun('2026-08', $admin);
        app(PayrollService::class)->submit($run, $admin);

        Livewire::test(Payroll\Show::class, ['run' => $run])->call('approve')->assertForbidden();
        $this->assertSame('submitted', $run->fresh()->status);
    }

    public function test_returning_to_draft_requires_a_reason_and_allows_editing_again(): void
    {
        $e = $this->employee();
        $this->fullAttendance($e);
        $officer = $this->as(Permissions::PAYROLL_OFFICER);
        $run = app(PayrollService::class)->createRun('2026-08', $officer);
        app(PayrollService::class)->submit($run, $officer);

        $this->as(Permissions::HR_MANAGER);
        Livewire::test(Payroll\Show::class, ['run' => $run])->set('reviewNote', '')->call('reject')->assertHasErrors('reviewNote');
        Livewire::test(Payroll\Show::class, ['run' => $run])->set('reviewNote', 'Check overtime')->call('reject')->assertHasNoErrors();

        $this->assertSame('draft', $run->fresh()->status);
    }

    public function test_payslip_pdf_access_rules(): void
    {
        $e = $this->employee();
        $employeeUser = User::factory()->create()->assignRole(Permissions::EMPLOYEE);
        $e->update(['user_id' => $employeeUser->id]);
        $other = $this->employee(attrs: ['nic' => '200012345678']);
        $this->fullAttendance($e);
        $run = $this->makeRun();
        $mine = $run->payslips()->firstWhere('employee_id', $e->id);
        $theirs = $run->payslips()->firstWhere('employee_id', $other->id);

        // Employee: blocked until locked, never someone else's.
        $this->actingAs($employeeUser)->get(route('payslips.pdf', $mine))->assertForbidden();
        $run->update(['status' => 'locked']);
        $this->actingAs($employeeUser)->get(route('payslips.pdf', $mine))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($employeeUser)->get(route('payslips.pdf', $theirs))->assertForbidden();

        // Payroll staff may open any.
        $this->as(Permissions::PAYROLL_OFFICER);
        $this->get(route('payslips.pdf', $theirs))->assertOk();
    }

    public function test_salary_sheet_export_requires_payroll_access(): void
    {
        $e = $this->employee();
        $this->fullAttendance($e);
        $run = $this->makeRun();

        $this->as(Permissions::MANAGER);
        $this->get(route('payroll.export', $run))->assertForbidden();

        $this->as(Permissions::PAYROLL_OFFICER);
        $this->get(route('payroll.export', $run))->assertOk();
    }

    public function test_payroll_screens_load_and_settings_are_snapshotted_per_run(): void
    {
        $e = $this->employee();
        $this->fullAttendance($e);
        $this->as(Permissions::SUPER_ADMIN);
        $run = $this->makeRun();

        foreach (['payroll.index', 'payroll.settings', 'payroll.components'] as $r) {
            $this->get(route($r))->assertOk();
        }
        $this->get(route('payroll.show', $run))->assertOk()->assertSee('Payroll — August 2026');

        PayrollSetting::current()->update(['epf_employee_rate' => 10]);
        $this->assertEquals(8, $run->fresh()->settings['epf_employee_rate']);
    }

    public function test_employee_role_cannot_open_payroll(): void
    {
        $this->as(Permissions::EMPLOYEE);

        $this->get(route('payroll.index'))->assertForbidden();
    }
}
