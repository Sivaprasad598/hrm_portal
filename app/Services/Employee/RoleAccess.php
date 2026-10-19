<?php

namespace App\Services\Employee;

use Illuminate\Support\Facades\DB;

/**
 * What the HR, Admin and CEO roles may use (the CEO role is the Chairman / MD and the CEO): the dashboards, HR (employees,
 * attendance, leave, shifts, holidays, exits), Biometric, Payroll and the
 * settings pages that belong to them (Attendance, Shift, Biometric, Leave,
 * Notice Period, Payslip and AI settings).
 *
 * Company-wide controls stay with the Super Admin: company / account details,
 * sign-up, business addresses, roles & permissions, modules, Data Clean.
 * Nothing here can delete (no delete_* permission is given).
 *
 * Applied by a migration to the roles on file, and again whenever a role is
 * made afresh (after Data Clean the import re-creates HR and Admin).
 */
class RoleAccess
{

    public const ROLES = ['hr', 'manager_admin', 'ceo'];

    /** Granted at "all". */
    public const PERMISSIONS = [
        // dashboards
        'view_overview_dashboard', 'view_hr_dashboard',
        // employees
        'add_employees', 'view_employees', 'edit_employees', 'view_employee_menu', 'add_documents', 'view_documents',
        'edit_documents', 'view_leaves_taken', 'update_leaves_quota', 'manage_emergency_contact',
        'add_designation', 'view_designation', 'edit_designation', 'add_department', 'view_department', 'edit_department',
        'add_appreciation', 'view_appreciation', 'edit_appreciation', 'manage_award',
        'view_increment_promotion', 'manage_increment_promotion',
        // payroll
        'manage_payroll',
        // attendance, shifts, biometric (Biometric runs on the attendance permissions)
        'view_attendance', 'add_attendance', 'edit_attendance', 'view_shift_roster', 'manage_employee_shifts',
        // holidays and leave
        'add_holiday', 'view_holiday', 'edit_holiday', 'add_leave', 'view_leave', 'edit_leave', 'approve_or_reject_leaves',
        // their settings pages
        'manage_attendance_setting', 'manage_leave_setting',
    ];

    /** Sidebar entries granted, by route (and the group headings they sit under). */
    public const MENU_ROUTES = [
        'dashboard', 'dashboard.advanced',
        // HR
        'departments.index', 'designations.index', 'employees.index', 'identity.index', 'probation-requests.index', 'attendances.index',
        'shifts.index', 'holidays.index', 'appreciations.index', 'resignations.index', 'resignation-management.index',
        'employee-exit.index', 'employee-termination.index',
        // Leave Management
        'leaves.index', 'leave-management.index', 'leave-year-end.index',
        // Payroll
        'my-payslips.index', 'payslips.index', 'pay-elements.index', 'payroll-run.index', 'payslips.generated',
        'payroll-register.index', 'payroll-dashboard.index', 'income-tax.index', 'statutory-returns.index', 'settlements.index',
        // Biometric
        'biometric.overview', 'biometric.review', 'biometric.exceptions', 'biometric.attendance', 'biometric.attendance-sync',
        'biometric.profile', 'biometric.employees', 'biometric.devices', 'biometric.shifts', 'biometric.punch-log',
        'biometric.calendar', 'biometric.sync-logs', 'biometric.comparison', 'biometric.actions',
        // Settings
        'attendance-settings.index', 'employee-shifts.index', 'biometric-settings.index', 'leaves-settings.index',
        'notice-period-settings.index', 'payslip-settings.index', 'profile-settings.index',
    ];

    /** Give every HR / Admin role on file its access. @return array<string, int> users updated per role */
    public static function applyAll(): array
    {
        $done = [];

        foreach (self::ROLES as $name) {
            $roleId = DB::table('roles')->where('name', $name)->value('id');

            if ($roleId) {
                $done[$name] = self::apply((int) $roleId);
            }
        }

        return $done;
    }

    /** @return int how many people with the role had their permissions refreshed */
    public static function apply(int $roleId): int
    {
        $all = (int) DB::table('permission_types')->where('name', 'all')->value('id');
        $ids = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->pluck('id');

        foreach ($ids as $permissionId) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                ['permission_type_id' => $all]
            );
        }

        // The menu entries, with the headings above them.
        $menus = DB::table('sidebar_menus')->whereIn('route', self::MENU_ROUTES)->get(['id', 'parent_id']);
        $menuIds = $menus->pluck('id')->merge($menus->pluck('parent_id'))->filter()->unique();

        foreach ($menuIds as $menuId) {
            if (!DB::table('role_menus')->where('role_id', $roleId)->where('menu_id', $menuId)->exists()) {
                DB::table('role_menus')->insert(['role_id' => $roleId, 'menu_id' => $menuId, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        // People with the role work with its permissions (the extra role decides them).
        $users = DB::table('role_user')->where('role_id', $roleId)->pluck('user_id');

        foreach ($users as $userId) {
            if ($user = \App\Models\User::withoutGlobalScopes()->find($userId)) {
                $user->assignUserRolePermission($roleId);
                cache()->forget('sidebar_user_perms_' . $userId);
                cache()->forget('sidebar_menus_' . $userId);
            }
        }

        return $users->count();
    }

}
