<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // ID the fingerprint device knows the employee by; falls back to employee_no when empty.
            $table->string('biometric_id', 30)->nullable()->unique()->after('employee_no');
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(60);
            $table->unsignedSmallInteger('late_grace_minutes')->default(15);
            $table->unsignedSmallInteger('early_grace_minutes')->default(15);
            $table->unsignedSmallInteger('ot_after_minutes')->default(30);
            $table->json('working_days'); // ISO weekdays 1 (Mon) - 7 (Sun)
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->string('type', 20)->default('public');
            $table->timestamps();
        });

        Schema::create('punches', function (Blueprint $table) {
            $table->id();
            $table->string('device_user_id', 30)->index();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('punched_at');
            $table->string('source', 20)->default('csv');
            $table->string('batch', 40)->nullable()->index();
            $table->timestamps();

            $table->unique(['device_user_id', 'punched_at']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 15); // present, absent, leave, holiday, off
            $table->dateTime('first_in')->nullable();
            $table->dateTime('last_out')->nullable();
            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('early_minutes')->default(0);
            $table->unsignedSmallInteger('ot_minutes')->default(0);
            $table->decimal('leave_fraction', 3, 1)->default(0);
            $table->boolean('missing_punch')->default(false);
            $table->boolean('is_adjusted')->default(false);
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index('date');
        });

        Schema::create('attendance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->dateTime('in_at')->nullable();
            $table->dateTime('out_at')->nullable();
            $table->string('reason');
            $table->string('status', 10)->default('pending')->index();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('annual_days', 5, 1)->nullable(); // null = unlimited
            $table->boolean('is_paid')->default(true);
            $table->decimal('carry_forward_max', 5, 1)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('entitled', 5, 1)->default(0);
            $table->decimal('carried_forward', 5, 1)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained();
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('days', 5, 1);
            $table->boolean('half_day')->default(false);
            $table->string('reason')->nullable();
            $table->string('status', 10)->default('pending')->index(); // pending, approved, rejected, cancelled
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'from_date', 'to_date']);
        });
    }

    public function down(): void
    {
        foreach (['leave_requests', 'leave_balances', 'leave_types', 'attendance_adjustments', 'attendance_records', 'punches', 'holidays', 'shift_assignments', 'shifts'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn('biometric_id'));
    }
};
