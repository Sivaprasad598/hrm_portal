<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employee_details', 'employment_status')) {
            Schema::table('employee_details', function (Blueprint $table) {
                $table->string('employment_status', 20)->nullable()->default('confirmed')->after('employment_type');
            });

            // Set probation status for employees with a future probation_end_date
            DB::table('employee_details')
                ->whereNotNull('probation_end_date')
                ->where('probation_end_date', '>=', now()->toDateString())
                ->update(['employment_status' => 'probation']);

            // Set notice_period for employees with notice period start date
            DB::table('employee_details')
                ->whereNotNull('notice_period_start_date')
                ->update(['employment_status' => 'notice_period']);

            // Set off_boarded for employees with a last_date in the past
            DB::table('employee_details')
                ->whereNotNull('last_date')
                ->where('last_date', '<', now()->toDateString())
                ->where('employment_status', 'confirmed')
                ->update(['employment_status' => 'off_boarded']);
        }
    }

    public function down(): void
    {
        Schema::table('employee_details', function (Blueprint $table) {
            $table->dropColumn('employment_status');
        });
    }
};
