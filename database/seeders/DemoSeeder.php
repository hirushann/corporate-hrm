<?php

namespace Database\Seeders;

use App\Models\AttendanceAdjustment;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeCategory;
use App\Models\EmployeeComponent;
use App\Models\EmployeeDocument;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Notice;
use App\Models\PayrollRun;
use App\Models\Punch;
use App\Models\SalaryComponent;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\AttendanceProcessor;
use App\Services\LeaveService;
use App\Services\PayrollService;
use App\Support\Permissions;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Realistic sample data for client demos. Run after the base seeders:
 *
 *   php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder
 *
 * Demo logins are created with DEMO_PASSWORD from .env (see .env.example).
 */
class DemoSeeder extends Seeder
{
    private const DEMO_EMAIL_DOMAIN = 'lankatech.lk';

    private const PUNCH_SEED = 2026;

    /** @var array<string, Employee> keyed by employee_no */
    private array $employees = [];

    public function run(): void
    {
        if (Employee::where('employee_no', 'EMP-1001')->exists()) {
            $this->command?->warn('Demo data already present (EMP-1001 exists). Run "php artisan migrate:fresh --seed" first to reseed.');

            return;
        }

        mt_srand(self::PUNCH_SEED);

        $admin = User::where('email', env('ADMIN_EMAIL', 'admin@example.com'))->firstOrFail();
        $password = env('DEMO_PASSWORD', 'Demo@2026');

        $this->company();
        $branches = $this->branches();
        $departments = $this->departments();
        $designations = $this->designations();
        $categories = EmployeeCategory::pluck('id', 'name');
        $shifts = $this->shifts();
        $this->holidays();

        $users = $this->users($password);
        $this->employees($branches, $departments, $designations, $categories, $users, $admin);
        $this->salaryComponents();
        $this->shiftAssignments($shifts);
        $this->documents($admin);

        $leave = app(LeaveService::class);
        $leave->generateBalances((int) today()->year);
        $this->leave($leave, $users);

        $this->punches();
        app(AttendanceProcessor::class)->processRange(today()->subMonths(2)->startOfMonth(), today()->subDay());
        $this->adjustments($users);

        $this->payroll($users, $admin);
        $this->notices($departments, $admin);

        $this->command?->info('Demo data seeded. Logins (password in .env DEMO_PASSWORD):');
        foreach (['hr.manager', 'payroll', 'manager', 'employee'] as $local) {
            $this->command?->line("  {$local}@".self::DEMO_EMAIL_DOMAIN);
        }
    }

    private function company(): void
    {
        Company::query()->first()?->update([
            'name' => 'LankaTech Solutions (Pvt) Ltd',
            'registration_no' => 'PV 00218847',
            'address' => 'No. 120, Galle Road, Colombo 03',
            'phone' => '+94 11 2 345 678',
            'email' => 'info@'.self::DEMO_EMAIL_DOMAIN,
            'epf_registration_no' => 'CO/12345',
            'etf_registration_no' => 'ETF/98765',
            'tax_registration_no' => '114 567 890 0000',
        ]);
    }

    /** @return array<string, int> code => id */
    private function branches(): array
    {
        $company = Company::query()->first();
        $ids = [];
        foreach ([
            ['HO', 'Head Office', 'No. 120, Galle Road, Colombo 03', '+94 11 2 345 678'],
            ['KDY', 'Kandy Branch', 'No. 45, Peradeniya Road, Kandy', '+94 81 2 223 344'],
            ['GAL', 'Galle Branch', 'No. 8, Matara Road, Galle', '+94 91 2 234 567'],
        ] as [$code, $name, $address, $phone]) {
            $ids[$code] = Branch::updateOrCreate(['code' => $code], [
                'company_id' => $company->id, 'name' => $name, 'address' => $address, 'phone' => $phone,
            ])->id;
        }

        return $ids;
    }

    /** @return array<string, int> name => id */
    private function departments(): array
    {
        $ids = [];
        foreach ([
            ['Finance', 'FIN'], ['Human Resources', 'HR'], ['Information Technology', 'IT'],
            ['Operations', 'OPS'], ['Sales & Marketing', 'SAL'], ['Administration', 'ADM'],
        ] as [$name, $code]) {
            $ids[$name] = Department::updateOrCreate(['name' => $name], ['code' => $code])->id;
        }

        return $ids;
    }

