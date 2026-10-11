<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consecutive failed chunks, so a blip does not stop a long pull.
 *
 * The link to the device server drops occasionally — a step failed mid-run with
 * "Could not reach the iApp server" while the chunk either side of it succeeded.
 * Pausing on the first such failure would strand an overnight backfill, so the
 * run keeps going and only pauses once several in a row have failed.
 *
 * Reset to zero by any chunk that succeeds, so this counts a run of failures,
 * not failures in total.
 */
return new class extends Migration
{

    public function up(): void
    {
        if (!Schema::hasTable('iapp_backfill_runs') || Schema::hasColumn('iapp_backfill_runs', 'error_count')) {
            return;
        }

        Schema::table('iapp_backfill_runs', function (Blueprint $table) {
            $table->unsignedSmallInteger('error_count')->default(0)->after('last_error');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('iapp_backfill_runs') || !Schema::hasColumn('iapp_backfill_runs', 'error_count')) {
            return;
        }

        Schema::table('iapp_backfill_runs', function (Blueprint $table) {
            $table->dropColumn('error_count');
        });
    }

};
