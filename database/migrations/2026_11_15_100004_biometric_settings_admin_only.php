<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Settings -> Biometric Settings is for the Super Admin and the Admin only, not
 * HR: the HR role's assignment of the page (Assign Menus) is removed. The page
 * itself checks the role too (BiometricSettingController). Found by route and
 * role name, so the same on every server; down() gives HR the page back.
 */
return new class extends Migration {

    private const ROUTE = 'biometric-settings.index';

    public function up(): void
    {
        if (!$this->ready()) {
            return;
        }

        DB::table('role_menus')->whereIn('menu_id', $this->menuIds())->whereIn('role_id', $this->hrIds())->delete();
        $this->forgetMenus();
    }

    public function down(): void
    {
        if (!$this->ready()) {
            return;
        }

        foreach ($this->menuIds() as $menuId) {
            foreach ($this->hrIds() as $roleId) {
                if (!DB::table('role_menus')->where('role_id', $roleId)->where('menu_id', $menuId)->exists()) {
                    DB::table('role_menus')->insert(['role_id' => $roleId, 'menu_id' => $menuId, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }

        $this->forgetMenus();
    }

    private function ready(): bool
    {
        return Schema::hasTable('role_menus') && Schema::hasTable('sidebar_menus');
    }

    private function menuIds(): array
    {
        return DB::table('sidebar_menus')->where('route', self::ROUTE)->pluck('id')->all();
    }

    private function hrIds(): array
    {
        return DB::table('roles')->where('name', 'hr')->pluck('id')->all();
    }

    private function forgetMenus(): void
    {
        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