    /** @return array<string, int> name => id */
    private function designations(): array
    {
        $ids = [];
        foreach ([
            'Managing Director', 'Head of Human Resources', 'Finance Manager', 'Accountant', 'Accounts Executive',
            'IT Manager', 'Senior Software Engineer', 'Software Engineer', 'QA Engineer',
            'Operations Manager', 'Operations Executive', 'Sales Manager', 'Sales Executive',
            'Administrative Officer', 'Receptionist', 'Office Assistant',
        ] as $name) {
            $ids[$name] = Designation::firstOrCreate(['name' => $name])->id;
        }

        return $ids;
    }

    /** @return array<string, Shift> */
    private function shifts(): array
    {
        $general = Shift::where('is_default', true)->firstOrFail();
        $early = Shift::firstOrCreate(['name' => 'Operations (early)'], [
            'start_time' => '07:00', 'end_time' => '16:00', 'break_minutes' => 60,
            'late_grace_minutes' => 10, 'early_grace_minutes' => 10, 'ot_after_minutes' => 30,
            'working_days' => [1, 2, 3, 4, 5, 6], 'is_default' => false,
        ]);

        return ['general' => $general, 'early' => $early];
    }

    private function holidays(): void
    {
        $year = today()->year;
        foreach ([
            ['01-14', 'Tamil Thai Pongal Day', 'public'],
            ['02-04', 'Independence Day', 'public'],
            ['04-13', 'Day prior to Sinhala & Tamil New Year', 'public'],
            ['04-14', 'Sinhala & Tamil New Year Day', 'public'],
            ['05-01', 'May Day', 'public'],
            ['05-31', 'Vesak Full Moon Poya Day', 'public'],
            ['06-01', 'Day following Vesak Poya', 'public'],
            ['06-29', 'Poson Full Moon Poya Day', 'public'],
            ['07-29', 'Esala Full Moon Poya Day', 'public'],
            ['08-27', 'Nikini Full Moon Poya Day', 'public'],
            ['09-25', 'Binara Full Moon Poya Day', 'public'],
            ['10-25', 'Vap Full Moon Poya Day', 'public'],
            ['11-08', 'Deepavali Festival Day', 'public'],
            ['12-24', 'Company Day (half)', 'company'],
            ['12-25', 'Christmas Day', 'public'],
        ] as [$md, $name, $type]) {
            Holiday::firstOrCreate(['date' => "{$year}-{$md}"], ['name' => $name, 'type' => $type]);
        }
    }

    /** @return array<string, User> keyed by role handle */
    private function users(string $password): array
    {
        $users = [];
        foreach ([
            'hr.manager' => ['Dilani Wickramasinghe', Permissions::HR_MANAGER],
            'payroll' => ['Ruwan Jayasuriya', Permissions::PAYROLL_OFFICER],
            'manager' => ['Kasun Fernando', Permissions::MANAGER],
            'employee' => ['Sachini Perera', Permissions::EMPLOYEE],
        ] as $handle => [$name, $role]) {
            $user = User::firstOrCreate(
                ['email' => "{$handle}@".self::DEMO_EMAIL_DOMAIN],
                ['name' => $name, 'password' => $password, 'email_verified_at' => now()],
            );
            $user->syncRoles($role);
            $users[$handle] = $user;
        }

        return $users;
    }

