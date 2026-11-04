<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Work and Assets (the headings and every page under them) leave the Admin and
 * HR roles; the Super Admin keeps them. Re-assign from Settings > Assign Menus.
 */
return new class extends Migration
{
    private const ROLES = ['hr', 'manager_admin'];
    private const HEADINGS = ['work', 'assets'];

    public function up(): void
    {
        $roleIds = DB::table('roles')->whereIn('name', self::ROLES)->pluck('id');
        DB::table('role_menus')->whereIn('role_id', $roleIds)->whereIn('menu_id', $this->menuIds())->delete();
        \App\Models\User::forgetAllMenuCaches();
    }

    public function down(): void
    {
        // What they had before: the headings plus Contracts/Projects/Tasks/Time Logs, and all of Assets
        $keep = ['work', 'contracts-index', 'projects-index', 'tasks-index', 'timelogs-index', 'assets', 'assets-index', 'assets-assigned', 'asset-types-index'];
        $menus = DB::table('sidebar_menus')->whereIn('slug', $keep)->pluck('id');
        foreach (DB::table('roles')->whereIn('name', self::ROLES)->pluck('id') as $roleId) {
            foreach ($menus as $menuId) {
                DB::table('role_menus')->updateOrInsert(['role_id' => $roleId, 'menu_id' => $menuId], ['updated_at' => now(), 'created_at' => now()]);
            }
        }
        \App\Models\User::forgetAllMenuCaches();
    }

    private function menuIds(): array
    {
        $ids = DB::table('sidebar_menus')->whereNull('parent_id')->whereIn('slug', self::HEADINGS)->pluck('id')->all();
        $all = $ids;
        while ($ids) {
            $ids = DB::table('sidebar_menus')->whereIn('parent_id', $ids)->pluck('id')->all();
            $all = array_merge($all, $ids);
        }

        return $all;
    }
};
