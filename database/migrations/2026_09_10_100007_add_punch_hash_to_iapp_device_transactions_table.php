<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the punch natural key with a single non-null hash column.
     *
     * The previous unique index was (controller_code, card_id, punch_time).
     * Live data showed card_id is empty for most punches, and MySQL treats
     * every NULL as distinct in a unique index — so re-running a sync inserted
     * the same punches again instead of updating them (1,134 rows became
     * 2,142 on the second run).
     *
     * punch_hash is derived from emp_code + controller_code + punch_time with
     * empty strings standing in for missing values, so it is always non-null
     * and the uniqueness actually holds.
     */
    public function up(): void
    {
        if (!Schema::hasTable('iapp_device_transactions')) {
            return;
        }

        if (!Schema::hasColumn('iapp_device_transactions', 'punch_hash')) {
            Schema::table('iapp_device_transactions', function (Blueprint $table) {
                $table->char('punch_hash', 32)->nullable()->after('id');
            });
        }

        // Backfill before the column is made unique.
        DB::statement("
            UPDATE iapp_device_transactions
            SET punch_hash = MD5(CONCAT_WS('|',
                COALESCE(emp_code, ''),
                COALESCE(controller_code, ''),
                COALESCE(DATE_FORMAT(punch_time, '%Y-%m-%d %H:%i:%s'), '')
            ))
            WHERE punch_hash IS NULL
        ");

        // Collapse any duplicates the old index allowed through, keeping the
        // earliest row of each set so foreign references stay valid.
        DB::statement('
            DELETE t FROM iapp_device_transactions t
            INNER JOIN (
                SELECT punch_hash, MIN(id) AS keep_id
                FROM iapp_device_transactions
                GROUP BY punch_hash
                HAVING COUNT(*) > 1
            ) d ON t.punch_hash = d.punch_hash AND t.id > d.keep_id
        ');

        Schema::table('iapp_device_transactions', function (Blueprint $table) {
            $table->char('punch_hash', 32)->nullable(false)->change();
        });

        Schema::table('iapp_device_transactions', function (Blueprint $table) {
            $table->dropUnique('iapp_device_transactions_natural_key');
            $table->unique('punch_hash', 'iapp_device_transactions_natural_key');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('iapp_device_transactions')) {
            return;
        }

        Schema::table('iapp_device_transactions', function (Blueprint $table) {
            $table->dropUnique('iapp_device_transactions_natural_key');
            $table->unique(['controller_code', 'card_id', 'punch_time'], 'iapp_device_transactions_natural_key');
            $table->dropColumn('punch_hash');
        });
    }
};
