<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Employees (and the other staff roles) no longer see the Work menu (Tasks and
 * the rest): it stays with the roles that still have it (Super Admin, CEO, ...)
 * and can be given back in Settings > Assign Menus.
 */
return new class extends Migration
{
    private const STAFF = ['employee', 'team_lead', 'manager', 'gis_engineer', 'developer'];

    private function pairs(): array
    {
        $pairs = [];

        foreach (DB::table('sidebar_menus')->whereNull('parent_id')->where('slug', 'work')->get(['id', 'company_id']) as $work) {
            $menuIds = DB::table('sidebar_menus')->where('parent_id', $work->id)->pluck('id')->push($work->id)->all();
            $roleIds = DB::table('roles')->where('company_id', $work->company_id)->whereIn('name', self::STAFF)->pluck('id')->all();
            $pairs[] = [$roleIds, $menuIds];
        }

        return $pairs;
    }

    public function up(): void
    {
        foreach ($this->pairs() as [$roleIds, $menuIds]) {
            DB::table('role_menus')->whereIn('role_id', $roleIds)->whereIn('menu_id', $menuIds)->delete();
        }

        \App\Models\User::forgetAllMenuCaches();
    }

    public function down(): void
    {
        // what they had before: the Work heading and Tasks
        foreach ($this->pairs() as [$roleIds, $menuIds]) {
            $keep = DB::table('sidebar_menus')->whereIn('id', $menuIds)->whereIn('slug', ['work', 'tasks-index'])->pluck('id');
            foreach ($roleIds as $roleId) {
                foreach ($keep as $menuId) {
                    DB::table('role_menus')->updateOrInsert(['role_id' => $roleId, 'menu_id' => $menuId], ['created_at' => now(), 'updated_at' => now()]);
                }
            }
        }

        \App\Models\User::forgetAllMenuCaches();
    }
};
