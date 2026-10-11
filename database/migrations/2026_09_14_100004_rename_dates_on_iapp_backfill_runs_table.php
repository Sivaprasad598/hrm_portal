<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rename `dates` to `sync_dates`.
 *
 * `$dates` is a reserved property on Eloquent models — the old date-mutator
 * list — and it is declared on this project's model base. A column of that name
 * is written and read correctly from outside the model, but `$this->dates`
 * inside it resolves to that empty property instead of the attribute, so the
 * run looked like it covered no days at all and finished instantly.
 */
return new class extends Migration
{

    public function up(): void
    {
        if (!Schema::hasTable('iapp_backfill_runs')) {
            return;
        }

        if (Schema::hasColumn('iapp_backfill_runs', 'sync_dates')) {
            return;
        }

        Schema::table('iapp_backfill_runs', function (Blueprint $table) {
            $table->json('sync_dates')->nullable()->after('to_date');
        });

        if (Schema::hasColumn('iapp_backfill_runs', 'dates')) {
            DB::statement('UPDATE iapp_backfill_runs SET sync_dates = dates');

            Schema::table('iapp_backfill_runs', function (Blueprint $table) {
                $table->dropColumn('dates');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('iapp_backfill_runs') || !Schema::hasColumn('iapp_backfill_runs', 'sync_dates')) {
            return;
        }

        if (!Schema::hasColumn('iapp_backfill_runs', 'dates')) {
            Schema::table('iapp_backfill_runs', function (Blueprint $table) {
                $table->json('dates')->nullable()->after('to_date');
            });

            DB::statement('UPDATE iapp_backfill_runs SET dates = sync_dates');
        }

        Schema::table('iapp_backfill_runs', function (Blueprint $table) {
            $table->dropColumn('sync_dates');
        });
    }

};
