<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finance -> Invoices for HR and the CFO (the CFO had it already; given again if missing).
 * Found by route and role name, so the same on every server. Idempotent; down() takes it back from HR.
 */
return new class extends Migration {

    public function up(): void
    {
        foreach ($this->menuIds() as $menuId) {
            foreach (DB::table('roles')->whereIn('name', ['hr', 'cfo'])->pluck('id') as $roleId) {
                if (!DB::table('role_menus')->where('role_id', $roleId)->where('menu_id', $menuId)->exists()) {
                    DB::table('role_menus')->insert(['role_id' => $roleId, 'menu_id' => $menuId, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
        $this->forget();
    }

    public function down(): void
    {
        $ids = $this->menuIds();
        if ($ids) {
            DB::table('role_menus')->whereIn('menu_id', $ids)->whereIn('role_id', DB::table('roles')->where('name', 'hr')->select('id'))->delete();
        }
        $this->forget();
    }

    /** Finance -> Invoices and its Finance parent. */
    private function menuIds(): array
    {
        if (!Schema::hasTable('sidebar_menus') || !Schema::hasTable('role_menus')) {
            return [];
        }
        $menu = DB::table('sidebar_menus')->where('route', 'invoices.index')->first(['id', 'parent_id']);

        return array_values(array_filter([$menu?->id, $menu?->parent_id]));
    }

    private function forget(): void
    {
        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
