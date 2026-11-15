<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Settings -> Data Clean is for the Admin (manager_admin) as well as the Super
 * Admin: the page assigned (Assign Menus) to that role too. The page itself
 * checks the role (DataCleanController). Found by route and role name, so the
 * same on every server. down() takes the assignment away again.
 */
return new class extends Migration {

    private const ROUTE = 'data-clean.index';

    public function up(): void
    {
        if (!Schema::hasTable('role_menus') || !Schema::hasTable('sidebar_menus')) {
            return;
        }

        foreach ($this->menuIds() as $menuId) {
            foreach ($this->roleIds() as $roleId) {
                if (!DB::table('role_menus')->where('role_id', $roleId)->where('menu_id', $menuId)->exists()) {
                    DB::table('role_menus')->insert(['role_id' => $roleId, 'menu_id' => $menuId, 'created_at' => now(), 'updated_at' => now()]);
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
        return DB::table('roles')->where('name', 'manager_admin')->pluck('id')->all();
    }

    private function forgetMenus(): void
    {
        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
