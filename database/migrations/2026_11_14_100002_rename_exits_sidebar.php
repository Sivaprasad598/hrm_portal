<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR sidebar: the "Exits" entry (the group of Resignations / Exited Employees /
 * Termination) is named "Exited Employees". Found by its pages' slugs, so the same
 * on every server; down() puts "Exits" back.
 */
return new class extends Migration {

    private const PAGES = ['resignation-management-index', 'employee-exit-index', 'employee-termination-index'];

    public function up(): void
    {
        $this->group('Exits', 'Exited Employees');
    }

    public function down(): void
    {
        $this->group('Exited Employees', 'Exits');
    }

    private function group(string $from, string $to): void
    {
        if (!Schema::hasTable('sidebar_menus') || !Schema::hasColumn('sidebar_menus', 'tab_group')) {
            return;
        }

        DB::table('sidebar_menus')->whereIn('slug', self::PAGES)->where('tab_group', $from)
            ->update(['tab_group' => $to, 'updated_at' => now()]);

        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
