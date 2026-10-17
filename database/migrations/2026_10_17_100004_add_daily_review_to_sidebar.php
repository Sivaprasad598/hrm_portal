<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{

    private array $daily = [
        'biometric.overview', 'biometric.review', 'biometric.exceptions', 'biometric.attendance',
        'biometric.attendance-sync', 'biometric.profile', 'biometric.employees',
    ];

    /** Biometric → Daily Review, second in the sidebar, for whoever sees Biometric. */
    public function up(): void
    {
        foreach (DB::table('sidebar_menus')->where('slug', 'biometric')->whereNull('parent_id')->get() as $parent) {
            if (!DB::table('sidebar_menus')->where('company_id', $parent->company_id)->where('slug', 'biometric-review')->exists()) {
                $id = DB::table('sidebar_menus')->insertGetId([
                    'company_id' => $parent->company_id,
                    'parent_id' => $parent->id,
                    'name' => 'Daily Review',
                    'slug' => 'biometric-review',
                    'route' => 'biometric.review',
                    'level' => 1,
                    'sort_order' => 2,
                    'is_active' => 1,
                    'permission_key' => 'view_attendance',
                    'permission_rule' => 'all',
                    'module' => 'attendance',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach (DB::table('role_menus')->where('menu_id', $parent->id)->pluck('role_id') as $roleId) {
                    DB::table('role_menus')->insertOrIgnore(['role_id' => $roleId, 'menu_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }

            foreach ($this->daily as $position => $route) {
                DB::table('sidebar_menus')->where('parent_id', $parent->id)->where('route', $route)
                    ->update(['sort_order' => $position + 1]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('sidebar_menus')->where('slug', 'biometric-review')->pluck('id');
        DB::table('role_menus')->whereIn('menu_id', $ids)->delete();
        DB::table('sidebar_menus')->whereIn('id', $ids)->delete();
    }

};
