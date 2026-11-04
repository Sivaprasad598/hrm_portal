<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HR > Attendance Dashboard as a page of its own in the sidebar (it was a tab of
 * Attendance), for the roles that see Attendance; and Attendance renamed
 * Attendance Register.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('sidebar_menus')->where('slug', 'attendances-index')->get() as $register) {
            DB::table('sidebar_menus')->where('id', $register->id)->update(['name' => 'Attendance Register', 'updated_at' => now()]);

            if (DB::table('sidebar_menus')->where('company_id', $register->company_id)->where('slug', 'attendance-dashboard')->exists()) {
                continue;
            }

            $id = DB::table('sidebar_menus')->insertGetId([
                'company_id' => $register->company_id,
                'parent_id' => $register->parent_id,
                'name' => 'Attendance Dashboard',
                'slug' => 'attendance-dashboard',
                'route' => 'attendances.dashboard',
                'icon' => null,
                'level' => $register->level,
                'sort_order' => $register->sort_order,
                'is_active' => 1,
                'permission_key' => $register->permission_key,
                'permission_rule' => $register->permission_rule,
                'role_rule' => $register->role_rule,
                'module' => $register->module,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // The same roles as Attendance Register
            $rows = DB::table('role_menus')->where('menu_id', $register->id)->pluck('role_id')
                ->map(fn ($roleId) => ['role_id' => $roleId, 'menu_id' => $id, 'created_at' => now(), 'updated_at' => now()])->all();
            DB::table('role_menus')->insert($rows);
        }
    }

    public function down(): void
    {
        $ids = DB::table('sidebar_menus')->where('slug', 'attendance-dashboard')->pluck('id');
        DB::table('role_menus')->whereIn('menu_id', $ids)->delete();
        DB::table('sidebar_menus')->whereIn('id', $ids)->delete();
        DB::table('sidebar_menus')->where('slug', 'attendances-index')->update(['name' => 'Attendance']);
    }
};
