<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns for the iApp getDetail fields that had nowhere to go before
     * (shift/leave/weekoff/holiday/hours breakdown) so the iApp attendance
     * sync stops dropping them on the floor. All nullable — existing rows
     * (non-iApp attendance) are unaffected.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('shift_code')->nullable()->after('employee_shift_id');
            $table->string('leave_code')->nullable()->after('shift_code');
            $table->decimal('leave_qty', 4, 2)->nullable()->after('leave_code');
            $table->string('week_off')->nullable()->after('leave_qty');
            $table->string('holiday')->nullable()->after('week_off');
            $table->string('work_hour')->nullable()->after('holiday');
            $table->string('extra_hours')->nullable()->after('work_hour');
            $table->string('over_time_hours')->nullable()->after('extra_hours');
            $table->string('late_hours')->nullable()->after('over_time_hours');
            $table->string('early_hours')->nullable()->after('late_hours');
            $table->string('less_working_hours')->nullable()->after('early_hours');
            $table->string('attendance_remark')->nullable()->after('less_working_hours');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'shift_code', 'leave_code', 'leave_qty', 'week_off', 'holiday',
                'work_hour', 'extra_hours', 'over_time_hours', 'late_hours',
                'early_hours', 'less_working_hours', 'attendance_remark',
            ]);
        });
    }
};
