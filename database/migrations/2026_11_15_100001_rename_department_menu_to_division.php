<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Departments are called Divisions (Sub Departments: Sub Divisions) everywhere people
 * read them. The sidebar entry for the Divisions page was still named "Department".
 * Found by its slug, so the same on every server; down() puts "Department" back.
 */
return new class extends Migration {

    private const SLUG = 'departments-index';

    public function up(): void
    {
        $this->rename('Department', 'Division');
    }

    public function down(): void
    {
        $this->rename('Division', 'Department');
    }

    private function rename(string $from, string $to): void
    {
        if (!Schema::hasTable('sidebar_menus')) {
            return;
        }

        DB::table('sidebar_menus')->where('slug', self::SLUG)->where('name', $from)
            ->update(['name' => $to, 'updated_at' => now()]);

        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
