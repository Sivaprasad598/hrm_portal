<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Biometric -> Fetch Attendance (the Sync Calendar page, biometric.calendar):
 * pull a date range from the devices and write it into attendance. Back in the
 * sidebar for the Super Admin and the Admin only: the entry is switched on,
 * named "Fetch Attendance", needs view_biometric (the page itself also checks
 * the role - BiometricController::canFetch) and is assigned (Assign Menus) to
 * the admin and manager_admin roles, not HR. Found by route and role name, so
 * the same on every server. down() puts the entry back as it was.
 */
return new class extends Migration {

    private const ROUTE = 'biometric.calendar';

    private const ROLES = ['admin', 'manager_admin'];

    public function up(): void
    {
        if (!Schema::hasTable('sidebar_menus')) {
            return;
        }

        $menuIds = DB::table('sidebar_menus')->where('route', self::ROUTE)->pluck('id')->all();

        DB::table('sidebar_menus')->whereIn('id', $menuIds)->update(array_filter([
            'name' => 'Fetch Attendance',
            'is_active' => 1,
            'permission_key' => Schema::hasColumn('sidebar_menus', 'permission_key') ? 'view_biometric' : null,
            'updated_at' => now(),
        ], fn ($v) => $v !== null));

        if (Schema::hasTable('role_menus')) {
            $roleIds = DB::table('roles')->whereIn('name', self::ROLES)->pluck('id')->all();

            foreach ($menuIds as $menuId) {
                foreach ($roleIds as $roleId) {
                    if (!DB::table('role_menus')->where('role_id', $roleId)->where('menu_id', $menuId)->exists()) {
                        DB::table('role_menus')->insert(['role_id' => $roleId, 'menu_id' => $menuId, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }
        }

        $this->forgetMenus();
    }

    public function down(): void
    {
        if (!Schema::hasTable('sidebar_menus')) {
            return;
        }

        $menuIds = DB::table('sidebar_menus')->where('route', self::ROUTE)->pluck('id')->all();

        DB::table('sidebar_menus')->whereIn('id', $menuIds)->update(array_filter([
            'name' => 'Sync Calendar',
            'is_active' => 0,
            'permission_key' => Schema::hasColumn('sidebar_menus', 'permission_key') ? 'manage_biometric_system' : null,
            'updated_at' => now(),
        ], fn ($v) => $v !== null));

        if (Schema::hasTable('role_menus')) {
            $managerAdmin = DB::table('roles')->where('name', 'manager_admin')->pluck('id')->all();
            DB::table('role_menus')->whereIn('menu_id', $menuIds)->whereIn('role_id', $managerAdmin)->delete();
        }

        $this->forgetMenus();
    }

    private function forgetMenus(): void
    {
        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
