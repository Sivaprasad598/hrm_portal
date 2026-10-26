<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Leave is self-service.
 *
 * Every member of staff applies for their own leave and sees their own leave
 * and calendar. Employees (and the other roles without leave access - CFO,
 * developers, team leads...) had View Leave and Add Leave at None, so most of
 * the workforce could not apply at all. They get View Leave: Owned (their own,
 * and a reporting manager's team) and Add Leave: Added (themselves).
 *
 * Nobody applies for somebody else: a new permission, "Apply Leave For
 * Others", is None for every role but the Super Admin - it can be given later
 * (field staff without a login). The Leave Management dashboard, which picks
 * any employee, is for those who see everybody's leave.
 */
return new class extends Migration
{

    private const ALL = 4;
    private const OWNED = 2;
    private const ADDED = 1;
    private const NONE = 5;

    public function up(): void
    {
        $moduleId = DB::table('modules')->where('module_name', 'leaves')->value('id');
        $forOthers = DB::table('permissions')->where('name', 'add_leave_for_others')->value('id') ?: DB::table('permissions')->insertGetId([
            'name' => 'add_leave_for_others', 'display_name' => 'Apply Leave For Others',
            'description' => 'Apply leave on behalf of another employee, and set its status while applying.',
            'module_id' => $moduleId, 'is_custom' => 1, 'allowed_permissions' => '{"all":4, "none":5}', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $roles = DB::table('roles')->get(['id', 'name']);
        $admins = DB::table('role_user as ru')->join('roles as r', 'r.id', '=', 'ru.role_id')->where('r.name', 'admin')->pluck('ru.user_id')->flip();

        foreach ($roles as $role) {
            DB::table('permission_role')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $forOthers,
                'permission_type_id' => $role->name === 'admin' ? self::ALL : self::NONE]);
        }

        $rows = [];

        foreach (DB::table('users')->pluck('id') as $userId) {
            $rows[] = ['user_id' => $userId, 'permission_id' => $forOthers, 'permission_type_id' => isset($admins[$userId]) ? self::ALL : self::NONE];
        }

        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('user_permissions')->insertOrIgnore($chunk);
        }

        // Staff roles without leave access get their own. Clients stay out.
        $viewLeave = DB::table('permissions')->where('name', 'view_leave')->value('id');
        $addLeave = DB::table('permissions')->where('name', 'add_leave')->value('id');

        foreach ($roles as $role) {
            if (in_array($role->name, ['admin', 'client'], true)) {
                continue;
            }

            $roleModel = \App\Models\Role::find($role->id);

            if ((int) DB::table('permission_role')->where('role_id', $role->id)->where('permission_id', $viewLeave)->value('permission_type_id') === self::NONE
                || !DB::table('permission_role')->where('role_id', $role->id)->where('permission_id', $viewLeave)->exists()) {
                \App\Services\Access\MenuPermissions::grant($roleModel, 'view_leave', 'owned');
                \App\Services\Access\MenuPermissions::permissionChanged((int) $role->id, (int) $viewLeave, self::OWNED);
            }

            if ((int) DB::table('permission_role')->where('role_id', $role->id)->where('permission_id', $addLeave)->value('permission_type_id') === self::NONE
                || !DB::table('permission_role')->where('role_id', $role->id)->where('permission_id', $addLeave)->exists()) {
                \App\Services\Access\MenuPermissions::grant($roleModel, 'add_leave', 'added');
            }
        }

        // The Leave Management dashboard picks any employee: for those who see everybody's leave.
        DB::table('sidebar_menus')->where('route', 'leave-management.index')->update(['permission_rule' => 'all', 'updated_at' => now()]);
        $menu = DB::table('sidebar_menus')->where('route', 'leave-management.index')->value('id');

        foreach ($roles as $role) {
            $level = DB::table('permission_role')->where('role_id', $role->id)->where('permission_id', $viewLeave)->value('permission_type_id');

            if ($menu && $role->name !== 'admin' && (int) $level !== self::ALL) {
                DB::table('role_menus')->where('role_id', $role->id)->where('menu_id', $menu)->delete();
            }
        }

        foreach (DB::table('users')->pluck('id') as $userId) {
            cache()->forget('sidebar_user_perms_' . $userId);
            cache()->forget('sidebar_menus_' . $userId);
        }
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('name', 'add_leave_for_others')->value('id');
        DB::table('user_permissions')->where('permission_id', $id)->delete();
        DB::table('permission_role')->where('permission_id', $id)->delete();
        DB::table('permissions')->where('id', $id)->delete();
        DB::table('sidebar_menus')->where('route', 'leave-management.index')->update(['permission_rule' => 'any']);
    }

};
