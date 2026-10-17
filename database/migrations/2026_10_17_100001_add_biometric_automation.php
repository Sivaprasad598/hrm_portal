<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Biometric → payslip automation:
     * - iapp_sync_logs.emp_code: a pull made for one employee only
     * - iapp_settings.auto_generate_payslips: draft payslips once a pay cycle ends
     * - auto_promote (push to ERP attendance after every pull) and the new switch
     *   are turned on, since the point of the change is that nobody has to remember
     *   the manual steps any more. Both can be switched off on Biometric Settings.
     */
    public function up(): void
    {
        Schema::table('iapp_sync_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('iapp_sync_logs', 'emp_code')) {
                $table->string('emp_code', 50)->nullable()->after('trigger');
            }
        });

        Schema::table('iapp_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('iapp_settings', 'auto_generate_payslips')) {
                $table->boolean('auto_generate_payslips')->default(true)->after('auto_promote');
            }
        });

        DB::table('iapp_settings')->update(['auto_promote' => 1, 'auto_generate_payslips' => 1]);
    }

    public function down(): void
    {
        Schema::table('iapp_settings', function (Blueprint $table) {
            $table->dropColumn('auto_generate_payslips');
        });

        Schema::table('iapp_sync_logs', function (Blueprint $table) {
            $table->dropColumn('emp_code');
        });

        DB::table('iapp_settings')->update(['auto_promote' => 0]);
    }

};
