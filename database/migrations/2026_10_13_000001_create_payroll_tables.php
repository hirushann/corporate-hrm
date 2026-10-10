<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('ot_eligible')->default(true)->after('apit_applicable');
        });

        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('working_days_divisor', 5, 2)->default(30);   // no-pay day rate = basic / this
            $table->decimal('ot_hour_divisor', 6, 2)->default(240);       // OT hourly rate = basic / this
            $table->decimal('ot_weekday_rate', 4, 2)->default(1.5);
            $table->decimal('ot_holiday_rate', 4, 2)->default(2.0);       // weekends and holidays
            $table->decimal('epf_employee_rate', 5, 2)->default(8);
            $table->decimal('epf_employer_rate', 5, 2)->default(12);
            $table->decimal('etf_rate', 5, 2)->default(3);
            $table->boolean('epf_includes_allowances')->default(false);   // false = EPF/ETF on (basic - no-pay) only
            $table->timestamps();
        });

        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('type', 10); // earning | deduction
            $table->boolean('epf_applicable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique(); // Y-m
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 10)->default('draft')->index(); // draft, submitted, approved, locked
            $table->json('settings'); // snapshot of the rates used
            $table->foreignId('prepared_by')->constrained('users');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->string('review_note')->nullable();
            $table->json('warnings')->nullable();
            $table->timestamps();
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained();

            // Snapshot so history never changes when the employee record does.
            $table->string('employee_no', 20);
            $table->string('employee_name');
            $table->string('department')->nullable();
            $table->string('designation')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->text('bank_account_number')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('epf_number', 30)->nullable();
            $table->string('etf_number', 30)->nullable();

            $table->decimal('basic_salary', 12, 2);            // monthly rate
            $table->decimal('no_pay_days', 5, 2)->default(0);
            $table->unsignedInteger('ot_weekday_minutes')->default(0);
            $table->unsignedInteger('ot_holiday_minutes')->default(0);
            $table->decimal('epf_base', 12, 2)->default(0);
            $table->decimal('gross', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);
            $table->decimal('employer_contributions', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('payslip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // earning | deduction | employer
            $table->string('code', 30);
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->boolean('is_manual')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payslip_lines', 'payslips', 'payroll_runs', 'employee_components', 'salary_components', 'payroll_settings'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn('ot_eligible'));
    }
};
