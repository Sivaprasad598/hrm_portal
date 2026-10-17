<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{

    /** The everyday flow, in the sidebar, in this order. */
    private array $daily = [
        'biometric.overview', 'biometric.exceptions', 'biometric.attendance',
        'biometric.attendance-sync', 'biometric.profile', 'biometric.employees',
    ];

    /** Still reachable from the Setup and History & tools menus on every biometric page. */
    private array $tucked = [
        'biometric.devices', 'biometric.shifts', 'biometric.punch-log', 'biometric.calendar',
        'biometric.sync-logs', 'biometric.comparison', 'biometric.actions',
    ];

    /**
     * Biometric sidebar: twelve entries down to the six used every day, with a
     * new Overview first. The other screens are not removed - they move into the
     * Setup and History & tools menus on the biometric pages themselves.
     */
    public function up(): void
    {
        $parents = DB::table('sidebar_menus')->where('slug', 'biometric')->whereNull('parent_id')->get();

        foreach ($parents as $parent) {
            $overview = DB::table('sidebar_menus')->where('company_id', $parent->company_id)->where('slug', 'biometric-overview')->first();

            if (!$overview) {
                $id = DB::table('sidebar_menus')->insertGetId([
                    'company_id' => $parent->company_id,
                    'parent_id' => $parent->id,
                    'name' => 'Overview',
                    'slug' => 'biometric-overview',
                    'route' => 'biometric.overview',
                    'icon' => null,
                    'level' => 1,
                    'sort_order' => 0,
                    'is_active' => 1,
                    'permission_key' => 'view_attendance',
                    'permission_rule' => 'all',
                    'role_rule' => null,
                    'module' => 'attendance',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Whoever can see the Biometric menu gets the Overview too.
                $roles = DB::table('role_menus')->where('menu_id', $parent->id)->pluck('role_id');

                foreach ($roles as $roleId) {
                    DB::table('role_menus')->insertOrIgnore([
                        'role_id' => $roleId, 'menu_id' => $id, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            $children = DB::table('sidebar_menus')->where('parent_id', $parent->id)->get(['id', 'route']);

            foreach ($children as $child) {
                $position = array_search($child->route, $this->daily, true);

                DB::table('sidebar_menus')->where('id', $child->id)->update([
                    'sort_order' => $position === false ? 100 + (array_search($child->route, $this->tucked, true) ?: 0) : $position + 1,
                    'is_active' => in_array($child->route, $this->tucked, true) ? 0 : 1,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('sidebar_menus')->whereIn('route', $this->tucked)->update(['is_active' => 1]);

        $ids = DB::table('sidebar_menus')->where('slug', 'biometric-overview')->pluck('id');
        DB::table('role_menus')->whereIn('menu_id', $ids)->delete();
        DB::table('sidebar_menus')->whereIn('id', $ids)->delete();
    }

};
