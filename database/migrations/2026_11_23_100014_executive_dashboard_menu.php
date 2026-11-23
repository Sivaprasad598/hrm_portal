<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard -> Executive Dashboard (ExecutiveDashboardController), for the Super Admin, Admin, CEO and
 * CFO (asked for 2026-11-23). One entry under Dashboard, granted to those roles (the Super Admin has
 * every entry). Safe to run more than once; down() takes the entry out.
 */
return new class extends Migration {

    private const ROUTE = 'executive-dashboard.index';
    private const ROLES = ['manager_admin', 'ceo', 'cfo'];

    public function up(): void
    {
        if (!Schema::hasTable('sidebar_menus') || !Schema::hasTable('role_menus')) {
            return;
        }

        foreach (DB::table('sidebar_menus')->whereNull('parent_id')->where('slug', 'dashboard')->get() as $dashboard) {
            $id = DB::table('sidebar_menus')->where('route', self::ROUTE)->where('company_id', $dashboard->company_id)->value('id');

            if (!$id) {
                $id = DB::table('sidebar_menus')->insertGetId([
                    'company_id' => $dashboard->company_id, 'parent_id' => $dashboard->id, 'name' => 'Executive Dashboard',
                    'slug' => 'executive-dashboard', 'route' => self::ROUTE, 'icon' => 'bi bi-speedometer2', 'level' => 1,
                    'sort_order' => (int) DB::table('sidebar_menus')->where('parent_id', $dashboard->id)->max('sort_order') + 1,
                    'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $roles = DB::table('roles')->whereIn('name', array_merge(self::ROLES, ['admin']))
                ->when($dashboard->company_id, fn ($q) => $q->where('company_id', $dashboard->company_id))->pluck('id');

            foreach ($roles as $roleId) {
                if (!DB::table('role_menus')->where('role_id', $roleId)->where('menu_id', $id)->exists()) {
                    DB::table('role_menus')->insert(['role_id' => $roleId, 'menu_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }

        $this->forget();
    }

    public function down(): void
    {
        if (!Schema::hasTable('sidebar_menus')) {
            return;
        }

        $ids = DB::table('sidebar_menus')->where('route', self::ROUTE)->pluck('id');
        DB::table('role_menus')->whereIn('menu_id', $ids)->delete();
        DB::table('sidebar_menus')->whereIn('id', $ids)->delete();

        $this->forget();
    }

    private function forget(): void
    {
        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
