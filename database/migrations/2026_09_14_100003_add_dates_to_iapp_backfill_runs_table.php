<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a run cover an explicit list of dates rather than only a solid range.
 *
 * HR picks days out of a calendar — Tuesday and Friday, say — and those are not
 * a range. Storing the dates explicitly makes one day, several scattered days, a
 * whole month and a long backfill the same kind of run, walked the same way.
 *
 * `date_index` is which of those dates the run is on; `cursor_at` is how far
 * into that one day it has reached. A day is only marked complete once its last
 * chunk lands, so a half-pulled day is never mistaken for a finished one.
 */
return new class extends Migration
{

    public function up(): void
    {
        if (!Schema::hasTable('iapp_backfill_runs')) {
            return;
        }

        Schema::table('iapp_backfill_runs', function (Blueprint $table) {
            if (!Schema::hasColumn('iapp_backfill_runs', 'dates')) {
                $table->json('dates')->nullable()->after('to_date');
            }

            if (!Schema::hasColumn('iapp_backfill_runs', 'date_index')) {
                $table->unsignedInteger('date_index')->default(0)->after('dates');
            }

            if (!Schema::hasColumn('iapp_backfill_runs', 'scope')) {
                // 'day', 'days', 'month' or 'range' — only to describe the run
                // in the UI; the walk itself treats them all alike.
                $table->string('scope', 20)->default('range')->after('date_index');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('iapp_backfill_runs')) {
            return;
        }

        Schema::table('iapp_backfill_runs', function (Blueprint $table) {
            foreach (['dates', 'date_index', 'scope'] as $column) {
                if (Schema::hasColumn('iapp_backfill_runs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

};
