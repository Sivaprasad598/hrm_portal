<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "My Team -> Attendance Requests" off the sidebar for every role.
 *
 * Only the menu grants are removed: the menu entry and the page stay, so it can
 * be given back to a role from Settings -> Assign Menus if it is ever wanted.
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('sidebar_menus') || !Schema::hasTable('role_menus')) {
            return;
        }

        $menuIds = DB::table('sidebar_menus')
            ->where('slug', 'attendance-requests')
            ->orWhere('route', 'attendances.by_request')
            ->pluck('id');

        if ($menuIds->isEmpty()) {
            return;
        }

        DB::table('role_menus')->whereIn('menu_id', $menuIds)->delete();

        // Sidebars are cached per person.
        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

    public function down(): void
    {
        // Grants removed are not restored automatically; give the menu back
        // from Settings -> Assign Menus.
    }

};
