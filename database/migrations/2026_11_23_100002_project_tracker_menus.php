<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Work -> Project Dashboard (project-tracker.dashboard) and Work -> Resource Planner
 * (project-tracker.planner): for the roles that see Projects, not clients. Found by route and
 * role name, so the same on every server; down() removes them.
 */
return new class extends Migration {

    private const MENUS = [
        ['project-tracker.dashboard', 'Project Dashboard', 'project-dashboard', 0],
        ['project-tracker.planner', 'Resource Planner', 'resource-planner', 2],
    ];

    private const ROLES = ['admin', 'ceo', 'hr', 'manager_admin'];

    public function up(): void
    {
        if (!Schema::hasTable('sidebar_menus')) {
            return;
        }

        $work = DB::table('sidebar_menus')->where('slug', 'work')->whereNull('parent_id')->first(['id', 'company_id']);

        if (!$work) {
            return;
        }

        foreach (self::MENUS as [$route, $name, $slug, $sort]) {
            $menuId = DB::table('sidebar_menus')->where('route', $route)->value('id');

            if (!$menuId) {
                $menuId = DB::table('sidebar_menus')->insertGetId(array_filter([
                    'company_id' => $work->company_id,
                    'parent_id' => $work->id,
                    'name' => $name,
                    'slug' => $slug,
                    'route' => $route,
                    'level' => 1,
                    'sort_order' => $sort,
                    'is_active' => 1,
                    'permission_key' => Schema::hasColumn('sidebar_menus', 'permission_key') ? 'view_projects' : null,
                    'permission_rule' => Schema::hasColumn('sidebar_menus', 'permission_rule') ? 'any' : null,
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
        }

        $this->forgetMenus();
    }

    public function down(): void
    {
        if (!Schema::hasTable('sidebar_menus')) {
            return;
        }

        $ids = DB::table('sidebar_menus')->whereIn('route', array_column(self::MENUS, 0))->pluck('id');

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
