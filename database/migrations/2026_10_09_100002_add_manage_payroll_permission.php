<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Payroll gets a permission of its own.
 *
 * Every payroll screen — the run, the issued payslips, the register, the pay
 * elements, the dashboard — was gated on `in_array('admin', user_roles())`, the
 * administrator role itself. There is no payroll permission in this
 * installation at all, so there was no way to let anybody else near payroll
 * without making them an administrator of everything.
 *
 * `manage_payroll` is that permission. It is all-or-nothing, like the setting
 * permissions it sits beside: you either run payroll or you do not.
 *
 * Granted here to the administrator, Admin and HR roles. Everybody else gets
 * the row at 'none', which is how this application stores "no", so the
 * permission screens list it for every role rather than leaving gaps.
 */
return new class extends Migration
{

    private const NAME = 'manage_payroll';
    private const ALL = 4;
    private const NONE = 5;

    public function up(): void
    {
        if (DB::table('permissions')->where('name', self::NAME)->exists()) {
            return;
        }

        $permissionId = DB::table('permissions')->insertGetId([
            'name' => self::NAME,
            'display_name' => 'Manage Payroll',
            'description' => 'Run payroll, issue payslips and see what every employee was paid.',
            // Payroll is work done on employees, so it belongs to that module.
            'module_id' => DB::table('modules')->where('module_name', 'employees')->value('id'),
            'is_custom' => 1,
            'allowed_permissions' => '{"all":4, "none":5}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $holders = ['admin', 'manager_admin', 'hr'];

        foreach (DB::table('roles')->get(['id', 'name']) as $role) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $role->id,
                'permission_id' => $permissionId,
                'permission_type_id' => in_array($role->name, $holders, true) ? self::ALL : self::NONE,
            ]);
        }

        // user_permissions is what the application actually reads, and it is
        // written per user when a role is assigned — so the rows are filled in
        // here too rather than waiting for somebody's role to be reassigned.
        $rows = [];

        foreach (DB::table('users')->pluck('id') as $userId) {
            $own = DB::table('role_user as ru')->join('roles as r', 'r.id', '=', 'ru.role_id')
                ->where('ru.user_id', $userId)
                ->whereIn('r.name', $holders)
                ->exists();

            $rows[] = [
                'user_id' => $userId,
                'permission_id' => $permissionId,
                'permission_type_id' => $own ? self::ALL : self::NONE,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('user_permissions')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', self::NAME)->value('id');

        if (!$permissionId) {
            return;
        }

        DB::table('user_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }

};
