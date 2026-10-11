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
        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->decimal('min_working_hours_full_day', 4, 2)->nullable()->after('clockin_in_day');
            $table->unsignedInteger('min_working_hours_full_day_grace')->nullable()->after('min_working_hours_full_day');
            $table->decimal('min_working_hours_half_day', 4, 2)->nullable()->after('min_working_hours_full_day_grace');
            $table->unsignedInteger('min_working_hours_half_day_grace')->nullable()->after('min_working_hours_half_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->dropColumn([
                'min_working_hours_full_day',
                'min_working_hours_full_day_grace',
                'min_working_hours_half_day',
                'min_working_hours_half_day_grace',
            ]);
        });
    }
};
