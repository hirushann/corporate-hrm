<?php

use App\Http\Controllers\CompanyDocumentController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\ReportExportController;
use App\Livewire\Admin;
use App\Livewire\Attendance;
use App\Livewire\Dashboard;
use App\Livewire\Employees;
use App\Livewire\Leave;
use App\Livewire\Organization;
use App\Livewire\Payroll;
use App\Livewire\Portal;
use App\Livewire\Reports;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('dashboard', Dashboard::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('organization')->middleware('permission:organization.view')->group(function () {
        Route::get('company', Organization\CompanyProfile::class)->name('organization.company');
        Route::get('branches', Organization\Branches::class)->name('organization.branches');
        Route::get('departments', Organization\Departments::class)->name('organization.departments');
        Route::get('designations', Organization\Designations::class)->name('organization.designations');
        Route::get('categories', Organization\EmployeeCategories::class)->name('organization.categories');
    });

    Route::prefix('employees')->middleware('permission:employees.view')->group(function () {
        Route::get('/', Employees\Index::class)->name('employees.index');
        Route::get('create', Employees\Form::class)->middleware('permission:employees.create')->name('employees.create');
        Route::get('documents/{document}', EmployeeDocumentController::class)->name('employees.documents.download');
        Route::get('{employee}', Employees\Show::class)->name('employees.show');
        Route::get('{employee}/edit', Employees\Form::class)->middleware('permission:employees.update')->name('employees.edit');
    });

    Route::prefix('attendance')->group(function () {
        Route::get('/', Attendance\Index::class)->middleware('permission:attendance.view')->name('attendance.index');
        Route::get('import', Attendance\Import::class)->middleware('permission:attendance.manage')->name('attendance.import');
        Route::get('adjustments', Attendance\Adjustments::class)->middleware('permission:attendance.adjust')->name('attendance.adjustments');
        Route::get('roster', Attendance\Roster::class)->middleware('permission:shifts.view')->name('attendance.roster');
        Route::get('shifts', Attendance\Shifts::class)->middleware('permission:shifts.view')->name('attendance.shifts');
        Route::get('holidays', Attendance\Holidays::class)->middleware('permission:shifts.view')->name('attendance.holidays');
    });

    Route::prefix('leave')->middleware('permission:leave.view')->group(function () {
        Route::get('/', Leave\Requests::class)->name('leave.requests');
        Route::get('balances', Leave\Balances::class)->name('leave.balances');
        Route::get('types', Leave\LeaveTypes::class)->name('leave.types');
    });

    Route::prefix('payroll')->group(function () {
        Route::middleware('permission:payroll.view')->group(function () {
            Route::get('/', Payroll\Index::class)->name('payroll.index');
            Route::get('settings', Payroll\Settings::class)->name('payroll.settings');
            Route::get('components', Payroll\SalaryComponents::class)->name('payroll.components');
            Route::get('runs/{run}', Payroll\Show::class)->name('payroll.show');
            Route::get('runs/{run}/export', [PayslipController::class, 'salarySheet'])->name('payroll.export');
        });
    });
    Route::get('payslips/{payslip}/pdf', [PayslipController::class, 'pdf'])->name('payslips.pdf');

    // Employee self-service ("My ..."): always scoped to the signed-in user's own employee record.
    Route::prefix('my')->group(function () {
        Route::get('profile', Portal\Profile::class)->middleware('permission:portal.use')->name('portal.profile');
        Route::get('payslips', Portal\Payslips::class)->middleware('permission:portal.use')->name('portal.payslips');
        Route::get('attendance', Portal\Attendance::class)->middleware('permission:portal.use')->name('portal.attendance');
        Route::get('leave', Portal\Leave::class)->middleware('permission:portal.use')->name('portal.leave');
    });
    Route::get('notices', Portal\Notices::class)->middleware('permission:notices.view')->name('portal.notices');
    Route::get('documents', Portal\Documents::class)->middleware('permission:documents.view')->name('portal.documents');
    Route::get('documents/{document}/download', CompanyDocumentController::class)->name('documents.download');

    Route::get('admin/notices', Admin\Notices::class)->middleware('permission:notices.manage')->name('admin.notices');
    Route::get('admin/documents', Admin\CompanyDocuments::class)->middleware('permission:documents.manage')->name('admin.documents');

    Route::get('reports', Reports\Index::class)->name('reports.index');
    Route::get('reports/{key}', Reports\Show::class)->name('reports.show');
    Route::get('reports/{key}/export/{format}', ReportExportController::class)->name('reports.export');

    Route::get('admin/users', Admin\Users::class)->middleware('permission:users.view')->name('admin.users');
    Route::get('admin/audit-log', Admin\AuditLog::class)->middleware('permission:audit.view')->name('admin.audit');
});

require __DIR__.'/auth.php';