    /**
     * @param  array<string, int>  $branches
     * @param  array<string, int>  $departments
     * @param  array<string, int>  $designations
     * @param  Collection<string, int>  $categories
     * @param  array<string, User>  $users
     */
    private function employees(array $branches, array $departments, array $designations, $categories, array $users, User $admin): void
    {
        $banks = ['Commercial Bank', 'Bank of Ceylon', 'Sampath Bank', 'Hatton National Bank', 'People\'s Bank', 'NDB Bank'];

        // [no, name, nic, dob, gender, dept, designation, branch, category, join, basic, allowances, manager_no, user handle|null]
        $rows = [
            ['EMP-1001', 'Nuwan Bandara', '198012345678', '1980-03-12', 'male', 'Administration', 'Managing Director', 'HO', 'Permanent', '2015-01-05', 450000, 50000, null, 'admin'],
            ['EMP-1002', 'Dilani Wickramasinghe', '198512345678', '1985-07-21', 'female', 'Human Resources', 'Head of Human Resources', 'HO', 'Permanent', '2016-03-01', 285000, 25000, 'EMP-1001', 'hr.manager'],
            ['EMP-1003', 'Ruwan Jayasuriya', '198712345678', '1987-11-02', 'male', 'Finance', 'Finance Manager', 'HO', 'Permanent', '2017-06-12', 260000, 25000, 'EMP-1001', 'payroll'],
            ['EMP-1004', 'Kasun Fernando', '198612345678', '1986-01-30', 'male', 'Information Technology', 'IT Manager', 'HO', 'Permanent', '2017-09-04', 275000, 25000, 'EMP-1001', 'manager'],
            ['EMP-1005', 'Sachini Perera', '199512345678', '1995-05-18', 'female', 'Information Technology', 'Software Engineer', 'HO', 'Permanent', '2022-02-14', 145000, 15000, 'EMP-1004', 'employee'],
            ['EMP-1006', 'Tharindu Silva', '199212345678', '1992-09-09', 'male', 'Information Technology', 'Senior Software Engineer', 'HO', 'Permanent', '2019-04-01', 210000, 20000, 'EMP-1004', null],
            ['EMP-1007', 'Ishara Gunawardena', '199612345678', '1996-12-25', 'female', 'Information Technology', 'QA Engineer', 'HO', 'Permanent', '2023-01-09', 125000, 15000, 'EMP-1004', null],
            ['EMP-1008', 'Chamara Rathnayake', '199012345678', '1990-04-04', 'male', 'Finance', 'Accountant', 'HO', 'Permanent', '2018-08-20', 165000, 15000, 'EMP-1003', null],
            ['EMP-1009', 'Nadeesha Kumari', '199812345678', '1998-02-11', 'female', 'Finance', 'Accounts Executive', 'HO', 'Probation', today()->subMonths(4)->startOfMonth()->toDateString(), 85000, 10000, 'EMP-1003', null],
            ['EMP-1010', 'Pradeep Weerasinghe', '198812345678', '1988-06-15', 'male', 'Operations', 'Operations Manager', 'KDY', 'Permanent', '2018-01-15', 220000, 20000, 'EMP-1001', null],
            ['EMP-1011', 'Sanduni Herath', '199712345678', '1997-10-03', 'female', 'Operations', 'Operations Executive', 'KDY', 'Permanent', '2021-11-01', 95000, 12000, 'EMP-1010', null],
            ['EMP-1012', 'Lahiru Madushanka', '199412345678', '1994-08-27', 'male', 'Operations', 'Operations Executive', 'GAL', 'Contract', '2024-05-06', 90000, 12000, 'EMP-1010', null],
            ['EMP-1013', 'Amali Jayawardena', '198912345678', '1989-03-19', 'female', 'Sales & Marketing', 'Sales Manager', 'HO', 'Permanent', '2019-10-07', 195000, 30000, 'EMP-1001', null],
            ['EMP-1014', 'Dinesh Karunaratne', '199312345678', '1993-07-07', 'male', 'Sales & Marketing', 'Sales Executive', 'KDY', 'Permanent', '2022-08-01', 95000, 25000, 'EMP-1013', null],
            ['EMP-1015', 'Hashini Dissanayake', '199912345678', '1999-01-22', 'female', 'Administration', 'Receptionist', 'HO', 'Permanent', '2023-06-19', 65000, 8000, 'EMP-1002', null],
            ['EMP-1016', 'Sunil Gamage', '197512345678', '1975-11-11', 'male', 'Administration', 'Office Assistant', 'HO', 'Permanent', '2015-02-02', 55000, 6000, 'EMP-1002', null],
            ['EMP-1017', 'Ravindu Senanayake', '200012345678', '2000-04-30', 'male', 'Information Technology', 'Software Engineer', 'HO', 'Intern', today()->subMonth()->day(15)->toDateString(), 50000, 0, 'EMP-1004', null],
            ['EMP-1018', 'Malsha Rajapaksha', '199112345678', '1991-09-14', 'female', 'Sales & Marketing', 'Sales Executive', 'GAL', 'Permanent', '2020-03-02', 92000, 25000, 'EMP-1013', null],
        ];

        foreach ($rows as $i => [$no, $name, $nic, $dob, $gender, $dept, $desig, $branch, $cat, $join, $basic, $allow, $managerNo, $handle]) {
            $first = explode(' ', $name)[0];
            $user = $handle === 'admin' ? $admin : ($handle ? $users[$handle] : null);

            $employee = Employee::create([
                'employee_no' => $no,
                'biometric_id' => (string) (100 + $i),
                'user_id' => $user?->id,
                'branch_id' => $branches[$branch],
                'department_id' => $departments[$dept],
                'designation_id' => $designations[$desig],
                'employee_category_id' => $categories[$cat],
                'manager_id' => $managerNo ? $this->employees[$managerNo]->id : null,
                'full_name' => $name,
                'nic' => $nic,
                'date_of_birth' => $dob,
                'gender' => $gender,
                'marital_status' => $i % 3 === 0 ? 'married' : 'single',
                'phone' => sprintf('+94 7%d %03d %04d', 1 + $i % 7, 100 + $i * 7, 1000 + $i * 113),
                'personal_email' => strtolower($first).'.'.strtolower(explode(' ', $name)[1]).'@gmail.com',
                'address' => ['No. 12, Temple Road, Nugegoda', 'No. 45/3, Hill Street, Dehiwala', 'No. 7, Lake Drive, Kandy', 'No. 88, Beach Road, Galle', 'No. 23, Station Road, Maharagama'][$i % 5],
                'emergency_contact_name' => ['Kamal', 'Nimal', 'Sunethra', 'Chandrika', 'Ajith'][$i % 5].' '.explode(' ', $name)[1],
                'emergency_contact_phone' => sprintf('+94 77 %03d %04d', 200 + $i * 3, 5000 + $i * 41),
                'join_date' => $join,
                'confirmation_date' => $cat === 'Permanent' ? Carbon::parse($join)->addMonths(6)->toDateString() : null,
                'status' => $cat === 'Probation' ? 'probation' : 'active',
                'bank_name' => $banks[$i % count($banks)],
                'bank_branch' => ['Colombo 03', 'Nugegoda', 'Kandy City', 'Galle Fort', 'Dehiwala'][$i % 5],
                'bank_account_number' => sprintf('%010d', 8000000000 + $i * 1234567),
                'bank_account_name' => $name,
                'epf_number' => 'CO/12345/'.(100 + $i),
                'etf_number' => 'ETF/98765/'.(100 + $i),
                'tax_tin' => $basic >= 150000 ? sprintf('TIN%09d', 100000000 + $i * 7919) : null,
                'epf_applicable' => $cat !== 'Intern',
                'etf_applicable' => $cat !== 'Intern',
                'apit_applicable' => $basic >= 150000,
                'ot_eligible' => $basic < 200000,
            ]);

            $employee->salaries()->create([
                'effective_from' => $join, 'basic_salary' => round($basic * 0.85, -2), 'fixed_allowances' => $allow,
                'reason' => 'Opening salary',
            ]);
            if (Carbon::parse($join)->lt(today()->subYear())) {
                $employee->salaries()->create([
                    'effective_from' => today()->subYear()->startOfYear()->toDateString(), 'basic_salary' => $basic,
                    'fixed_allowances' => $allow, 'reason' => 'Annual increment', 'created_by' => $admin->id,
                ]);
            }

            $this->employees[$no] = $employee;
        }
    }

