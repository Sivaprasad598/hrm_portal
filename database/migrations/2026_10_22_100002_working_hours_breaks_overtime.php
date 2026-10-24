<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Working hours, breaks, lates and overtime (Attendance Settings ->
 * Working Hours & OT). See App\Services\Attendance\WorkRules.
 *
 *   attendance_settings  wh_*  how a day is counted: work hours + lunch and
 *                               snacks breaks, counting anywhere between the
 *                               punches or only inside the shift, grace, half
 *                               day, what "late" is measured from, and the date
 *                               the rules apply from (the salary period running
 *                               when they were introduced, so issued payslips
 *                               never change)
 *                        ot_*  overtime: on/off, paid on gross or basic,
 *                               weekday / weekend / holiday rates, minimum and
 *                               daily caps
 *   employee_shifts      break_minutes   a shift's own break (empty = the setting)
 *   teams                consider_attendance_hours   0 = any punch is a full day
 *   employee_details     attendance_exempt   full salary whatever the punches
 */
return new class extends Migration {

    public function up(): void
    {
        // The salary period running today (21st to 20th).
        $today = now('Asia/Kolkata');
        $effective = ($today->day >= 21 ? $today->copy() : $today->copy()->subMonthNoOverflow())->day(21)->toDateString();

        Schema::table('attendance_settings', function (Blueprint $table) use ($effective) {
            if (!Schema::hasColumn('attendance_settings', 'wh_count_mode')) {
                $table->string('wh_count_mode', 20)->default('anywhere');
                $table->decimal('wh_work_hours', 4, 2)->default(8);
                $table->unsignedSmallInteger('wh_lunch_minutes')->default(45);
                $table->unsignedSmallInteger('wh_snack_minutes')->default(15);
                $table->unsignedSmallInteger('wh_grace_minutes')->default(10);
                $table->decimal('wh_half_day_hours', 4, 2)->default(4);
                $table->string('wh_late_basis', 10)->default('shift');
                $table->time('wh_late_time')->nullable();
                $table->date('wh_effective_from')->nullable();

                $table->boolean('ot_enabled')->default(false);
                $table->string('ot_basis', 10)->default('gross');
                $table->decimal('ot_rate_weekday', 4, 2)->default(1);
                $table->decimal('ot_rate_weekend', 4, 2)->default(1.5);
                $table->decimal('ot_rate_holiday', 4, 2)->default(2);
                $table->unsignedSmallInteger('ot_min_minutes')->default(30);
                $table->decimal('ot_cap_weekday_hours', 4, 2)->default(4);
                $table->decimal('ot_cap_off_hours', 4, 2)->default(9);
            }
        });

        DB::table('attendance_settings')->whereNull('wh_effective_from')->update(['wh_effective_from' => $effective]);

        if (!Schema::hasColumn('employee_shifts', 'break_minutes')) {
            Schema::table('employee_shifts', function (Blueprint $table) {
                $table->unsignedSmallInteger('break_minutes')->nullable();
            });
        }

        if (!Schema::hasColumn('teams', 'consider_attendance_hours')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->boolean('consider_attendance_hours')->default(true);
            });
        }

        if (!Schema::hasColumn('employee_details', 'attendance_exempt')) {
            Schema::table('employee_details', function (Blueprint $table) {
                $table->boolean('attendance_exempt')->default(false);
            });
        }
    }

    public function down(): void
    {
        $settings = ['wh_count_mode', 'wh_work_hours', 'wh_lunch_minutes', 'wh_snack_minutes', 'wh_grace_minutes', 'wh_half_day_hours',
            'wh_late_basis', 'wh_late_time', 'wh_effective_from', 'ot_enabled', 'ot_basis', 'ot_rate_weekday', 'ot_rate_weekend',
            'ot_rate_holiday', 'ot_min_minutes', 'ot_cap_weekday_hours', 'ot_cap_off_hours'];

        Schema::table('attendance_settings', function (Blueprint $table) use ($settings) {
            $table->dropColumn(array_values(array_filter($settings, fn ($c) => Schema::hasColumn('attendance_settings', $c))));
        });

        foreach (['employee_shifts' => 'break_minutes', 'teams' => 'consider_attendance_hours', 'employee_details' => 'attendance_exempt'] as $table => $column) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }

};
