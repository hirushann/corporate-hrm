<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\EmployeeCategory;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate([], ['name' => config('app.name')]);

        Branch::firstOrCreate(['code' => 'HO'], ['company_id' => $company->id, 'name' => 'Head Office']);

        foreach (['Permanent', 'Probation', 'Contract', 'Casual', 'Intern'] as $name) {
            EmployeeCategory::firstOrCreate(['name' => $name]);
        }
    }
}
