<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Arowana logo palette (copper #cb6b4f, black #231f20, grey #6d6e71) is now
 * the default look: the saved black-and-gold choice is cleared so it applies.
 * Another palette can still be picked in Settings > Theme Settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('global_settings')->where('epic_colors', 'like', '%b8892e%')->update(['epic_colors' => null]);
        cache()->flush();
    }

    public function down(): void
    {
        DB::table('global_settings')->whereNull('epic_colors')
            ->update(['epic_colors' => json_encode(['primary' => '#b8892e', 'accent' => '#cd6e4f', 'dark' => '#111111', 'strip' => '#111111'])]);
        cache()->flush();
    }
};
