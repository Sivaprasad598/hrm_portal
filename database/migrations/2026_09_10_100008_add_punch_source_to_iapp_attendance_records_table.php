<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets a staged attendance day be built from raw punches as well as from
     * iApp's day-wise summary.
     *
     * attd/getDetail is not available on this deployment, so attendance is
     * derived by pairing the punch feed. These columns record where a row came
     * from and which controller produced it, so the Device Attendance screen
     * can be filtered by device and a punch-derived row is never mistaken for
     * a vendor-supplied one.
     */
    public function up(): void
    {
        if (!Schema::hasTable('iapp_attendance_records')) {
            return;
        }

        Schema::table('iapp_attendance_records', function (Blueprint $table) {
            if (!Schema::hasColumn('iapp_attendance_records', 'source')) {
                $table->string('source', 20)->default('device_summary')->after('day_type');
            }

            // Controller of the first punch of the day — the arrival reader,
            // which is the one worth filtering and reporting on.
            if (!Schema::hasColumn('iapp_attendance_records', 'controller_code')) {
                $table->string('controller_code', 50)->nullable()->after('source');
            }

            // Every controller the employee touched that day.
            if (!Schema::hasColumn('iapp_attendance_records', 'controller_codes')) {
                $table->json('controller_codes')->nullable()->after('controller_code');
            }

            if (!Schema::hasColumn('iapp_attendance_records', 'punch_count')) {
                $table->unsignedSmallInteger('punch_count')->nullable()->after('controller_codes');
            }

            // Paired in/out spans, so a lunch break is visible rather than
            // being flattened into one first-in/last-out block.
            if (!Schema::hasColumn('iapp_attendance_records', 'sessions')) {
                $table->json('sessions')->nullable()->after('punch_count');
            }
        });

        Schema::table('iapp_attendance_records', function (Blueprint $table) {
            $table->index(['company_id', 'controller_code'], 'iapp_att_controller_idx');
            $table->index('source', 'iapp_att_source_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('iapp_attendance_records')) {
            return;
        }

        Schema::table('iapp_attendance_records', function (Blueprint $table) {
            $table->dropIndex('iapp_att_controller_idx');
            $table->dropIndex('iapp_att_source_idx');
            $table->dropColumn(['source', 'controller_code', 'controller_codes', 'punch_count', 'sessions']);
        });
    }
};
