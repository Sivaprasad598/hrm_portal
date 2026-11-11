<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The device sync runs every 15 minutes (Console\Kernel), so a punch shows as
 * clocked in on the dashboard soon after. "Sync every (minutes)" gated it to
 * 30 or 60; bring it down to match. Not editable on screen.
 */
return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasTable('iapp_settings') && Schema::hasColumn('iapp_settings', 'sync_interval_minutes')) {
            DB::table('iapp_settings')
                ->where(fn ($q) => $q->whereNull('sync_interval_minutes')->orWhere('sync_interval_minutes', '>', 15))
                ->update(['sync_interval_minutes' => 15]);
        }
    }

    public function down(): void
    {
        // The earlier value is not kept; 30 was the default in use.
        if (Schema::hasTable('iapp_settings') && Schema::hasColumn('iapp_settings', 'sync_interval_minutes')) {
            DB::table('iapp_settings')->where('sync_interval_minutes', 15)->update(['sync_interval_minutes' => 30]);
        }
    }

};
