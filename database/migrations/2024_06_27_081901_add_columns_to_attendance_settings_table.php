<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */

    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->enum('adjust_attendance_logs', ['not_allowed', 'missing_swipes', 'all_logs'])->default('not_allowed');
            $table->boolean('adjustment_allowed')->default(false);
            $table->enum('times_adjustment_allowed', ['1', '2', '3'])->nullable()->default('1');
            $table->integer('last_days')->nullable()->default('5');
            $table->integer('adjustment_total_times')->nullable()->default('1');
            $table->enum('adjustment_type', ['current_week', 'current_month', 'current_quarter', 'current_year'])->default('current_month');
            $table->integer('before_day_of_month')->nullable()->default('3');
            $table->text('attendance_regularize_roles')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn('adjust_attendance_logs');
            $table->dropColumn('adjustment_allowed');
            $table->dropColumn('times_adjustment_allowed');
            $table->dropColumn('last_days')->nullable();
            $table->dropColumn('adjustment_total_times');
            $table->dropColumn('adjustment_type');
            $table->dropColumn('before_day_of_month');
            $table->dropColumn('attendance_regularize_roles');
        });
    }

};
