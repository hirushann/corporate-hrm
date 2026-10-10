<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_no', 20)->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('department_id')->constrained();
            $table->foreignId('designation_id')->constrained();
            $table->foreignId('employee_category_id')->constrained();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();

            // Personal
            $table->string('full_name');
            $table->string('nic', 20)->nullable()->unique();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('marital_status', 15)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('personal_email')->nullable();
            $table->string('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();

            // Employment
            $table->date('join_date');
            $table->date('confirmation_date')->nullable();
            $table->date('resignation_date')->nullable();
            $table->string('status', 15)->default('active')->index();

            // Bank (account number stored encrypted)
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->text('bank_account_number')->nullable();
            $table->string('bank_account_name')->nullable();

            // Statutory (TIN stored encrypted)
            $table->string('epf_number', 30)->nullable();
            $table->string('etf_number', 30)->nullable();
            $table->text('tax_tin')->nullable();
            $table->boolean('epf_applicable')->default(true);
            $table->boolean('etf_applicable')->default(true);
            $table->boolean('apit_applicable')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->decimal('basic_salary', 12, 2);
            $table->decimal('fixed_allowances', 12, 2)->default(0);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'effective_from']);
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->date('expires_on')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_salaries');
        Schema::dropIfExists('employees');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_active'));
    }
};