    private function salaryComponents(): void
    {
        $components = SalaryComponent::pluck('id', 'name');
        $from = today()->subMonths(2)->startOfMonth()->toDateString();

        foreach ($this->employees as $no => $employee) {
            EmployeeComponent::create(['employee_id' => $employee->id, 'salary_component_id' => $components['Transport allowance'], 'amount' => in_array($no, ['EMP-1010', 'EMP-1011', 'EMP-1012', 'EMP-1014', 'EMP-1018']) ? 12000 : 8000, 'effective_from' => $from]);
            if ($employee->employee_category_id === EmployeeCategory::where('name', 'Permanent')->value('id')) {
                EmployeeComponent::create(['employee_id' => $employee->id, 'salary_component_id' => $components['Budgetary Relief Allowance'], 'amount' => 3500, 'effective_from' => $from]);
            }
        }

        EmployeeComponent::create(['employee_id' => $this->employees['EMP-1008']->id, 'salary_component_id' => $components['Loan recovery'], 'amount' => 15000, 'effective_from' => $from, 'effective_to' => today()->addMonths(10)->endOfMonth()->toDateString()]);
        EmployeeComponent::create(['employee_id' => $this->employees['EMP-1016']->id, 'salary_component_id' => $components['Salary advance recovery'], 'amount' => 5000, 'effective_from' => today()->subMonth()->startOfMonth()->toDateString(), 'effective_to' => today()->subMonth()->endOfMonth()->toDateString()]);
    }

