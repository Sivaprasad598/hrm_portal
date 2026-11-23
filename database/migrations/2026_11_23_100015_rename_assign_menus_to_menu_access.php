<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Settings -> "Assign Menus" is called "Menu Access" (2026-11-23): which menus each role can open.
 * Only the name people see; the route (assign-menus.index), files and slug stay as they are.
 * Safe to run more than once; down() puts the old name back.
 */
return new class extends Migration {

    public function up(): void
    {
        $this->rename('Assign Menus', 'Menu Access');
    }

    public function down(): void
    {
        $this->rename('Menu Access', 'Assign Menus');
    }

    private function rename(string $from, string $to): void
    {
        if (!Schema::hasTable('sidebar_menus')) {
            return;
        }

        DB::table('sidebar_menus')->where('route', 'assign-menus.index')->where('name', $from)->update(['name' => $to, 'updated_at' => now()]);

        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
