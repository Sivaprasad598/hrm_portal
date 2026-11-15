<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Settings -> Biometric Settings is assigned (Assign Menus) to the HR and Admin
 * roles, as Settings -> Attendance Settings already is. It was assigned to the
 * Super Admin role only, so the Settings list hid it from HR and the Admin even
 * with the right permission (see 2026_11_15_100002). Menu and roles are found by
 * route and role name, so the same on every server; a role that already has it
 * is left as it is. down() takes the two assignments away again.
 */
return new class extends Migration {

    private const ROUTE = 'biometric-settings.index';

    // Admin only: HR was taken off again the same day (2026_11_15_100004).
    private const ROLES = ['manager_admin'];

    public function up(): void
    {
        if (!Schema::hasTable('role_menus') || !Schema::hasTable('sidebar_menus')) {
            return;
        }

        foreach ($this->menuIds() as $menuId) {
            foreach ($this->roleIds() as $roleId) {
                $exists = DB::table('role_menus')->where('role_id', $roleId)->where('menu_id', $menuId)->exists();

                if (!$exists) {
                    DB::table('role_menus')->insert([
                        'role_id' => $roleId, 'menu_id' => $menuId, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->forgetMenus();
    }

    public function down(): void
    {
        if (!Schema::hasTable('role_menus') || !Schema::hasTable('sidebar_menus')) {
            return;
        }

        DB::table('role_menus')->whereIn('menu_id', $this->menuIds())->whereIn('role_id', $this->roleIds())->delete();

        $this->forgetMenus();
    }

    private function menuIds(): array
    {
        return DB::table('sidebar_menus')->where('route', self::ROUTE)->pluck('id')->all();
    }

    private function roleIds(): array
    {
        return DB::table('roles')->whereIn('name', self::ROLES)->pluck('id')->all();
    }

    private function forgetMenus(): void
    {
        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
