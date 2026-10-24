<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Leads > Lead Generation Bot, below Deals. Administrators only: role_rule
 * 'admin' hides it from everyone else even if Assign Menus grants it, and the
 * admin role is granted it here (a menu with no grant reaches nobody).
 */
return new class extends Migration
{

    public function up(): void
    {
        $now = now();

        foreach (DB::table('sidebar_menus')->whereNull('parent_id')->where('slug', 'leads')->get() as $leads) {
            $exists = DB::table('sidebar_menus')
                ->where('parent_id', $leads->id)
                ->where('route', 'lead-generation-bot.index')
                ->exists();

            if ($exists) {
                continue;
            }

            $menuId = DB::table('sidebar_menus')->insertGetId([
                'company_id' => $leads->company_id,
                'parent_id' => $leads->id,
                'name' => 'Lead Generation Bot',
                'slug' => 'lead-generation-bot-index',
                'route' => 'lead-generation-bot.index',
                'icon' => null,
                'level' => 1,
                'sort_order' => (int) DB::table('sidebar_menus')->where('parent_id', $leads->id)->max('sort_order') + 1,
                'is_active' => 1,
                'permission_key' => null,
                'permission_rule' => null,
                'role_rule' => 'admin',
                'module' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $adminRoles = DB::table('roles')->where('name', 'admin')->where('company_id', $leads->company_id)->pluck('id');

            DB::table('role_menus')->insertOrIgnore($adminRoles->map(fn ($roleId) => [
                'role_id' => $roleId,
                'menu_id' => $menuId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }

        User::forgetAllMenuCaches();
    }

    public function down(): void
    {
        $ids = DB::table('sidebar_menus')->where('route', 'lead-generation-bot.index')->pluck('id');

        DB::table('role_menus')->whereIn('menu_id', $ids)->delete();
        DB::table('sidebar_menus')->whereIn('id', $ids)->delete();

        User::forgetAllMenuCaches();
    }

};
