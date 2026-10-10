<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_no' => fn () => 'EMP-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'branch_id' => fn () => Branch::query()->first()?->id ?? Branch::create([
                'company_id' => Company::firstOrCreate([], ['name' => 'Test Co'])->id,
                'name' => 'Head Office', 'code' => 'HO'.fake()->unique()->numerify('###'),
            ])->id,
            'department_id' => fn () => Department::firstOrCreate(['name' => 'Operations'])->id,
            'designation_id' => fn () => Designation::firstOrCreate(['name' => 'Executive'])->id,
            'employee_category_id' => fn () => EmployeeCategory::firstOrCreate(['name' => 'Permanent'])->id,
            'full_name' => fake()->name(),
            'nic' => fake()->unique()->numerify('############'),
            'join_date' => fake()->dateTimeBetween('-5 years', '-1 month'),
            'status' => 'active',
        ];
    }
}
