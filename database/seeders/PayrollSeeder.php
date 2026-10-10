<?php

namespace Database\Seeders;

use App\Models\PayrollSetting;
use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;

class PayrollSeeder extends Seeder
{
    public function run(): void
    {
        PayrollSetting::current();

        foreach ([
            ['Transport allowance', 'earning', false],
            ['Budgetary Relief Allowance', 'earning', true],
            ['Salary advance recovery', 'deduction', false],
            ['Loan recovery', 'deduction', false],
        ] as [$name, $type, $epf]) {
            SalaryComponent::firstOrCreate(['name' => $name], ['type' => $type, 'epf_applicable' => $epf]);
        }
    }
}
