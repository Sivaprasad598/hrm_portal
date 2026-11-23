<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll -> Payroll Import was added with module "payroll", which no sidebar entry
 * uses, so it was never shown. It takes Payroll Run's settings instead: no module,
 * shown to whoever may prepare payroll. Safe to run more than once.
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('sidebar_menus')) {
            return;
        }

        $columns = Schema::getColumnListing('sidebar_menus');

        DB::table('sidebar_menus')->where('route', 'payroll-import.index')->update(array_intersect_key([
            'module' => null, 'permission_key' => 'payroll_prepare', 'permission_rule' => 'setting_all', 'updated_at' => now(),
        ], array_flip($columns)));

        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

    public function down(): void
    {
        // nothing to undo: the entry was not visible before
    }

};
