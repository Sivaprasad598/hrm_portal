<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes the attendance screens were missing (found with EXPLAIN):
 *
 *  - iapp_device_transactions (punch_time): Punch Log by date read every punch and sorted them
 *    (its query has no company or person to lead the existing indexes).
 *  - attendance_reviews (user_id, date): one employee's days in a period (their month, Employee
 *    360) used the single user_id index and filtered every row of theirs.
 *  - iapp_attendance_links (user_id, attendance_date): the writer looks up each employee-day.
 *  - the same on the archive tables, which are created LIKE the live ones but may predate this.
 *
 * Idempotent: an index already there is left alone.
 */
return new class extends Migration {

    protected const INDEXES = [
        ['iapp_device_transactions', 'iapp_trans_punch_time_idx', ['punch_time']],
        ['iapp_device_transactions_archive', 'iapp_trans_punch_time_idx', ['punch_time']],
        ['attendance_reviews', 'attendance_reviews_user_date_idx', ['user_id', 'date']],
        // the attendance writer's "is this day unchanged" check, per employee-day
        ['iapp_attendance_links', 'iapp_att_links_user_date_idx', ['user_id', 'attendance_date']],
    ];

    protected function has(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)->where('index_name', $index)->exists();
    }

    public function up(): void
    {
        foreach (self::INDEXES as [$table, $index, $columns]) {
            if (Schema::hasTable($table) && !$this->has($table, $index)) {
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $index));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as [$table, $index]) {
            if (Schema::hasTable($table) && $this->has($table, $index)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($index));
            }
        }
    }

};
