<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * New punches are pulled and written into attendance every 5 minutes through
 * the day (the scheduler asks every 5 minutes; Biometric Settings -> "Pull new
 * punches every" sets the pace, now on screen). The stored 15 (from
 * 2026_11_11_100002), or nothing at all, becomes 5. A value chosen on the page
 * other than those is left alone. down() goes back to 15.
 */
return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasTable('iapp_settings') && Schema::hasColumn('iapp_settings', 'sync_interval_minutes')) {
            DB::table('iapp_settings')
                ->where(fn ($q) => $q->whereNull('sync_interval_minutes')->orWhere('sync_interval_minutes', 15))
                ->update(['sync_interval_minutes' => 5]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('iapp_settings') && Schema::hasColumn('iapp_settings', 'sync_interval_minutes')) {
            DB::table('iapp_settings')->where('sync_interval_minutes', 5)->update(['sync_interval_minutes' => 15]);
        }
    }

};
