<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Appreciation is not used: its menu entries are switched off, so it leaves the
 * sidebar, Assign Menus and the dashboard (which follows assigned menus). The
 * rows and any appreciations on record stay; down() brings the menus back.
 */
return new class extends Migration
{
    const SLUGS = ['appreciations-index', 'awards-index'];

    public function up(): void
    {
        DB::table('sidebar_menus')->whereIn('slug', self::SLUGS)->update(['is_active' => 0]);
        $this->forgetSidebars();
    }

    public function down(): void
    {
        DB::table('sidebar_menus')->whereIn('slug', self::SLUGS)->update(['is_active' => 1]);
        $this->forgetSidebars();
    }

    private function forgetSidebars(): void
    {
        foreach (DB::table('users')->pluck('id') as $id) {
            cache()->forget('sidebar_menus_' . $id);
        }
    }
};
