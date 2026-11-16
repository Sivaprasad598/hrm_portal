<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR -> Letter Generator (offer-letters.generate): offer and relieving letters
 * typed into a form, with the issued letters a click away. Its own entry in
 * the HR sub menu (after Employee Termination), for the Super Admin, the Admin
 * and HR (Assign Menus); the page itself also checks the role / Edit Employees.
 * Found by route and role name, so the same on every server; down() removes it.
 */
return new class extends Migration {

    private const ROUTE = 'offer-letters.generate';

    private const ROLES = ['admin', 'manager_admin', 'hr'];

    public function up(): void
    {
        if (!Schema::hasTable('sidebar_menus')) {
            return;
        }

        $hr = DB::table('sidebar_menus')->where('slug', 'hr')->whereNull('parent_id')->first(['id', 'company_id']);

        if (!$hr) {
            return;
        }

        $menuId = DB::table('sidebar_menus')->where('route', self::ROUTE)->value('id');

        if (!$menuId) {
            // After Employee Termination, before Salary Revisions.
            $after = (int) DB::table('sidebar_menus')->where('parent_id', $hr->id)->where('route', 'employee-termination.index')->value('sort_order');

            $menuId = DB::table('sidebar_menus')->insertGetId(array_filter([
                'company_id' => $hr->company_id,
                'parent_id' => $hr->id,
                'name' => 'Letter Generator',
                'slug' => 'letter-generator',
                'route' => self::ROUTE,
                'icon' => null,
                'level' => 1,
                'sort_order' => $after ? $after + 1 : 15,
                'is_active' => 1,
                'permission_key' => Schema::hasColumn('sidebar_menus', 'permission_key') ? 'edit_employees' : null,
                'permission_rule' => Schema::hasColumn('sidebar_menus', 'permission_rule') ? 'setting_all' : null,
                'created_at' => now(),
                'updated_at' => now(),
            ], fn ($v) => $v !== null) + ['icon' => null]);
        }

        if (Schema::hasTable('role_menus')) {
            foreach (DB::table('roles')->whereIn('name', self::ROLES)->pluck('id') as $roleId) {
                if (!DB::table('role_menus')->where('role_id', $roleId)->where('menu_id', $menuId)->exists()) {
                    DB::table('role_menus')->insert(['role_id' => $roleId, 'menu_id' => $menuId, 'created_at' => now(), 'updated_at' => now()]);
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

        $ids = DB::table('sidebar_menus')->where('route', self::ROUTE)->pluck('id');

        if (Schema::hasTable('role_menus')) {
            DB::table('role_menus')->whereIn('menu_id', $ids)->delete();
        }

        DB::table('sidebar_menus')->whereIn('id', $ids)->delete();
        $this->forgetMenus();
    }

    private function forgetMenus(): void
    {
        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
