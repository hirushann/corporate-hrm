<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, OrganizationSeeder::class, AttendanceSeeder::class, PayrollSeeder::class]);

        $password = env('ADMIN_PASSWORD') ?: Str::password(16);

        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            ['name' => 'Super Admin', 'password' => $password, 'email_verified_at' => now()],
        );
        $admin->syncRoles(Permissions::SUPER_ADMIN);

        if ($admin->wasRecentlyCreated && ! env('ADMIN_PASSWORD')) {
            $this->command?->warn("Super Admin created: {$admin->email} / {$password}  (change it after first login)");
        }
    }
}
