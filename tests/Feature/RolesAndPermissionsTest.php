<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    public function test_all_five_roles_are_seeded(): void
    {
        foreach ([Permissions::SUPER_ADMIN, Permissions::HR_MANAGER, Permissions::PAYROLL_OFFICER, Permissions::MANAGER, Permissions::EMPLOYEE] as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role]);
        }
    }

    public function test_super_admin_passes_every_check(): void
    {
        $this->assertTrue($this->userWithRole(Permissions::SUPER_ADMIN)->can('payroll.lock'));
    }

    public function test_employee_cannot_see_salary_or_process_payroll(): void
    {
        $user = $this->userWithRole(Permissions::EMPLOYEE);

        $this->assertFalse($user->can('employees.view-salary'));
        $this->assertFalse($user->can('payroll.process'));
    }

    public function test_payroll_officer_prepares_but_cannot_approve_payroll(): void
    {
        $user = $this->userWithRole(Permissions::PAYROLL_OFFICER);

        $this->assertTrue($user->can('payroll.process'));
        $this->assertFalse($user->can('payroll.approve'));
    }

    public function test_login_screen_and_dashboard_guard(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
