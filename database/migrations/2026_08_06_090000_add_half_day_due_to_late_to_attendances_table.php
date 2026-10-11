<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks an attendance record's Half Day as having been applied by the
     * "N late comings = 1 half day" policy (Attendance Settings → Half Day
     * Settings), as opposed to a half day marked for any other reason.
     * Needed so the pending-deduction count doesn't re-count a late day
     * that's already had its deduction applied.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('half_day_due_to_late')->default(false)->after('half_day_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('half_day_due_to_late');
        });
    }
};