    /** @param array<string, Shift> $shifts */
    private function shiftAssignments(array $shifts): void
    {
        foreach (['EMP-1011', 'EMP-1012'] as $no) {
            ShiftAssignment::create(['employee_id' => $this->employees[$no]->id, 'shift_id' => $shifts['early']->id, 'effective_from' => today()->subMonths(2)->startOfMonth()->toDateString()]);
        }
    }

    private function documents(User $admin): void
    {
        foreach ([
            ['Employee Handbook', 'policy', 'Welcome to LankaTech. This handbook sets out working hours, conduct, benefits and the grievance procedure.'],
            ['Leave Policy', 'policy', 'Annual leave: 14 days. Casual leave: 7 days. Medical leave: 7 days. Requests must be submitted through the employee portal.'],
            ['Code of Conduct', 'policy', 'All employees are expected to act with integrity, respect colleagues and clients, and protect company information.'],
            ['Medical Claim Form', 'form', 'Attach original receipts and submit to Human Resources within 30 days of treatment.'],
        ] as [$title, $category, $text]) {
            $path = 'company-documents/'.str($title)->slug().'.pdf';
            Storage::disk('local')->put($path, $this->pdf($title, $text));
            CompanyDocument::create([
                'title' => $title, 'category' => $category, 'path' => $path, 'original_name' => "{$title}.pdf",
                'mime_type' => 'application/pdf', 'size' => Storage::disk('local')->size($path), 'uploaded_by' => $admin->id,
            ]);
        }

        foreach (['EMP-1005', 'EMP-1006', 'EMP-1008'] as $no) {
            $employee = $this->employees[$no];
            foreach ([['contract', 'Employment contract', null], ['nic', 'NIC copy', null], ['other', 'Degree certificate', null]] as [$type, $title, $expires]) {
                $path = "employee-documents/{$employee->id}/".str($title)->slug().'.pdf';
                Storage::disk('local')->put($path, $this->pdf($title, "{$title} — {$employee->full_name} ({$employee->employee_no})"));
                EmployeeDocument::create([
                    'employee_id' => $employee->id, 'type' => $type, 'title' => $title, 'path' => $path,
                    'original_name' => "{$title}.pdf", 'mime_type' => 'application/pdf',
                    'size' => Storage::disk('local')->size($path), 'expires_on' => $expires, 'uploaded_by' => $admin->id,
                ]);
            }
        }
    }

    private function pdf(string $title, string $text): string
    {
        $company = e(Company::query()->first()->name);

        return Pdf::loadHTML("<h1>{$company}</h1><h2>".e($title).'</h2><p>'.e($text).'</p><p style="color:#888">Sample document generated for demonstration.</p>')->output();
    }

