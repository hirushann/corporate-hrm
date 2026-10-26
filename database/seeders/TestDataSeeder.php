<?php

namespace Database\Seeders;

use App\Models\AttendanceAdjustment;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeCategory;
use App\Models\EmployeeComponent;
use App\Models\EmployeeSalary;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Notice;
use App\Models\PayrollRun;
use App\Models\PayrollSetting;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Models\Punch;
use App\Models\SalaryComponent;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Support\Permissions;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCompanyAndBranches();
        $this->seedDepartmentsAndDesignations();
        $this->seedHolidaysAndShifts();
        $this->seedUsersAndEmployees();
        $this->seedLeaveData();
        $this->seedAttendanceData();
        $this->seedPayrollRuns();
        $this->seedNoticesAndDocuments();
    }

    private function seedCompanyAndBranches(): void
    {
        $company = Company::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Corporate HRM Solutions Ltd',
                'registration_no' => 'PV-987654',
                'address' => '123 Galle Road, Colombo 03, Sri Lanka',
                'phone' => '+94 11 234 5678',
                'email' => 'info@corporatehrm.local',
                'epf_registration_no' => 'EPF-889900',
                'etf_registration_no' => 'ETF-112233',
                'tax_registration_no' => 'TIN-445566',
            ]
        );

        $company->update([
            'registration_no' => 'PV-987654',
            'address' => '123 Galle Road, Colombo 03, Sri Lanka',
            'phone' => '+94 11 234 5678',
            'email' => 'info@corporatehrm.local',
            'epf_registration_no' => 'EPF-889900',
            'etf_registration_no' => 'ETF-112233',
            'tax_registration_no' => 'TIN-445566',
        ]);

        Branch::firstOrCreate(
            ['code' => 'HO'],
            ['company_id' => $company->id, 'name' => 'Head Office', 'address' => 'Colombo 03', 'phone' => '+94 11 234 5678']
        );

        Branch::firstOrCreate(
            ['code' => 'KB'],
            ['company_id' => $company->id, 'name' => 'Kandy Branch', 'address' => 'Dalada Veediya, Kandy', 'phone' => '+94 81 223 4455']
        );

        Branch::firstOrCreate(
            ['code' => 'GB'],
            ['company_id' => $company->id, 'name' => 'Galle Branch', 'address' => 'Main Street, Galle Fort', 'phone' => '+94 91 223 7788']
        );
    }

    private function seedDepartmentsAndDesignations(): void
    {
        $departments = [
            ['name' => 'Executive Management', 'code' => 'EXEC'],
            ['name' => 'Engineering & Technology', 'code' => 'ENG'],
            ['name' => 'Human Resources', 'code' => 'HR'],
            ['name' => 'Finance & Accounting', 'code' => 'FIN'],
            ['name' => 'Marketing & Sales', 'code' => 'MKT'],
            ['name' => 'Operations', 'code' => 'OPS'],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(['name' => $dept['name']], ['code' => $dept['code'], 'is_active' => true]);
        }

        $designations = [
            'Chief Executive Officer',
            'Engineering Lead',
            'Senior Software Engineer',
            'Software Engineer',
            'HR Manager',
            'HR Executive',
            'Chief Financial Officer',
            'Finance Officer',
            'Marketing Specialist',
            'Operations Coordinator',
        ];

        foreach ($designations as $desig) {
            Designation::firstOrCreate(['name' => $desig], ['is_active' => true]);
        }
    }

    private function seedHolidaysAndShifts(): void
    {
        $generalShift = Shift::firstOrCreate(
            ['name' => 'General'],
            [
                'start_time' => '08:30',
                'end_time' => '17:30',
                'break_minutes' => 60,
                'late_grace_minutes' => 15,
                'early_grace_minutes' => 15,
                'ot_after_minutes' => 30,
                'working_days' => [1, 2, 3, 4, 5],
                'is_default' => true,
                'is_active' => true,
            ]
        );

        Shift::firstOrCreate(
            ['name' => 'Morning Shift'],
            [
                'start_time' => '07:00',
                'end_time' => '15:30',
                'break_minutes' => 45,
                'late_grace_minutes' => 10,
                'early_grace_minutes' => 10,
                'ot_after_minutes' => 30,
                'working_days' => [1, 2, 3, 4, 5, 6],
                'is_default' => false,
                'is_active' => true,
            ]
        );

        $holidays = [
            ['date' => '2026-01-01', 'name' => 'New Year Day', 'type' => 'public'],
            ['date' => '2026-02-04', 'name' => 'National Independence Day', 'type' => 'public'],
            ['date' => '2026-04-13', 'name' => 'Sinhala & Tamil New Year Eve', 'type' => 'public'],
            ['date' => '2026-04-14', 'name' => 'Sinhala & Tamil New Year Day', 'type' => 'public'],
            ['date' => '2026-05-01', 'name' => 'May Day', 'type' => 'public'],
            ['date' => '2026-10-25', 'name' => 'Vap Full Moon Poya Day', 'type' => 'public'],
            ['date' => '2026-12-25', 'name' => 'Christmas Day', 'type' => 'public'],
        ];

        foreach ($holidays as $holiday) {
            Holiday::firstOrCreate(['date' => $holiday['date']], ['name' => $holiday['name'], 'type' => $holiday['type']]);
        }
    }

    private function seedUsersAndEmployees(): void
    {
        $branchHo = Branch::where('code', 'HO')->first();
        $branchKb = Branch::where('code', 'KB')->first();
        $permCat = EmployeeCategory::where('name', 'Permanent')->first() ?? EmployeeCategory::firstOrCreate(['name' => 'Permanent']);
        $probCat = EmployeeCategory::where('name', 'Probation')->first() ?? EmployeeCategory::firstOrCreate(['name' => 'Probation']);

        $deptExec = Department::where('code', 'EXEC')->first();
        $deptEng = Department::where('code', 'ENG')->first();
        $deptHr = Department::where('code', 'HR')->first();
        $deptFin = Department::where('code', 'FIN')->first();
        $deptMkt = Department::where('code', 'MKT')->first();

        $desigCeo = Designation::where('name', 'Chief Executive Officer')->first();
        $desigEngLead = Designation::where('name', 'Engineering Lead')->first();
        $desigSrDev = Designation::where('name', 'Senior Software Engineer')->first();
        $desigDev = Designation::where('name', 'Software Engineer')->first();
        $desigHrMgr = Designation::where('name', 'HR Manager')->first();
        $desigHrExec = Designation::where('name', 'HR Executive')->first();
        $desigCfo = Designation::where('name', 'Chief Financial Officer')->first();
        $desigFinOff = Designation::where('name', 'Finance Officer')->first();
        $desigMkt = Designation::where('name', 'Marketing Specialist')->first();

        // 1. Super Admin / Test User
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Super Admin', 'password' => 'password', 'email_verified_at' => now()]
        );
        $superAdmin->syncRoles(Permissions::SUPER_ADMIN);

        $testUser = User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => 'password', 'email_verified_at' => now()]
        );
        $testUser->syncRoles(Permissions::SUPER_ADMIN);

        // 2. HR Manager
        $hrUser = User::firstOrCreate(
            ['email' => 'hr@example.com'],
            ['name' => 'Kasun Perera', 'password' => 'password', 'email_verified_at' => now()]
        );
        $hrUser->syncRoles(Permissions::HR_MANAGER);

        // 3. Payroll Officer
        $payrollUser = User::firstOrCreate(
            ['email' => 'payroll@example.com'],
            ['name' => 'Dilshan Silva', 'password' => 'password', 'email_verified_at' => now()]
        );
        $payrollUser->syncRoles(Permissions::PAYROLL_OFFICER);

        // 4. Engineering Manager
        $mgrUser = User::firstOrCreate(
            ['email' => 'manager@example.com'],
            ['name' => 'Nimal Jayasinghe', 'password' => 'password', 'email_verified_at' => now()]
        );
        $mgrUser->syncRoles(Permissions::MANAGER);

        // 5. Regular Employee 1
        $emp1User = User::firstOrCreate(
            ['email' => 'employee@example.com'],
            ['name' => 'Chaminda Fernando', 'password' => 'password', 'email_verified_at' => now()]
        );
        $emp1User->syncRoles(Permissions::EMPLOYEE);

        // 6. Regular Employee 2
        $emp2User = User::firstOrCreate(
            ['email' => 'sarah@example.com'],
            ['name' => 'Sarah Wickramasinghe', 'password' => 'password', 'email_verified_at' => now()]
        );
        $emp2User->syncRoles(Permissions::EMPLOYEE);

        // Seed Employees
        // CEO
        $empCeo = Employee::firstOrCreate(
            ['employee_no' => 'EMP-0001'],
            [
                'user_id' => $testUser->id,
                'branch_id' => $branchHo->id,
                'department_id' => $deptExec->id,
                'designation_id' => $desigCeo->id,
                'employee_category_id' => $permCat->id,
                'full_name' => 'Test User (CEO)',
                'nic' => '198212345678',
                'date_of_birth' => '1982-05-14',
                'gender' => 'male',
                'marital_status' => 'married',
                'phone' => '+94 77 100 2000',
                'personal_email' => 'test@example.com',
                'address' => '45 Flower Road, Colombo 07',
                'emergency_contact_name' => 'Anoma Perera',
                'emergency_contact_phone' => '+94 77 100 2001',
                'join_date' => '2022-01-10',
                'confirmation_date' => '2022-04-10',
                'status' => 'active',
                'bank_name' => 'Commercial Bank',
                'bank_branch' => 'Kollupitiya',
                'bank_account_number' => '1000293847',
                'bank_account_name' => 'Test User',
                'epf_number' => 'EPF-001',
                'etf_number' => 'ETF-001',
                'tax_tin' => 'TIN-001234',
                'epf_applicable' => true,
                'etf_applicable' => true,
                'apit_applicable' => true,
                'ot_eligible' => false,
            ]
        );

        // Engineering Lead (Manager)
        $empMgr = Employee::firstOrCreate(
            ['employee_no' => 'EMP-0002'],
            [
                'user_id' => $mgrUser->id,
                'branch_id' => $branchHo->id,
                'department_id' => $deptEng->id,
                'designation_id' => $desigEngLead->id,
                'employee_category_id' => $permCat->id,
                'manager_id' => $empCeo->id,
                'full_name' => 'Nimal Jayasinghe',
                'nic' => '198634567890',
                'date_of_birth' => '1986-08-22',
                'gender' => 'male',
                'marital_status' => 'married',
                'phone' => '+94 77 234 5678',
                'personal_email' => 'nimal.j@gmail.com',
                'address' => '12 Temple Road, Dehiwala',
                'emergency_contact_name' => 'Kanthi Jayasinghe',
                'emergency_contact_phone' => '+94 77 234 5679',
                'join_date' => '2022-06-01',
                'confirmation_date' => '2022-09-01',
                'status' => 'active',
                'bank_name' => 'Sampath Bank',
                'bank_branch' => 'Dehiwala',
                'bank_account_number' => '2000384756',
                'bank_account_name' => 'Nimal Jayasinghe',
                'epf_number' => 'EPF-002',
                'etf_number' => 'ETF-002',
                'tax_tin' => 'TIN-002345',
                'epf_applicable' => true,
                'etf_applicable' => true,
                'apit_applicable' => true,
                'ot_eligible' => true,
            ]
        );

        // HR Manager
        $empHr = Employee::firstOrCreate(
            ['employee_no' => 'EMP-0003'],
            [
                'user_id' => $hrUser->id,
                'branch_id' => $branchHo->id,
                'department_id' => $deptHr->id,
                'designation_id' => $desigHrMgr->id,
                'employee_category_id' => $permCat->id,
                'manager_id' => $empCeo->id,
                'full_name' => 'Kasun Perera',
                'nic' => '198945678901',
                'date_of_birth' => '1989-11-05',
                'gender' => 'male',
                'marital_status' => 'single',
                'phone' => '+94 71 345 6789',
                'personal_email' => 'kasun.p@gmail.com',
                'address' => '78 High Level Road, Nugegoda',
                'emergency_contact_name' => 'Gamini Perera',
                'emergency_contact_phone' => '+94 71 345 6780',
                'join_date' => '2023-02-15',
                'confirmation_date' => '2023-05-15',
                'status' => 'active',
                'bank_name' => 'Commercial Bank',
                'bank_branch' => 'Nugegoda',
                'bank_account_number' => '3000495867',
                'bank_account_name' => 'Kasun Perera',
                'epf_number' => 'EPF-003',
                'etf_number' => 'ETF-003',
                'tax_tin' => 'TIN-003456',
                'epf_applicable' => true,
                'etf_applicable' => true,
                'apit_applicable' => false,
                'ot_eligible' => true,
            ]
        );

        // Payroll Officer
        $empPayroll = Employee::firstOrCreate(
            ['employee_no' => 'EMP-0004'],
            [
                'user_id' => $payrollUser->id,
                'branch_id' => $branchHo->id,
                'department_id' => $deptFin->id,
                'designation_id' => $desigFinOff->id,
                'employee_category_id' => $permCat->id,
                'manager_id' => $empCeo->id,
                'full_name' => 'Dilshan Silva',
                'nic' => '199156789012',
                'date_of_birth' => '1991-03-18',
                'gender' => 'male',
                'marital_status' => 'single',
                'phone' => '+94 76 456 7890',
                'personal_email' => 'dilshan.s@gmail.com',
                'address' => '24 Havelock Road, Colombo 05',
                'emergency_contact_name' => 'Sunil Silva',
                'emergency_contact_phone' => '+94 76 456 7891',
                'join_date' => '2023-05-01',
                'confirmation_date' => '2023-08-01',
                'status' => 'active',
                'bank_name' => 'Hatton National Bank',
                'bank_branch' => 'Havelock Town',
                'bank_account_number' => '4000506978',
                'bank_account_name' => 'Dilshan Silva',
                'epf_number' => 'EPF-004',
                'etf_number' => 'ETF-004',
                'tax_tin' => 'TIN-004567',
                'epf_applicable' => true,
                'etf_applicable' => true,
                'apit_applicable' => false,
                'ot_eligible' => true,
            ]
        );

        // Developer 1
        $empDev1 = Employee::firstOrCreate(
            ['employee_no' => 'EMP-0005'],
            [
                'user_id' => $emp1User->id,
                'branch_id' => $branchHo->id,
                'department_id' => $deptEng->id,
                'designation_id' => $desigSrDev->id,
                'employee_category_id' => $permCat->id,
                'manager_id' => $empMgr->id,
                'full_name' => 'Chaminda Fernando',
                'nic' => '199367890123',
                'date_of_birth' => '1993-07-29',
                'gender' => 'male',
                'marital_status' => 'single',
                'phone' => '+94 77 567 8901',
                'personal_email' => 'chaminda.f@gmail.com',
                'address' => '56 Station Road, Moratuwa',
                'emergency_contact_name' => 'Rohini Fernando',
                'emergency_contact_phone' => '+94 77 567 8902',
                'join_date' => '2023-09-01',
                'confirmation_date' => '2023-12-01',
                'status' => 'active',
                'bank_name' => 'Commercial Bank',
                'bank_branch' => 'Moratuwa',
                'bank_account_number' => '5000617089',
                'bank_account_name' => 'Chaminda Fernando',
                'epf_number' => 'EPF-005',
                'etf_number' => 'ETF-005',
                'tax_tin' => 'TIN-005678',
                'epf_applicable' => true,
                'etf_applicable' => true,
                'apit_applicable' => false,
                'ot_eligible' => true,
            ]
        );

        // Developer 2
        $empDev2 = Employee::firstOrCreate(
            ['employee_no' => 'EMP-0006'],
            [
                'user_id' => $emp2User->id,
                'branch_id' => $branchKb->id,
                'department_id' => $deptEng->id,
                'designation_id' => $desigDev->id,
                'employee_category_id' => $permCat->id,
                'manager_id' => $empMgr->id,
                'full_name' => 'Sarah Wickramasinghe',
                'nic' => '199578901234',
                'date_of_birth' => '1995-10-12',
                'gender' => 'female',
                'marital_status' => 'single',
                'phone' => '+94 70 678 9012',
                'personal_email' => 'sarah.w@gmail.com',
                'address' => '89 Peradeniya Road, Kandy',
                'emergency_contact_name' => 'Priyani Wickramasinghe',
                'emergency_contact_phone' => '+94 70 678 9013',
                'join_date' => '2024-01-15',
                'confirmation_date' => '2024-04-15',
                'status' => 'active',
                'bank_name' => 'Sampath Bank',
                'bank_branch' => 'Kandy',
                'bank_account_number' => '6000728190',
                'bank_account_name' => 'Sarah Wickramasinghe',
                'epf_number' => 'EPF-006',
                'etf_number' => 'ETF-006',
                'tax_tin' => 'TIN-006789',
                'epf_applicable' => true,
                'etf_applicable' => true,
                'apit_applicable' => false,
                'ot_eligible' => true,
            ]
        );

        // HR Executive (Probation)
        $empHrExec = Employee::firstOrCreate(
            ['employee_no' => 'EMP-0007'],
            [
                'branch_id' => $branchHo->id,
                'department_id' => $deptHr->id,
                'designation_id' => $desigHrExec->id,
                'employee_category_id' => $probCat->id,
                'manager_id' => $empHr->id,
                'full_name' => 'Ashan Wijetunga',
                'nic' => '199889012345',
                'date_of_birth' => '1998-04-03',
                'gender' => 'male',
                'marital_status' => 'single',
                'phone' => '+94 75 789 0123',
                'personal_email' => 'ashan.w@gmail.com',
                'address' => '15 Pagoda Road, Nugegoda',
                'emergency_contact_name' => 'Bandula Wijetunga',
                'emergency_contact_phone' => '+94 75 789 0124',
                'join_date' => '2026-08-01',
                'confirmation_date' => null,
                'status' => 'probation',
                'bank_name' => 'Commercial Bank',
                'bank_branch' => 'Nugegoda',
                'bank_account_number' => '7000839201',
                'bank_account_name' => 'Ashan Wijetunga',
                'epf_number' => 'EPF-007',
                'etf_number' => 'ETF-007',
                'tax_tin' => 'TIN-007890',
                'epf_applicable' => true,
                'etf_applicable' => true,
                'apit_applicable' => false,
                'ot_eligible' => true,
            ]
        );

        // Marketing Specialist
        $empMkt = Employee::firstOrCreate(
            ['employee_no' => 'EMP-0008'],
            [
                'branch_id' => $branchHo->id,
                'department_id' => $deptMkt->id,
                'designation_id' => $desigMkt->id,
                'employee_category_id' => $permCat->id,
                'manager_id' => $empCeo->id,
                'full_name' => 'Nadeesha Senanayake',
                'nic' => '199490123456',
                'date_of_birth' => '1994-12-19',
                'gender' => 'female',
                'marital_status' => 'single',
                'phone' => '+94 78 890 1234',
                'personal_email' => 'nadeesha.s@gmail.com',
                'address' => '33 Ward Place, Colombo 07',
                'emergency_contact_name' => 'Malini Senanayake',
                'emergency_contact_phone' => '+94 78 890 1235',
                'join_date' => '2024-03-01',
                'confirmation_date' => '2024-06-01',
                'status' => 'active',
                'bank_name' => 'Nations Trust Bank',
                'bank_branch' => 'Colombo 07',
                'bank_account_number' => '8000940312',
                'bank_account_name' => 'Nadeesha Senanayake',
                'epf_number' => 'EPF-008',
                'etf_number' => 'ETF-008',
                'tax_tin' => 'TIN-008901',
                'epf_applicable' => true,
                'etf_applicable' => true,
                'apit_applicable' => false,
                'ot_eligible' => true,
            ]
        );

        // Seed Salaries and Shift Assignments
        $salaryMap = [
            'EMP-0001' => ['basic' => 350000, 'allowance' => 50000],
            'EMP-0002' => ['basic' => 240000, 'allowance' => 30000],
            'EMP-0003' => ['basic' => 180000, 'allowance' => 25000],
            'EMP-0004' => ['basic' => 140000, 'allowance' => 20000],
            'EMP-0005' => ['basic' => 170000, 'allowance' => 20000],
            'EMP-0006' => ['basic' => 120000, 'allowance' => 15000],
            'EMP-0007' => ['basic' => 85000,  'allowance' => 10000],
            'EMP-0008' => ['basic' => 110000, 'allowance' => 15000],
        ];

        $generalShift = Shift::where('name', 'General')->first();
        $transportComp = SalaryComponent::where('name', 'Transport allowance')->first();
        $braComp = SalaryComponent::where('name', 'Budgetary Relief Allowance')->first();

        foreach (Employee::all() as $emp) {
            $salary = $salaryMap[$emp->employee_no] ?? ['basic' => 100000, 'allowance' => 10000];

            EmployeeSalary::firstOrCreate(
                ['employee_id' => $emp->id, 'effective_from' => $emp->join_date->toDateString()],
                [
                    'basic_salary' => $salary['basic'],
                    'fixed_allowances' => $salary['allowance'],
                    'reason' => 'Initial Placement',
                    'created_by' => $superAdmin->id,
                ]
            );

            if ($generalShift) {
                ShiftAssignment::firstOrCreate(
                    ['employee_id' => $emp->id, 'shift_id' => $generalShift->id, 'effective_from' => $emp->join_date->toDateString()],
                    ['effective_to' => null]
                );
            }

            if ($transportComp) {
                EmployeeComponent::firstOrCreate(
                    ['employee_id' => $emp->id, 'salary_component_id' => $transportComp->id, 'effective_from' => $emp->join_date->toDateString()],
                    ['amount' => 12000]
                );
            }

            if ($braComp) {
                EmployeeComponent::firstOrCreate(
                    ['employee_id' => $emp->id, 'salary_component_id' => $braComp->id, 'effective_from' => $emp->join_date->toDateString()],
                    ['amount' => 5000]
                );
            }
        }
    }

    private function seedLeaveData(): void
    {
        $year = 2026;
        $leaveTypes = LeaveType::all();
        $employees = Employee::all();

        foreach ($employees as $emp) {
            foreach ($leaveTypes as $type) {
                if ($type->annual_days !== null) {
                    LeaveBalance::firstOrCreate(
                        ['employee_id' => $emp->id, 'leave_type_id' => $type->id, 'year' => $year],
                        ['entitled' => $type->annual_days, 'carried_forward' => 0]
                    );
                }
            }
        }

        $casualType = LeaveType::where('name', 'Casual')->first();
        $annualType = LeaveType::where('name', 'Annual')->first();
        $medicalType = LeaveType::where('name', 'Medical')->first();

        $chaminda = Employee::where('employee_no', 'EMP-0005')->first();
        $sarah = Employee::where('employee_no', 'EMP-0006')->first();
        $ashan = Employee::where('employee_no', 'EMP-0007')->first();
        $hrUser = User::where('email', 'hr@example.com')->first();
        $mgrUser = User::where('email', 'manager@example.com')->first();

        // 1. Pending Leave Request (for Chaminda) -> shows up on Dashboard pending review!
        if ($chaminda && $casualType) {
            LeaveRequest::firstOrCreate(
                ['employee_id' => $chaminda->id, 'from_date' => '2026-10-20'],
                [
                    'leave_type_id' => $casualType->id,
                    'to_date' => '2026-10-21',
                    'days' => 2.0,
                    'half_day' => false,
                    'reason' => 'Family occasion out of Colombo',
                    'status' => 'pending',
                    'requested_by' => $chaminda->user_id,
                ]
            );
        }

        // 2. Approved past Leave Request (for Sarah)
        if ($sarah && $annualType) {
            LeaveRequest::firstOrCreate(
                ['employee_id' => $sarah->id, 'from_date' => '2026-09-18'],
                [
                    'leave_type_id' => $annualType->id,
                    'to_date' => '2026-09-19',
                    'days' => 2.0,
                    'half_day' => false,
                    'reason' => 'Personal travel',
                    'status' => 'approved',
                    'requested_by' => $sarah->user_id,
                    'decided_by' => $mgrUser?->id,
                    'decided_at' => '2026-09-15 10:00:00',
                    'decision_note' => 'Approved as requested',
                ]
            );
        }

        // 3. Rejected Leave Request (for Ashan)
        if ($ashan && $medicalType) {
            LeaveRequest::firstOrCreate(
                ['employee_id' => $ashan->id, 'from_date' => '2026-09-05'],
                [
                    'leave_type_id' => $medicalType->id,
                    'to_date' => '2026-09-05',
                    'days' => 1.0,
                    'half_day' => false,
                    'reason' => 'Medical appointment',
                    'status' => 'rejected',
                    'requested_by' => $ashan->user_id,
                    'decided_by' => $hrUser?->id,
                    'decided_at' => '2026-09-04 16:30:00',
                    'decision_note' => 'Please reschedule due to urgent client deadline',
                ]
            );
        }
    }

    private function seedAttendanceData(): void
    {
        $employees = Employee::all();
        $generalShift = Shift::where('name', 'General')->first();
        $today = Carbon::today();
        $superAdmin = User::where('email', 'admin@example.com')->first();
        $chaminda = Employee::where('employee_no', 'EMP-0005')->first();

        // Seed past 10 working days up to today
        for ($i = 9; $i >= 0; $i--) {
            $date = $today->copy()->subDays($i);

            // Skip weekends (Saturday=6, Sunday=0/7)
            if ($date->isWeekend()) {
                continue;
            }

            $dateStr = $date->toDateString();

            foreach ($employees as $idx => $emp) {
                // Today special statuses for dashboard variety
                if ($i === 0) {
                    if ($emp->employee_no === 'EMP-0007') {
                        // On leave today
                        AttendanceRecord::updateOrCreate(
                            ['employee_id' => $emp->id, 'date' => $dateStr],
                            ['shift_id' => $generalShift?->id, 'status' => 'leave', 'leave_fraction' => 1.0]
                        );

                        continue;
                    }

                    if ($emp->employee_no === 'EMP-0008') {
                        // Absent today
                        AttendanceRecord::updateOrCreate(
                            ['employee_id' => $emp->id, 'date' => $dateStr],
                            ['shift_id' => $generalShift?->id, 'status' => 'absent']
                        );

                        continue;
                    }

                    if ($emp->employee_no === 'EMP-0006') {
                        // Present with late minutes
                        $inTime = Carbon::parse($dateStr.' 09:12:00');
                        $outTime = Carbon::parse($dateStr.' 17:30:00');
                        AttendanceRecord::updateOrCreate(
                            ['employee_id' => $emp->id, 'date' => $dateStr],
                            [
                                'shift_id' => $generalShift?->id,
                                'status' => 'present',
                                'first_in' => $inTime,
                                'last_out' => $outTime,
                                'worked_minutes' => 438,
                                'late_minutes' => 27,
                                'early_minutes' => 0,
                                'ot_minutes' => 0,
                            ]
                        );
                        Punch::firstOrCreate(['device_user_id' => $emp->deviceId(), 'punched_at' => $inTime], ['employee_id' => $emp->id, 'source' => 'csv']);
                        Punch::firstOrCreate(['device_user_id' => $emp->deviceId(), 'punched_at' => $outTime], ['employee_id' => $emp->id, 'source' => 'csv']);

                        continue;
                    }
                }

                // Regular present days
                $inMinute = ($idx % 3) * 4; // slight variance
                $inTime = Carbon::parse($dateStr.' 08:'.str_pad((string) (20 + $inMinute), 2, '0', STR_PAD_LEFT).':00');
                $outTime = Carbon::parse($dateStr.' 17:35:00');

                AttendanceRecord::updateOrCreate(
                    ['employee_id' => $emp->id, 'date' => $dateStr],
                    [
                        'shift_id' => $generalShift?->id,
                        'status' => 'present',
                        'first_in' => $inTime,
                        'last_out' => $outTime,
                        'worked_minutes' => 495,
                        'late_minutes' => 0,
                        'early_minutes' => 0,
                        'ot_minutes' => 35,
                    ]
                );

                Punch::firstOrCreate(['device_user_id' => $emp->deviceId(), 'punched_at' => $inTime], ['employee_id' => $emp->id, 'source' => 'csv']);
                Punch::firstOrCreate(['device_user_id' => $emp->deviceId(), 'punched_at' => $outTime], ['employee_id' => $emp->id, 'source' => 'csv']);
            }
        }

        // Attendance Adjustment pending review (shows on Dashboard)
        if ($chaminda) {
            $yesterday = $today->copy()->subWeekday()->toDateString();
            AttendanceAdjustment::firstOrCreate(
                ['employee_id' => $chaminda->id, 'date' => $yesterday],
                [
                    'in_at' => Carbon::parse($yesterday.' 08:30:00'),
                    'out_at' => Carbon::parse($yesterday.' 17:30:00'),
                    'reason' => 'Fingerprint reader was offline during departure',
                    'status' => 'pending',
                    'requested_by' => $chaminda->user_id,
                ]
            );
        }
    }

    private function seedPayrollRuns(): void
    {
        $superAdmin = User::where('email', 'admin@example.com')->first();
        $payrollOfficer = User::where('email', 'payroll@example.com')->first();
        $settings = PayrollSetting::current()->only([
            'working_days_divisor', 'ot_hour_divisor', 'ot_weekday_rate', 'ot_holiday_rate',
            'epf_employee_rate', 'epf_employer_rate', 'etf_rate', 'epf_includes_allowances',
        ]);

        // 1. September 2026 Payroll Run (Status: submitted -> shows "Payroll awaiting approval" on Dashboard!)
        $runSep = PayrollRun::firstOrCreate(
            ['period' => '2026-09'],
            [
                'period_start' => '2026-09-01',
                'period_end' => '2026-09-30',
                'status' => 'submitted',
                'settings' => $settings,
                'prepared_by' => $payrollOfficer?->id ?? $superAdmin->id,
                'submitted_by' => $payrollOfficer?->id ?? $superAdmin->id,
                'submitted_at' => '2026-10-01 10:00:00',
                'review_note' => 'September regular monthly payroll for all departments submitted for approval.',
            ]
        );

        $this->generatePayslipsForRun($runSep);

        // 2. August 2026 Payroll Run (Status: locked -> fully closed, payslips available under portal!)
        $runAug = PayrollRun::firstOrCreate(
            ['period' => '2026-08'],
            [
                'period_start' => '2026-08-01',
                'period_end' => '2026-08-31',
                'status' => 'locked',
                'settings' => $settings,
                'prepared_by' => $payrollOfficer?->id ?? $superAdmin->id,
                'submitted_by' => $payrollOfficer?->id ?? $superAdmin->id,
                'submitted_at' => '2026-08-31 17:00:00',
                'approved_by' => $superAdmin->id,
                'approved_at' => '2026-09-01 09:30:00',
                'locked_by' => $superAdmin->id,
                'locked_at' => '2026-09-01 10:00:00',
                'review_note' => 'August payroll locked and paid.',
            ]
        );

        $this->generatePayslipsForRun($runAug);
    }

    private function generatePayslipsForRun(PayrollRun $run): void
    {
        $employees = Employee::with(['department', 'designation', 'currentSalary'])->get();

        foreach ($employees as $emp) {
            $salary = $emp->currentSalary;
            $basic = (float) ($salary?->basic_salary ?? 100000);
            $allowance = (float) ($salary?->fixed_allowances ?? 15000);
            $gross = $basic + $allowance + 17000; // transport (12k) + BRA (5k)

            $epfEmployee = round($basic * 0.08, 2);
            $epfEmployer = round($basic * 0.12, 2);
            $etf = round($basic * 0.03, 2);
            $totalDeductions = $epfEmployee;
            $netSalary = $gross - $totalDeductions;
            $employerContrib = $epfEmployer + $etf;

            $payslip = Payslip::firstOrCreate(
                ['payroll_run_id' => $run->id, 'employee_id' => $emp->id],
                [
                    'employee_no' => $emp->employee_no,
                    'employee_name' => $emp->full_name,
                    'department' => $emp->department?->name,
                    'designation' => $emp->designation?->name,
                    'bank_name' => $emp->bank_name,
                    'bank_branch' => $emp->bank_branch,
                    'bank_account_number' => $emp->bank_account_number,
                    'bank_account_name' => $emp->bank_account_name,
                    'epf_number' => $emp->epf_number,
                    'etf_number' => $emp->etf_number,
                    'basic_salary' => $basic,
                    'no_pay_days' => 0,
                    'ot_weekday_minutes' => 120,
                    'ot_holiday_minutes' => 0,
                    'epf_base' => $basic,
                    'gross' => $gross,
                    'total_deductions' => $totalDeductions,
                    'net_salary' => $netSalary,
                    'employer_contributions' => $employerContrib,
                ]
            );

            // Payslip lines
            $lines = [
                ['type' => 'earning', 'code' => 'BASIC', 'description' => 'Basic Salary', 'amount' => $basic],
                ['type' => 'earning', 'code' => 'FIXED_ALLOW', 'description' => 'Fixed Allowance', 'amount' => $allowance],
                ['type' => 'earning', 'code' => 'TRANS_ALLOW', 'description' => 'Transport Allowance', 'amount' => 12000],
                ['type' => 'earning', 'code' => 'BRA', 'description' => 'Budgetary Relief Allowance', 'amount' => 5000],
                ['type' => 'deduction', 'code' => 'EPF_EMP', 'description' => 'EPF (Employee 8%)', 'amount' => $epfEmployee],
                ['type' => 'employer', 'code' => 'EPF_EMPLR', 'description' => 'EPF (Employer 12%)', 'amount' => $epfEmployer],
                ['type' => 'employer', 'code' => 'ETF_EMPLR', 'description' => 'ETF (Employer 3%)', 'amount' => $etf],
            ];

            foreach ($lines as $line) {
                PayslipLine::firstOrCreate(
                    ['payslip_id' => $payslip->id, 'code' => $line['code']],
                    [
                        'type' => $line['type'],
                        'description' => $line['description'],
                        'amount' => $line['amount'],
                        'is_manual' => false,
                    ]
                );
            }
        }
    }

    private function seedNoticesAndDocuments(): void
    {
        $superAdmin = User::where('email', 'admin@example.com')->first();
        $deptEng = Department::where('code', 'ENG')->first();

        // 1. Pinned Notice (General)
        Notice::firstOrCreate(
            ['title' => 'Annual Town Hall & Q4 Strategy Meeting'],
            [
                'body' => 'All staff members are invited to attend our Annual Town Hall meeting on Friday, October 24th at the Main Auditorium and virtually via Teams. Join us to review company milestones and strategic goals for 2027.',
                'department_id' => null,
                'is_pinned' => true,
                'published_at' => Carbon::today()->subDays(2)->toDateString(),
                'expires_at' => Carbon::today()->addDays(20)->toDateString(),
                'created_by' => $superAdmin?->id,
            ]
        );

        // 2. Department Notice (Engineering)
        if ($deptEng) {
            Notice::firstOrCreate(
                ['title' => 'Scheduled Cloud Infrastructure Maintenance Window'],
                [
                    'body' => 'The DevOps team will be upgrading primary database instances this Saturday between 01:00 AM and 04:00 AM UTC. Please schedule non-critical jobs accordingly.',
                    'department_id' => $deptEng->id,
                    'is_pinned' => false,
                    'published_at' => Carbon::today()->subDay()->toDateString(),
                    'expires_at' => Carbon::today()->addDays(7)->toDateString(),
                    'created_by' => $superAdmin?->id,
                ]
            );
        }

        // 3. General Notice
        Notice::firstOrCreate(
            ['title' => 'Health & Medical Insurance Card Distribution'],
            [
                'body' => 'New medical insurance membership cards for the 2026/2027 policy year have been received from Sri Lanka Insurance. Please collect your card from the HR Department.',
                'department_id' => null,
                'is_pinned' => false,
                'published_at' => Carbon::today()->subDays(5)->toDateString(),
                'expires_at' => Carbon::today()->addDays(15)->toDateString(),
                'created_by' => $superAdmin?->id,
            ]
        );

        // Company Documents
        $storage = Storage::disk('local');
        $docs = [
            [
                'title' => 'Corporate Employee Handbook 2026',
                'category' => 'handbook',
                'filename' => 'handbook_2026.pdf',
                'original_name' => 'Corporate_Employee_Handbook_2026.pdf',
            ],
            [
                'title' => 'Code of Conduct & Ethics Policy',
                'category' => 'policy',
                'filename' => 'code_of_conduct.pdf',
                'original_name' => 'Code_of_Conduct_2026.pdf',
            ],
            [
                'title' => 'Medical Expense Reimbursement Claim Form',
                'category' => 'form',
                'filename' => 'medical_claim_form.pdf',
                'original_name' => 'Medical_Claim_Form.pdf',
            ],
        ];

        foreach ($docs as $doc) {
            $path = 'company-documents/'.$doc['filename'];
            if (! $storage->exists($path)) {
                $storage->put($path, "%PDF-1.4\n% Sample PDF document for Corporate HRM: ".$doc['title']);
            }

            CompanyDocument::firstOrCreate(
                ['title' => $doc['title']],
                [
                    'category' => $doc['category'],
                    'path' => $path,
                    'original_name' => $doc['original_name'],
                    'mime_type' => 'application/pdf',
                    'size' => 10240,
                    'uploaded_by' => $superAdmin?->id,
                ]
            );
        }
    }
}
