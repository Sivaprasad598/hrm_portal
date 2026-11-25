<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Archive tables for attendance kept longer than a year (attendance:archive moves rows there
 * monthly): punches (iapp_device_transactions_archive) and attendance sessions
 * (attendances_archive). The same columns and indexes as the live tables, so the archive stays
 * searchable - Employee 360 and Punch Log read it for archived dates. The daily summary
 * (attendance_reviews) is never archived. Idempotent.
 */
return new class extends Migration {

    protected const TABLES = ['iapp_device_transactions', 'attendances'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && !Schema::hasTable($table . '_archive')) {
                DB::statement('CREATE TABLE `' . $table . '_archive` LIKE `' . $table . '`');
            }
        }
    }

    public function down(): void
    {
        // Archived rows go back where they came from before the tables are dropped.
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table . '_archive')) {
                DB::statement('INSERT IGNORE INTO `' . $table . '` SELECT * FROM `' . $table . '_archive`');
                Schema::drop($table . '_archive');
            }
        }
    }

};