    /** @param array<string, User> $users */
    private function leave(LeaveService $leave, array $users): void
    {
        $types = LeaveType::pluck('id', 'name')->map(fn ($id) => LeaveType::find($id));
        $lastMonth = today()->subMonth();
        $hr = $users['hr.manager'];

        $plan = [
            // [employee, type, from, to, half, reason, decision]
            ['EMP-1005', 'Annual', $lastMonth->copy()->day(8), $lastMonth->copy()->day(10), false, 'Family trip to Nuwara Eliya', 'approved'],
            ['EMP-1008', 'Medical', $lastMonth->copy()->day(15), $lastMonth->copy()->day(16), false, 'Fever — doctor\'s note attached', 'approved'],
            ['EMP-1011', 'Casual', $lastMonth->copy()->day(21), $lastMonth->copy()->day(21), true, 'Bank visit', 'approved'],
            ['EMP-1014', 'Annual', $lastMonth->copy()->day(2), $lastMonth->copy()->day(4), false, 'Wedding', 'approved'],
            ['EMP-1016', 'No Pay', $lastMonth->copy()->day(28), $lastMonth->copy()->day(29), false, 'Personal matter', 'approved'],
            ['EMP-1007', 'Annual', today()->copy()->day(5), today()->copy()->day(6), false, 'Personal', 'approved'],
            ['EMP-1006', 'Casual', today()->copy()->day(12), today()->copy()->day(12), false, 'Vehicle service', 'rejected'],
            ['EMP-1005', 'Annual', today()->addDays(9), today()->addDays(11), false, 'Pilgrimage', null],
            ['EMP-1013', 'Casual', today()->addDays(3), today()->addDays(3), false, 'Child\'s school event', null],
            ['EMP-1015', 'Medical', today()->addDays(1), today()->addDays(2), false, 'Dental surgery', null],
        ];

        foreach ($plan as [$no, $type, $from, $to, $half, $reason, $decision]) {
            $employee = $this->employees[$no];
            try {
                $request = $leave->request($employee, $types[$type], $from, $to, $half, $reason, $employee->user_id ?? $hr->id);
            } catch (\Throwable $e) {
                $this->command?->warn("Leave for {$no} skipped: {$e->getMessage()}");

                continue;
            }
            if ($decision) {
                $leave->decide($request, $decision === 'approved', $hr->id, $decision === 'rejected' ? 'Month-end release scheduled that day' : null);
            }
        }
    }

    private function punches(): void
    {
        $from = today()->subMonths(2)->startOfMonth();
        $to = today()->subDay();
        $holidays = Holiday::whereBetween('date', [$from, $to])->pluck('date')->map(fn ($d) => $d->toDateString())->flip();
        $leaves = LeaveRequest::where('status', 'approved')->get()->groupBy('employee_id');

        foreach ($this->employees as $no => $employee) {
            $shift = $employee->shiftAssignments()->first()?->shift ?? Shift::where('is_default', true)->first();
            [$startH, $startM] = array_map('intval', explode(':', $shift->start_time));
            [$endH, $endM] = array_map('intval', explode(':', $shift->end_time));
            $rows = [];

            for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                if ($day->lt($employee->join_date) || ! $shift->isWorkingDay($day) || isset($holidays[$day->toDateString()])) {
                    continue;
                }
                $onLeave = ($leaves[$employee->id] ?? collect())->first(fn ($l) => $l->from_date->lte($day) && $l->to_date->gte($day) && ! $l->half_day);
                if ($onLeave) {
                    continue;
                }
                // ~3% unexplained absences, except for senior staff.
                if (mt_rand(1, 100) <= 3 && ! in_array($no, ['EMP-1001', 'EMP-1002', 'EMP-1003', 'EMP-1004'])) {
                    continue;
                }

                $inOffset = mt_rand(-25, 10);                                   // mostly early, sometimes late
                if (mt_rand(1, 100) <= 12) {
                    $inOffset = mt_rand(16, 45);                                // ~12% late beyond grace
                }
                $outOffset = mt_rand(0, 20);
                if (mt_rand(1, 100) <= 20 && $employee->ot_eligible) {
                    $outOffset = mt_rand(45, 150);                              // ~20% overtime
                }
                if (mt_rand(1, 100) <= 4) {
                    $outOffset = mt_rand(-90, -30);                             // ~4% early leave
                }

                $in = $day->copy()->setTime($startH, $startM)->addMinutes($inOffset);
                $out = $day->copy()->setTime($endH, $endM)->addMinutes($outOffset);
                $rows[] = ['device_user_id' => $employee->biometric_id, 'employee_id' => $employee->id, 'punched_at' => $in, 'source' => 'csv', 'batch' => 'demo', 'created_at' => now(), 'updated_at' => now()];
                if (mt_rand(1, 100) > 2) {                                      // ~2% missing out-punch
                    $rows[] = ['device_user_id' => $employee->biometric_id, 'employee_id' => $employee->id, 'punched_at' => $out, 'source' => 'csv', 'batch' => 'demo', 'created_at' => now(), 'updated_at' => now()];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                Punch::insert($chunk);
            }
        }
    }

