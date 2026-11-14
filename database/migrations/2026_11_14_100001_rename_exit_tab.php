<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR -> Exits: the "Exit" tab is named for what it lists, "Exited Employees".
 * Found by slug, so the same on every server; down() puts the old name back.
 */
return new class extends Migration {

    public function up(): void
    {
        $this->label('Exited Employees');
    }

    public function down(): void
    {
        $this->label('Exit');
    }

    private function label(string $text): void
    {
        if (!Schema::hasTable('sidebar_menus') || !Schema::hasColumn('sidebar_menus', 'tab_label')) {
            return;
        }

        DB::table('sidebar_menus')->where('slug', 'employee-exit-index')->update(['tab_label' => $text, 'updated_at' => now()]);

        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
