<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staging table for iApp attd/getDetail rows.
     *
     * Device data lands here first, exactly as returned, and is only then
     * promoted into `attendances`. Keeping the raw pull separate means a
     * re-sync of a past date (iApp restates attendance after regularisation
     * and leave approval) can be compared against what we already had,
     * instead of silently overwriting it.
     *
     * Rows with no first_in — absent, leave, week-off, holiday — are kept
     * here too. They never become `attendances` rows (clock_in_time is NOT
     * NULL) but payroll LOP and absence reporting need them.
     */
    public function up(): void
    {
        if (Schema::hasTable('iapp_attendance_records')) {
            return;
        }

        Schema::create('iapp_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();

            // As returned by iApp
            $table->string('emp_id', 100);
            $table->string('employee_name')->nullable();
            $table->string('location_code', 10)->nullable();
            $table->date('attendance_date');

            $table->dateTime('first_in')->nullable();
            $table->dateTime('last_out')->nullable();

            // Durations arrive as HH:MM strings. Stored raw for display, plus
            // a minutes column so totals and comparisons are plain integer
            // maths instead of parsing strings in every report query.
            $table->string('work_hour', 10)->nullable();
            $table->unsignedSmallInteger('work_minutes')->nullable();
            $table->string('extra_hours', 10)->nullable();
            $table->string('over_time_hours', 10)->nullable();
            $table->unsignedSmallInteger('over_time_minutes')->nullable();
            $table->string('late_hours', 10)->nullable();
            $table->unsignedSmallInteger('late_minutes')->nullable();
            $table->string('early_hours', 10)->nullable();
            $table->string('less_working_hours', 10)->nullable();
            $table->string('od_hours', 10)->nullable();

            $table->string('shift_code', 50)->nullable();
            $table->string('leave_code', 50)->nullable();
            $table->decimal('leave_qty', 4, 2)->nullable();
            $table->string('week_off', 10)->nullable();
            $table->string('holiday', 10)->nullable();
            $table->decimal('absent', 4, 2)->nullable();
            $table->decimal('present', 4, 2)->nullable();
            $table->decimal('tour', 4, 2)->nullable();
            $table->string('attendance_remark')->nullable();

            // Classification derived once at sync time so listing screens and
            // the promoter never have to re-derive it per row.
            $table->string('day_type', 20)->default('unknown');

            $table->string('promote_status', 20)->default('pending');
            $table->text('promote_message')->nullable();
            $table->unsignedInteger('attendance_id')->nullable();
            $table->timestamp('promoted_at')->nullable();

            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            // Natural key of a getDetail row: one row per employee per day.
            // Drives the bulk upsert, so a re-pull updates in place.
            $table->unique(['company_id', 'emp_id', 'attendance_date'], 'iapp_att_natural_key');

            $table->index(['company_id', 'attendance_date'], 'iapp_att_company_date_idx');
            $table->index(['user_id', 'attendance_date'], 'iapp_att_user_date_idx');
            $table->index(['company_id', 'promote_status'], 'iapp_att_promote_idx');
            $table->index('day_type', 'iapp_att_day_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iapp_attendance_records');
    }
};
