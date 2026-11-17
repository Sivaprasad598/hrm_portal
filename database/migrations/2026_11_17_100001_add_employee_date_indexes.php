<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance and the shift roster are read "for this employee, between these
 * dates" on almost every attendance, payroll and biometric screen. Each table had
 * an index on the employee and another on the date, so the database could use
 * only one of them and sift through the rest - fine with a few thousand rows,
 * slower with every month of punches. One index on both columns answers those
 * reads directly however large the tables grow.
 *
 * Safe to run more than once: an index that is already there is left alone.
 */
return new class extends Migration {

    private const INDEXES = [
        'attendances' => ['attendances_user_clock_in_index', ['user_id', 'clock_in_time']],
        'employee_shift_schedules' => ['shift_schedules_user_date_index', ['user_id', 'date']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => [$name, $columns]) {
            if (!Schema::hasTable($table) || $this->hasIndex($table, $name)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    continue 2;
                }
            }

            Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => [$name]) {
            if (Schema::hasTable($table) && $this->hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
            }
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(DB::select('show index from `' . $table . '`'))->contains(fn ($i) => $i->Key_name === $name);
    }

};
