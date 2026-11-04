<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recruitment opens for the Super Admin only, for now: every other role and
 * person is set to "none" on the three recruitment permissions and loses the
 * Recruitment menus. They are given back later from Settings -> Access.
 *
 * Recruitment Settings moves from the Recruitment group to Settings.
 */
return new class extends Migration {

    private const PERMISSIONS = ['view_recruitment', 'manage_recruitment', 'manage_recruitment_settings'];
    private const ALL = 4;
    private const NONE = 5;

    public function up(): void
    {
        $adminRole = DB::table('roles')->where('name', 'admin')->value('id');
        $admins = DB::table('role_user')->where('role_id', $adminRole)->pluck('user_id');
        $permissionIds = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->where('role_id', '<>', $adminRole)
            ->update(['permission_type_id' => self::NONE]);
        DB::table('user_permissions')->whereIn('permission_id', $permissionIds)->whereNotIn('user_id', $admins)
            ->update(['permission_type_id' => self::NONE]);
        DB::table('user_permissions')->whereIn('permission_id', $permissionIds)->whereIn('user_id', $admins)
            ->update(['permission_type_id' => self::ALL]);

        $settings = DB::table('sidebar_menus')->whereNull('parent_id')->where('slug', 'settings')->value('id');

        if ($settings) {
            DB::table('sidebar_menus')->where('slug', 'recruitment-settings-index')
                ->update(['parent_id' => $settings, 'sort_order' => 27, 'updated_at' => now()]);
        }

        $recruitment = DB::table('sidebar_menus')->whereNull('parent_id')->where('slug', 'recruitment')->value('id');
        $menuIds = DB::table('sidebar_menus')->where('parent_id', $recruitment)->pluck('id')->push($recruitment)
            ->push(DB::table('sidebar_menus')->where('slug', 'recruitment-settings-index')->value('id'))->filter();

        DB::table('role_menus')->whereIn('menu_id', $menuIds)->where('role_id', '<>', $adminRole)->delete();

        foreach (DB::table('users')->pluck('id') as $userId) {
            cache()->forget('sidebar_user_perms_' . $userId);
        }

        \App\Models\User::forgetAllMenuCaches();
    }

    public function down(): void
    {
        $recruitment = DB::table('sidebar_menus')->whereNull('parent_id')->where('slug', 'recruitment')->value('id');

        if ($recruitment) {
            DB::table('sidebar_menus')->where('slug', 'recruitment-settings-index')->update(['parent_id' => $recruitment, 'sort_order' => 6]);
        }

        \App\Models\User::forgetAllMenuCaches();
    }

};
