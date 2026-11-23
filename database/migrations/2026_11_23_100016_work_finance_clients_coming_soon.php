<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Work, Finance and Clients are marked Coming soon (asked for 2026-11-23): their menus stay with a
 * "Soon" tag and their pages show the Coming soon page to everyone but the Super Admin, who goes on
 * building them (App\Services\Access\ComingSoon). Settings -> Menu Access -> Modules switches them
 * back on one at a time. Safe to run more than once; down() opens the three again.
 */
return new class extends Migration {

    /** The modules' top-level menu entries, by slug. */
    private const SLUGS = ['work', 'finance', 'clients-index'];

    public function up(): void
    {
        $this->mark(true);
    }

    public function down(): void
    {
        $this->mark(false);
    }

    private function mark(bool $soon): void
    {
        if (!Schema::hasTable('sidebar_menus') || !Schema::hasColumn('sidebar_menus', 'coming_soon')) {
            return;
        }

        DB::table('sidebar_menus')->whereNull('parent_id')->whereIn('slug', self::SLUGS)
            ->update(['coming_soon' => $soon, 'updated_at' => now()]);

        if (class_exists(\App\Services\Access\ComingSoon::class)) {
            \App\Services\Access\ComingSoon::forget();
        }

        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
