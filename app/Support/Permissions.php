<?php

namespace App\Support;

/**
 * Single source of truth for roles and their permissions.
 * Permission names follow "<module>.<action>".
 */
final class Permissions
{
    public const SUPER_ADMIN = 'Super Admin';

    public const HR_MANAGER = 'HR Manager';

    public const PAYROLL_OFFICER = 'Payroll Officer';

    public const MANAGER = 'Manager';

    public const EMPLOYEE = 'Employee';

    /** @return array<string, list<string>> module => actions */
    public static function modules(): array
    {
        return [
            'organization' => ['view', 'manage'],
            'employees' => ['view', 'create', 'update', 'delete', 'view-salary', 'manage-documents'],
            'attendance' => ['view', 'manage', 'adjust', 'approve-adjustment'],
            'shifts' => ['view', 'manage'],
            'leave' => ['view', 'manage', 'approve'],
            'payroll' => ['view', 'process', 'approve', 'lock', 'settings'],
            'reports' => ['attendance', 'payroll', 'statutory', 'headcount', 'export'],
            'notices' => ['view', 'manage'],
            'documents' => ['view', 'manage'],
            'portal' => ['use'],
            'users' => ['view', 'manage'],
            'roles' => ['manage'],
            'audit' => ['view'],
            'backups' => ['manage'],
        ];
    }

    /** @return list<string> */
    public static function all(): array
    {
        $names = [];
        foreach (self::modules() as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = "$module.$action";
            }
        }

        return $names;
    }

    /** @return array<string, list<string>> role => permissions (Super Admin gets everything via Gate::before) */
    public static function roles(): array
    {
        return [
            self::SUPER_ADMIN => self::all(),
            self::HR_MANAGER => [
                'organization.view', 'organization.manage',
                'employees.view', 'employees.create', 'employees.update', 'employees.delete', 'employees.view-salary', 'employees.manage-documents',
                'attendance.view', 'attendance.manage', 'attendance.adjust', 'attendance.approve-adjustment',
                'shifts.view', 'shifts.manage',
                'leave.view', 'leave.manage', 'leave.approve',
                'payroll.view', 'payroll.approve',
                'reports.attendance', 'reports.payroll', 'reports.statutory', 'reports.headcount', 'reports.export',
                'notices.view', 'notices.manage',
                'documents.view', 'documents.manage',
                'portal.use',
                'users.view', 'users.manage',
            ],
            self::PAYROLL_OFFICER => [
                'organization.view',
                'employees.view', 'employees.view-salary',
                'attendance.view',
                'leave.view',
                'payroll.view', 'payroll.process',
                'reports.attendance', 'reports.payroll', 'reports.statutory', 'reports.export',
                'notices.view', 'documents.view', 'portal.use',
            ],
            self::MANAGER => [
                'employees.view',
                'attendance.view', 'attendance.adjust',
                'leave.view', 'leave.approve',
                'reports.attendance', 'reports.headcount',
                'notices.view', 'documents.view', 'portal.use',
            ],
            self::EMPLOYEE => [
                'notices.view', 'documents.view', 'portal.use',
            ],
        ];
    }
}
