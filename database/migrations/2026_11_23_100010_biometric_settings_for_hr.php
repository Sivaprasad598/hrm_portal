<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

/**
 * Settings -> Biometric Settings for the HR role too (it was Super Admin and Admin only, decided
 * 2026-11-15; HR added 2026-11-23). The page checks the same roles (BiometricSettingController::ROLES).
 * Safe to run more than once; down() takes HR off again.
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('sidebar_menus') || !Schema::hasTable('role_menus')) {
            return;
        }

        $menu = DB::table('sidebar_menus')->where('route', 'biometric-settings.index')->value('id');
        $hr = DB::table('roles')->where('name', 'hr')->pluck('id');

        foreach ($menu ? $hr : [] as $roleId) {
            if (!DB::table('role_menus')->where('role_id', $roleId)->where('menu_id', $menu)->exists()) {
                DB::table('role_menus')->insert(['role_id' => $roleId, 'menu_id' => $menu, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $this->forgetMenus();
    }

    public function down(): void
    {
        if (!Schema::hasTable('sidebar_menus') || !Schema::hasTable('role_menus')) {
            return;
        }

        $menu = DB::table('sidebar_menus')->where('route', 'biometric-settings.index')->value('id');

        if ($menu) {
            DB::table('role_menus')->where('menu_id', $menu)->whereIn('role_id', DB::table('roles')->where('name', 'hr')->select('id'))->delete();
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
