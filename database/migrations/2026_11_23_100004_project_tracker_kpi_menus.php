<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Work -> Employee KPIs (project-tracker.kpis) for the people who run the work, and
 * My Space -> My KPI (their own, the KPI tab of their employee page) for everyone with an
 * employee record - the roles that have My Space -> My Assets. The CFO also gets Work -> Project
 * Dashboard (the money card). Found by route, slug and role name, so the same on every server;
 * down() removes what it added.
 */
return new class extends Migration {

    private const KPI_ROLES = ['admin', 'ceo', 'cfo', 'hr', 'manager_admin'];

    public function up(): void
    {
        if (!Schema::hasTable('sidebar_menus')) {
            return;
        }

        $work = DB::table('sidebar_menus')->where('slug', 'work')->whereNull('parent_id')->first(['id', 'company_id']);
        if ($work) {
            $id = $this->menu('project-tracker.kpis', ['company_id' => $work->company_id, 'parent_id' => $work->id, 'name' => 'Employee KPIs',
                'slug' => 'employee-kpis', 'sort_order' => 3, 'permission_key' => 'view_projects', 'permission_rule' => 'any']);
            $this->give($id, DB::table('roles')->whereIn('name', self::KPI_ROLES)->pluck('id')->all());
        }

        // finance: the Project Dashboard's money card
        $dashboard = DB::table('sidebar_menus')->where('route', 'project-tracker.dashboard')->value('id');
        if ($dashboard) {
            $this->give((int) $dashboard, DB::table('roles')->where('name', 'cfo')->pluck('id')->all());
        }

        $space = DB::table('sidebar_menus')->where('slug', 'my-space')->whereNull('parent_id')->first(['id', 'company_id']);
        if ($space) {
            $sort = (int) DB::table('sidebar_menus')->where('parent_id', $space->id)->max('sort_order') + 1;
            $id = $this->menu('employees.show|{me}?tab=kpi', ['company_id' => $space->company_id, 'parent_id' => $space->id, 'name' => 'My KPI',
                'slug' => 'my-kpi', 'sort_order' => $sort, 'role_rule' => 'has_employee_record']);
            $assets = DB::table('sidebar_menus')->where('slug', 'my-assets')->value('id');
            $roles = $assets && Schema::hasTable('role_menus') ? DB::table('role_menus')->where('menu_id', $assets)->pluck('role_id')->all() : [];
            $this->give($id, $roles);
        }

        $this->forgetMenus();
    }

    public function down(): void
    {
        if (!Schema::hasTable('sidebar_menus')) {
            return;
        }

        $ids = DB::table('sidebar_menus')->whereIn('route', ['project-tracker.kpis', 'employees.show|{me}?tab=kpi'])->pluck('id');

        if (Schema::hasTable('role_menus')) {
            DB::table('role_menus')->whereIn('menu_id', $ids)->delete();
        }

        DB::table('sidebar_menus')->whereIn('id', $ids)->delete();

        $dashboard = DB::table('sidebar_menus')->where('route', 'project-tracker.dashboard')->value('id');
        if ($dashboard && Schema::hasTable('role_menus')) {
            DB::table('role_menus')->where('menu_id', $dashboard)->whereIn('role_id', DB::table('roles')->where('name', 'cfo')->select('id'))->delete();
        }
        $this->forgetMenus();
    }

    private function menu(string $route, array $row): int
    {
        $id = DB::table('sidebar_menus')->where('route', $route)->value('id');

        if ($id) {
            return (int) $id;
        }

        $columns = Schema::getColumnListing('sidebar_menus');

        return (int) DB::table('sidebar_menus')->insertGetId(array_intersect_key($row + ['route' => $route, 'level' => 1, 'is_active' => 1,
            'icon' => null, 'created_at' => now(), 'updated_at' => now()], array_flip($columns)));
    }

    private function give(int $menuId, array $roleIds): void
    {
        if (!Schema::hasTable('role_menus')) {
            return;
        }

        foreach ($roleIds as $roleId) {
            if (!DB::table('role_menus')->where('role_id', $roleId)->where('menu_id', $menuId)->exists()) {
                DB::table('role_menus')->insert(['role_id' => $roleId, 'menu_id' => $menuId, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    private function forgetMenus(): void
    {
        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
