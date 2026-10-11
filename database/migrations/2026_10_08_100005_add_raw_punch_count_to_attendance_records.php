<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How many punches the device actually reported for a day, beside how many
 * were counted.
 *
 * `punch_count` holds the figure after gate repeats are collapsed, and the
 * exception report depends on that meaning — "single punch" is
 * `punch_count = 1`, which must stay true for a day whose one swipe was
 * reported twice. But the Device Attendance screen labels its column PUNCHES
 * and sits directly under the words "21 punch(es) stored for this range",
 * so showing the collapsed 15 there read as a fault.
 *
 * Two columns, two meanings: `raw_punch_count` is what the device sent,
 * `punch_count` is what the figures are built from.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::table('iapp_attendance_records', function (Blueprint $table) {
            $table->unsignedInteger('raw_punch_count')->nullable()->after('punch_count');
        });

        // Existing rows predate the column. The honest backfill is the real
        // number of punches stored for that employee and day.
        DB::statement("
            UPDATE iapp_attendance_records r
            SET r.raw_punch_count = (
                SELECT COUNT(*) FROM iapp_device_transactions t
                WHERE t.emp_code = r.emp_id
                  AND DATE(t.punch_time) = r.attendance_date
                  AND (r.company_id IS NULL OR t.company_id = r.company_id)
            )
            WHERE r.raw_punch_count IS NULL
        ");

        // A day whose punches are no longer stored keeps the counted figure
        // rather than claiming zero.
        DB::statement('UPDATE iapp_attendance_records SET raw_punch_count = punch_count
                       WHERE raw_punch_count IS NULL OR raw_punch_count < punch_count');
    }

    public function down(): void
    {
        Schema::table('iapp_attendance_records', function (Blueprint $table) {
            $table->dropColumn('raw_punch_count');
        });
    }

};
