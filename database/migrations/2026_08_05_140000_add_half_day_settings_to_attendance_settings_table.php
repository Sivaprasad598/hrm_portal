<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendance Settings → Half Day Settings tab: after how many late
     * comings a deduction kicks in, and whether that deduction is a half
     * day or a full day.
     */
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->unsignedInteger('half_day_late_threshold')->default(3)->after('late_mark_duration');
            $table->decimal('half_day_deduction_type', 3, 1)->default(0.5)->after('half_day_late_threshold');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn(['half_day_late_threshold', 'half_day_deduction_type']);
        });
    }
};
