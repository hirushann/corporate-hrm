<?php

namespace Tests\Feature;

use App\Livewire\Attendance;
use App\Livewire\Leave;
use App\Models\AttendanceAdjustment;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Punch;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceProcessor;
use App\Services\LeaveService;
use App\Services\PunchImporter;
use App\Support\Permissions;
use Carbon\Carbon;
use Database\Seeders\AttendanceSeeder;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    // 2026-09-07 is a Monday; shift is 08:30-17:30, 60 min break, 15 min grace, OT after 30 min.
    private const MON = '2026-09-07';

    private const SAT = '2026-09-12';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, OrganizationSeeder::class, AttendanceSeeder::class]);
    }

    private function employee(array $attrs = []): Employee
    {
        return Employee::factory()->create($attrs + ['join_date' => '2026-01-01']);
    }

    private function punch(Employee $e, string $dateTime): void
    {
        Punch::create(['device_user_id' => $e->employee_no, 'employee_id' => $e->id, 'punched_at' => $dateTime]);
    }

    private function record(Employee $e, string $date): ?AttendanceRecord
    {
        app(AttendanceProcessor::class)->processRange(Carbon::parse($date), Carbon::parse($date), [$e]);

        return AttendanceRecord::where('employee_id', $e->id)->whereDate('date', $date)->first();
    }

    private function as(string $role): User
    {
        $user = User::factory()->create()->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_on_time_day_has_no_flags(): void
    {
        $e = $this->employee();
        $this->punch($e, self::MON.' 08:40');
        $this->punch($e, self::MON.' 17:35');

        $r = $this->record($e, self::MON);

        $this->assertSame('present', $r->status);
        $this->assertSame(0, $r->late_minutes);
        $this->assertSame(0, $r->early_minutes);
        $this->assertSame(0, $r->ot_minutes);
        $this->assertSame(475, $r->worked_minutes); // 08:40-17:35 = 535 min minus 60 break
    }

    public function test_late_beyond_grace_counts_full_lateness(): void
    {
        $e = $this->employee();
        $this->punch($e, self::MON.' 08:50');
        $this->punch($e, self::MON.' 17:30');

        $this->assertSame(20, $this->record($e, self::MON)->late_minutes);
    }

    public function test_early_departure_beyond_grace(): void
    {
        $e = $this->employee();
        $this->punch($e, self::MON.' 08:30');
        $this->punch($e, self::MON.' 16:30');

        $this->assertSame(60, $this->record($e, self::MON)->early_minutes);
    }

    public function test_overtime_starts_after_threshold(): void
    {
        $e = $this->employee();
        $this->punch($e, self::MON.' 08:30');
        $this->punch($e, self::MON.' 19:00');

        $this->assertSame(90, $this->record($e, self::MON)->ot_minutes);

        $e2 = $this->employee();
        $this->punch($e2, self::MON.' 08:30');
        $this->punch($e2, self::MON.' 17:50');
        $this->assertSame(0, $this->record($e2, self::MON)->ot_minutes);
    }

    public function test_no_punches_on_a_working_day_is_absent(): void
    {
        $this->assertSame('absent', $this->record($this->employee(), self::MON)->status);
    }

    public function test_single_punch_is_present_with_missing_punch_flag(): void
    {
        $e = $this->employee();
        $this->punch($e, self::MON.' 08:30');

        $r = $this->record($e, self::MON);
        $this->assertSame('present', $r->status);
        $this->assertTrue($r->missing_punch);
    }

    public function test_weekend_and_holiday_are_not_absent_and_work_is_overtime(): void
    {
        Holiday::create(['date' => '2026-09-08', 'name' => 'Test Holiday']);
        $e = $this->employee();

        $this->assertSame('off', $this->record($e, self::SAT)->status);
        $this->assertSame('holiday', $this->record($e, '2026-09-08')->status);

        $this->punch($e, self::SAT.' 09:00');
        $this->punch($e, self::SAT.' 13:00');
        $r = $this->record($e, self::SAT);
        $this->assertSame(240, $r->ot_minutes);
    }

    public function test_approved_leave_marks_day_as_leave(): void
    {
        $e = $this->employee();
        LeaveRequest::create([
            'employee_id' => $e->id, 'leave_type_id' => LeaveType::first()->id, 'from_date' => self::MON, 'to_date' => self::MON,
            'days' => 1, 'status' => 'approved',
        ]);

        $r = $this->record($e, self::MON);
        $this->assertSame('leave', $r->status);
        $this->assertSame('1.0', (string) $r->leave_fraction);
    }

    public function test_days_before_joining_get_no_record(): void
    {
        $e = $this->employee(['join_date' => '2026-09-10']);

        $this->assertNull($this->record($e, self::MON));
    }

    public function test_reprocessing_is_idempotent(): void
    {
        $e = $this->employee();
        $this->punch($e, self::MON.' 08:30');
        $this->punch($e, self::MON.' 17:30');

        $this->record($e, self::MON);
        $this->record($e, self::MON);

        $this->assertSame(1, AttendanceRecord::where('employee_id', $e->id)->count());
    }

    public function test_night_or_custom_shift_assignment_is_respected(): void
    {
        $e = $this->employee();
        $early = Shift::create(['name' => 'Early', 'start_time' => '06:00', 'end_time' => '14:00', 'working_days' => [1, 2, 3, 4, 5]]);
        $e->shiftAssignments()->create(['shift_id' => $early->id, 'effective_from' => '2026-09-01']);
        $this->punch($e, self::MON.' 06:00');
        $this->punch($e, self::MON.' 14:00');

        $r = $this->record($e, self::MON);
        $this->assertSame($early->id, $r->shift_id);
        $this->assertSame(0, $r->late_minutes);
    }

    public function test_csv_import_dedupes_reports_unknown_ids_and_processes(): void
    {
        $e = $this->employee();
        $csv = "employee_no,datetime\n{$e->employee_no},".self::MON." 08:31:00\n{$e->employee_no},".self::MON." 17:32:00\n{$e->employee_no},".self::MON." 17:32:00\nNOPE,".self::MON." 09:00:00\n";
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $csv);

        $result = app(PunchImporter::class)->import($path);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(1, $result['duplicates']);
        $this->assertCount(1, $result['errors']);
    }

    public function test_csv_import_supports_separate_date_time_and_biometric_ids(): void
    {
        $e = $this->employee(['biometric_id' => '77']);
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "User ID,Date,Time\n77,".self::MON.",08:30:00\n");

        $this->assertSame(1, app(PunchImporter::class)->import($path)['imported']);
        $this->assertSame($e->id, Punch::first()->employee_id);
    }

    public function test_import_screen_requires_manage_permission_and_recalculates(): void
    {
        $this->as(Permissions::MANAGER);
        $this->get(route('attendance.import'))->assertForbidden();

        $this->as(Permissions::HR_MANAGER);
        $e = $this->employee();
        $file = UploadedFile::fake()->createWithContent('p.csv', "employee_no,datetime\n{$e->employee_no},".self::MON." 08:30:00\n{$e->employee_no},".self::MON." 17:30:00\n");

        Livewire::test(Attendance\Import::class)->set('file', $file)->call('import')->assertHasNoErrors();

        $this->assertSame('present', AttendanceRecord::first()->status);
    }

    public function test_adjustment_workflow_requires_a_different_approver_and_updates_attendance(): void
    {
        $e = $this->employee();
        $hr = $this->as(Permissions::HR_MANAGER);

        Livewire::test(Attendance\Adjustments::class)
            ->set('employeeId', $e->id)->set('date', self::MON)->set('inTime', '08:30')->set('outTime', '17:30')->set('reason', 'Device was offline')
            ->call('submit')->assertHasNoErrors();

        $adjustment = AttendanceAdjustment::firstOrFail();
        Livewire::test(Attendance\Adjustments::class)->call('review', $adjustment->id, true)->assertForbidden();

        $this->as(Permissions::HR_MANAGER);
        Livewire::test(Attendance\Adjustments::class)->call('review', $adjustment->id, true)->assertHasNoErrors();

        $r = AttendanceRecord::where('employee_id', $e->id)->firstOrFail();
        $this->assertSame('present', $r->status);
        $this->assertTrue($r->is_adjusted);
        $this->assertDatabaseHas('activity_log', ['subject_type' => AttendanceAdjustment::class, 'event' => 'updated']);
    }

    public function test_manager_only_sees_own_team(): void
    {
        $manager = $this->as(Permissions::MANAGER);
        $boss = $this->employee(['user_id' => $manager->id]);
        $mine = $this->employee(['full_name' => 'Team Member', 'manager_id' => $boss->id]);
        $other = $this->employee(['full_name' => 'Stranger']);

        $this->assertEquals([$mine->id], Employee::visibleTo($manager)->pluck('id')->all());

        $this->get(route('employees.show', $other))->assertForbidden();
        $this->get(route('employees.show', $mine))->assertOk();
    }

    public function test_leave_days_skip_weekends_and_holidays(): void
    {
        Holiday::create(['date' => '2026-09-09', 'name' => 'Mid-week']);
        $e = $this->employee();

        // Mon 7 → Mon 14 = Mon-Fri (5, minus the Wednesday holiday) + Mon 14 = 5
        $days = app(LeaveService::class)->countDays($e, Carbon::parse('2026-09-07'), Carbon::parse('2026-09-14'));
        $this->assertSame(5.0, $days);
    }

    public function test_leave_request_blocked_by_balance_and_overlap(): void
    {
        $e = $this->employee();
        $annual = LeaveType::where('name', 'Annual')->first();
        LeaveBalance::create(['employee_id' => $e->id, 'leave_type_id' => $annual->id, 'year' => 2026, 'entitled' => 2]);
        $service = app(LeaveService::class);

        $service->request($e, $annual, Carbon::parse('2026-09-07'), Carbon::parse('2026-09-08'), false, null, null);

        $this->expectException(ValidationException::class);
        $service->request($e, $annual, Carbon::parse('2026-09-08'), Carbon::parse('2026-09-09'), false, null, null);
    }

    public function test_leave_over_balance_is_rejected(): void
    {
        $e = $this->employee();
        $annual = LeaveType::where('name', 'Annual')->first();
        LeaveBalance::create(['employee_id' => $e->id, 'leave_type_id' => $annual->id, 'year' => 2026, 'entitled' => 1]);

        $this->expectException(ValidationException::class);
        app(LeaveService::class)->request($e, $annual, Carbon::parse('2026-09-07'), Carbon::parse('2026-09-08'), false, null, null);
    }

    public function test_approving_leave_updates_attendance_and_cancelling_reverts_it(): void
    {
        $e = $this->employee();
        $casual = LeaveType::where('name', 'Casual')->first();
        LeaveBalance::create(['employee_id' => $e->id, 'leave_type_id' => $casual->id, 'year' => 2026, 'entitled' => 7]);
        $service = app(LeaveService::class);

        $request = $service->request($e, $casual, Carbon::parse(self::MON), Carbon::parse(self::MON), false, 'Family', null);
        $service->decide($request, true, User::factory()->create()->id);
        $this->assertSame('leave', AttendanceRecord::where('employee_id', $e->id)->first()->status);

        $service->cancel($request->fresh());
        $this->assertSame('absent', AttendanceRecord::where('employee_id', $e->id)->first()->status);
    }

    public function test_nobody_can_approve_their_own_leave(): void
    {
        $manager = $this->as(Permissions::HR_MANAGER);
        $self = $this->employee(['user_id' => $manager->id]);
        $annual = LeaveType::where('name', 'Annual')->first();
        $request = LeaveRequest::create(['employee_id' => $self->id, 'leave_type_id' => $annual->id, 'from_date' => self::MON, 'to_date' => self::MON, 'days' => 1]);

        Livewire::test(Leave\Requests::class)->call('decide', $request->id, true)->assertForbidden();
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_generate_balances_applies_leave_type_defaults_and_carry_forward(): void
    {
        $e = $this->employee();
        $annual = LeaveType::where('name', 'Annual')->first();
        $annual->update(['carry_forward_max' => 5]);
        LeaveBalance::create(['employee_id' => $e->id, 'leave_type_id' => $annual->id, 'year' => 2025, 'entitled' => 14]);
        LeaveRequest::create(['employee_id' => $e->id, 'leave_type_id' => $annual->id, 'from_date' => '2025-03-03', 'to_date' => '2025-03-04', 'days' => 2, 'status' => 'approved']);

        app(LeaveService::class)->generateBalances(2026);

        $b = LeaveBalance::where(['employee_id' => $e->id, 'leave_type_id' => $annual->id, 'year' => 2026])->first();
        $this->assertSame('14.0', (string) $b->entitled);
        $this->assertSame('5.0', (string) $b->carried_forward); // 12 left, capped at 5
    }

    public function test_every_phase_two_screen_loads_for_super_admin(): void
    {
        $this->as(Permissions::SUPER_ADMIN);

        foreach (['attendance.index', 'attendance.import', 'attendance.adjustments', 'attendance.roster', 'attendance.shifts', 'attendance.holidays', 'leave.requests', 'leave.balances', 'leave.types'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_shift_form_validates_and_saves_working_days(): void
    {
        $this->as(Permissions::HR_MANAGER);

        Livewire::test(Attendance\Shifts::class)->call('create')
            ->set('form.name', 'Saturday half')->set('form.start_time', '08:00')->set('form.end_time', '13:00')
            ->set('form.working_days', [6])
            ->call('save')->assertHasNoErrors();

        $this->assertSame(['6'], array_map('strval', Shift::where('name', 'Saturday half')->first()->working_days));

        Livewire::test(Attendance\Shifts::class)->call('create')
            ->set('form.name', 'Bad')->set('form.start_time', '10:00')->set('form.end_time', '09:00')
            ->call('save')->assertHasErrors('form.end_time');
    }
}
