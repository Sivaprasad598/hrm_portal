<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Biometric gets permissions of its own, and every menu entry names the
 * permission it needs, so Roles & Permissions and Assign Menus decide together.
 *
 * Biometric ran entirely on view_attendance = all: anyone who could see
 * attendance could open the device data, sync logs, pulls and the scheduler.
 * HR works with four things - who is in, what needs fixing, one person's
 * punches, linking a new joiner - so the module splits in four:
 *
 *   view_biometric               Today, Daily Review, Exceptions, Employee 360
 *   manage_biometric_exceptions  resolve exceptions, review days, get latest punches
 *   manage_biometric_mapping     link employees to device codes
 *   manage_biometric_system      device data, sync, punch log, devices, calendar,
 *                                logs, pipeline and scheduler, Biometric Settings
 *
 * Settings entries were rendered on their own permission checks but their menu
 * rows named none, so Assign Menus could not tell what a role was missing.
 */
return new class extends Migration
{

    private const ALL = 4;
    private const NONE = 5;

    /** permission => [display name, description, roles given it] */
    private const PERMISSIONS = [
        'view_biometric' => ['Biometric: View', 'Today, Daily Review, Exceptions and Employee 360.', ['admin', 'manager_admin', 'hr', 'ceo']],
        'manage_biometric_exceptions' => ['Biometric: Resolve Exceptions', 'Resolve exceptions, review days and get the latest punches.', ['admin', 'manager_admin', 'hr']],
        'manage_biometric_mapping' => ['Biometric: Link Device Codes', 'Link employees to their biometric device codes.', ['admin', 'manager_admin', 'hr']],
        'manage_biometric_system' => ['Biometric: Technical', 'Device data, sync, punch log, devices, calendar, logs, pipeline and Biometric Settings.', ['admin']],
    ];

    /** Biometric menu routes => permission. */
    private const BIOMETRIC_MENUS = [
        'biometric.overview' => 'view_biometric', 'biometric.review' => 'view_biometric',
        'biometric.exceptions' => 'view_biometric', 'biometric.profile' => 'view_biometric',
        'biometric.employees' => 'manage_biometric_mapping', 'biometric.comparison' => 'manage_biometric_mapping',
        'biometric.attendance' => 'manage_biometric_system', 'biometric.attendance-sync' => 'manage_biometric_system',
        'biometric.devices' => 'manage_biometric_system', 'biometric.shifts' => 'manage_biometric_system',
        'biometric.punch-log' => 'manage_biometric_system', 'biometric.calendar' => 'manage_biometric_system',
        'biometric.sync-logs' => 'manage_biometric_system', 'biometric.actions' => 'manage_biometric_system',
        'biometric-settings.index' => 'manage_biometric_system',
    ];

    /** Settings entries => the permission their page already checks. */
    private const SETTINGS_MENUS = [
        'app-settings.index' => 'manage_app_setting', 'app-launcher-settings.index' => 'manage_app_setting',
        'attendance-settings.index' => 'manage_attendance_setting', 'employee-shifts.index' => 'manage_attendance_setting',
        'business-address.index' => 'manage_company_setting', 'company-settings.index' => 'manage_company_setting',
        'sign-up-settings.index' => 'manage_company_setting', 'contract-settings.index' => 'manage_contract_setting',
        'currency-settings.index' => 'manage_currency_setting', 'custom-fields.index' => 'manage_custom_field_setting',
        'custom-link-settings.index' => 'manage_custom_link_setting', 'invoice-settings.index' => 'manage_finance_setting',
        'gdpr-settings.index' => 'manage_gdpr_setting', 'google-calendar-settings.index' => 'manage_google_calendar_setting',
        'language-settings.index' => 'manage_language_setting', 'lead-settings.index' => 'manage_lead_setting',
        'leaves-settings.index' => 'manage_leave_setting', 'message-settings.index' => 'manage_message_setting',
        'module-settings.index' => 'manage_module_setting', 'notifications.index' => 'manage_notification_setting',
        'payment-gateway-settings.index' => 'manage_payment_setting', 'project-settings.index' => 'manage_project_setting',
        'role-permissions.index' => 'manage_role_permission_setting', 'assign-menus.index' => 'manage_role_permission_setting',
        'menu-management.index' => 'manage_role_permission_setting', 'social-auth-settings.index' => 'manage_social_login_setting',
        'storage-settings.index' => 'manage_storage_setting', 'task-settings.index' => 'manage_task_setting',
        'taxes.index' => 'manage_tax', 'theme-settings.index' => 'manage_theme_setting',
        'ticket-settings.index' => 'manage_ticket_setting', 'timelog-settings.index' => 'manage_time_log_setting',
    ];

    public function up(): void
    {
        $moduleId = DB::table('modules')->where('module_name', 'attendance')->value('id');
        $roles = DB::table('roles')->get(['id', 'name']);

        foreach (self::PERMISSIONS as $name => [$display, $description, $holders]) {
            $permissionId = DB::table('permissions')->where('name', $name)->value('id') ?: DB::table('permissions')->insertGetId([
                'name' => $name, 'display_name' => $display, 'description' => $description, 'module_id' => $moduleId,
                'is_custom' => 1, 'allowed_permissions' => '{"all":4, "none":5}', 'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach ($roles as $role) {
                DB::table('permission_role')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $permissionId,
                    'permission_type_id' => in_array($role->name, $holders, true) ? self::ALL : self::NONE]);
            }

            // user_permissions is what the application reads: everybody gets a
            // row now, 'all' where one of their roles holds it.
            $holderUsers = DB::table('role_user as ru')->join('roles as r', 'r.id', '=', 'ru.role_id')
                ->whereIn('r.name', $holders)->pluck('ru.user_id')->unique()->flip();
            $rows = [];

            foreach (DB::table('users')->pluck('id') as $userId) {
                $rows[] = ['user_id' => $userId, 'permission_id' => $permissionId, 'permission_type_id' => isset($holderUsers[$userId]) ? self::ALL : self::NONE];
            }

            foreach (array_chunk($rows, 300) as $chunk) {
                DB::table('user_permissions')->insertOrIgnore($chunk);
            }
        }

        foreach (self::BIOMETRIC_MENUS + self::SETTINGS_MENUS as $route => $permission) {
            DB::table('sidebar_menus')->where('route', $route)->update(['permission_key' => $permission, 'permission_rule' => 'setting_all', 'updated_at' => now()]);
        }

        // Each role's sidebar matches what it holds. A linked entry it cannot
        // open comes off (it was hidden anyway, and left ticked it would hand
        // out its permission the next time the role is saved in Assign Menus).
        // The new Biometric and Settings links are also added where it can.
        $linked = DB::table('sidebar_menus')->whereNotNull('permission_key')->where('permission_key', 'not like', '%,%')
            ->get(['id', 'parent_id', 'route', 'permission_key', 'permission_rule']);
        $newLinks = array_keys(self::BIOMETRIC_MENUS + self::SETTINGS_MENUS);

        foreach ($roles as $role) {
            if ($role->name === 'admin') {
                continue;
            }

            $levels = \App\Services\Access\MenuPermissions::roleLevels((int) $role->id);

            foreach ($linked as $menu) {
                $opens = \App\Services\Access\MenuPermissions::levelPasses($levels[$menu->permission_key] ?? 'none', $menu->permission_rule ?: 'any');

                if (!$opens) {
                    DB::table('role_menus')->where('role_id', $role->id)->where('menu_id', $menu->id)->delete();
                } elseif (in_array($menu->route, $newLinks, true)) {
                    foreach (array_filter([$menu->id, $menu->parent_id]) as $menuId) {
                        DB::table('role_menus')->insertOrIgnore(['role_id' => $role->id, 'menu_id' => $menuId, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }
        }

        foreach (DB::table('users')->pluck('id') as $userId) {
            cache()->forget('sidebar_user_perms_' . $userId);
            cache()->forget('sidebar_menus_' . $userId);
        }
    }

    public function down(): void
    {
        DB::table('sidebar_menus')->whereIn('route', array_keys(self::SETTINGS_MENUS))->update(['permission_key' => null, 'permission_rule' => null]);
        DB::table('sidebar_menus')->whereIn('route', array_keys(self::BIOMETRIC_MENUS))->where('route', '<>', 'biometric-settings.index')
            ->update(['permission_key' => 'view_attendance', 'permission_rule' => 'all']);
        DB::table('sidebar_menus')->where('route', 'biometric-settings.index')->update(['permission_key' => null, 'permission_rule' => null]);

        $ids = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
        DB::table('user_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }

};