    /** @param array<string, User> $users */
    private function adjustments(array $users): void
    {
        $lastMonth = today()->subMonth();
        $hr = $users['hr.manager'];

        $approved = AttendanceAdjustment::create([
            'employee_id' => $this->employees['EMP-1006']->id, 'date' => $lastMonth->copy()->day(12)->toDateString(),
            'in_at' => $lastMonth->copy()->day(12)->setTime(8, 25), 'out_at' => $lastMonth->copy()->day(12)->setTime(17, 35),
            'reason' => 'Forgot to punch out — was at client site', 'status' => 'approved',
            'requested_by' => $this->employees['EMP-1006']->user_id ?? $hr->id, 'reviewed_by' => $hr->id, 'reviewed_at' => now(), 'review_note' => 'Confirmed with client',
        ]);
        app(AttendanceProcessor::class)->processRange($approved->date, $approved->date, [$approved->employee]);

        AttendanceAdjustment::create([
            'employee_id' => $this->employees['EMP-1005']->id, 'date' => today()->subDays(3)->toDateString(),
            'in_at' => today()->subDays(3)->setTime(8, 30), 'out_at' => null,
            'reason' => 'Fingerprint reader was not working', 'status' => 'pending',
            'requested_by' => $users['employee']->id,
        ]);
    }

    /** @param array<string, User> $users */
    private function payroll(array $users, User $admin): void
    {
        $service = app(PayrollService::class);

        foreach ([today()->subMonths(2), today()->subMonth()] as $month) {
            $run = $service->createRun($month->format('Y-m'), $users['payroll']);

            if ($month->isSameMonth(today()->subMonth())) {
                $payslip = $run->payslips()->where('employee_no', 'EMP-1013')->first();
                $payslip?->lines()->create(['type' => 'earning', 'code' => 'MANUAL', 'description' => 'Q3 sales incentive', 'amount' => 35000, 'is_manual' => true]);
                $payslip?->refreshTotals();
            }

            $service->submit($run, $users['payroll']);
            $service->approve($run, $users['hr.manager']);
            $service->lock($run, $admin);

            PayrollRun::whereKey($run->id)->update([
                'created_at' => $month->copy()->endOfMonth()->addDays(2), 'submitted_at' => $month->copy()->endOfMonth()->addDays(2),
                'approved_at' => $month->copy()->endOfMonth()->addDays(3), 'locked_at' => $month->copy()->endOfMonth()->addDays(4),
            ]);
        }
    }

    /** @param array<string, int> $departments */
    private function notices(array $departments, User $admin): void
    {
        foreach ([
            ['Annual Staff Get-together — '.today()->addDays(18)->format('j F'), 'Join us at the Kingsbury, Colombo from 6.30 pm. Families are welcome. Please confirm attendance with Reception by '.today()->addDays(10)->format('j F').'.', null, true, today()->subDays(2), null],
            ['Payslips for '.today()->subMonth()->format('F').' are now available', 'Payslips have been published to the employee portal. Please review and report any discrepancies to Finance within 5 working days.', null, false, today()->subMonth()->endOfMonth()->addDays(4), null],
            ['New leave request process', 'From this month all leave requests must be submitted through the employee portal. Paper forms will no longer be accepted.', null, false, today()->subDays(20), null],
            ['IT: Scheduled server maintenance', 'Email and the ERP will be unavailable on Saturday from 10 pm to 2 am for scheduled maintenance.', $departments['Information Technology'], false, today()->subDays(5), today()->addDays(3)],
            ['Finance: Q3 expense claims deadline', 'All Q3 expense claims must be submitted with receipts by the end of this week.', $departments['Finance'], false, today()->subDays(7), today()->addDays(2)],
        ] as [$title, $body, $departmentId, $pinned, $published, $expires]) {
            Notice::create([
                'title' => $title, 'body' => $body, 'department_id' => $departmentId, 'is_pinned' => $pinned,
                'published_at' => $published, 'expires_at' => $expires, 'created_by' => $admin->id,
            ]);
        }
    }
}
