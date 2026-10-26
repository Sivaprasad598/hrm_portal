<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Punch Log - every in / out punch from the machines - is for HR too:
 * "I punched at 9:02" is answered there. It moves from Biometric: Technical
 * to Biometric: View, and into the sidebar after Employee 360, for every role
 * holding Biometric: View.
 */
return new class extends Migration
{

    public function up(): void
    {
        DB::table('sidebar_menus')->where('route', 'biometric.punch-log')->update([
            'permission_key' => 'view_biometric', 'permission_rule' => 'setting_all', 'is_active' => 1, 'sort_order' => 6, 'updated_at' => now(),
        ]);
        // Employee 360 and Employee Mapping move one place down, to keep the order.
        DB::table('sidebar_menus')->where('route', 'biometric.profile')->update(['sort_order' => 7]);
        DB::table('sidebar_menus')->where('route', 'biometric.employees')->update(['sort_order' => 8]);

        $menu = DB::table('sidebar_menus')->where('route', 'biometric.punch-log')->first(['id', 'parent_id']);
        $permissionId = DB::table('permissions')->where('name', 'view_biometric')->value('id');

        if (!$menu || !$permissionId) {
            return;
        }

        $roles = DB::table('permission_role as pr')->join('roles as r', 'r.id', '=', 'pr.role_id')
            ->where('pr.permission_id', $permissionId)->where('pr.permission_type_id', 4)->where('r.name', '<>', 'admin')->pluck('r.id');

        foreach ($roles as $roleId) {
            foreach (array_filter([$menu->id, $menu->parent_id]) as $menuId) {
                DB::table('role_menus')->insertOrIgnore(['role_id' => $roleId, 'menu_id' => $menuId, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        foreach (DB::table('users')->pluck('id') as $userId) {
            cache()->forget('sidebar_menus_' . $userId);
        }
    }

    public function down(): void
    {
        DB::table('sidebar_menus')->where('route', 'biometric.punch-log')->update([
            'permission_key' => 'manage_biometric_system', 'permission_rule' => 'setting_all', 'is_active' => 0, 'sort_order' => 102,
        ]);
        DB::table('sidebar_menus')->where('route', 'biometric.profile')->update(['sort_order' => 6]);
        DB::table('sidebar_menus')->where('route', 'biometric.employees')->update(['sort_order' => 7]);
    }

};
