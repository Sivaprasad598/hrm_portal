<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carries the employee name reported by the devices.
     *
     * The punch feed does expose names, but only under the exact column name
     * 'Name' — 'name', 'empName' and 'EmployeeName' are all rejected. With the
     * name available, a biometric code can be matched to an ERP employee by
     * person rather than by identifier, which is far more reliable here: the
     * two systems' code formats do not overlap.
     */
    public function up(): void
    {
        if (Schema::hasTable('iapp_device_transactions')
            && !Schema::hasColumn('iapp_device_transactions', 'employee_name')) {
            Schema::table('iapp_device_transactions', function (Blueprint $table) {
                $table->string('employee_name')->nullable()->after('emp_code');
            });
        }

        if (Schema::hasTable('iapp_employees')) {
            Schema::table('iapp_employees', function (Blueprint $table) {
                if (!Schema::hasColumn('iapp_employees', 'location_name')) {
                    $table->string('location_name')->nullable()->after('location_code');
                }

                if (!Schema::hasColumn('iapp_employees', 'company_name')) {
                    $table->string('company_name')->nullable()->after('location_name');
                }
            });

            // Names are matched by normalised comparison in PHP rather than by
            // SQL, but an index keeps the review screen's search responsive.
            Schema::table('iapp_employees', function (Blueprint $table) {
                $table->index('employee_name', 'iapp_employee_name_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('iapp_device_transactions')) {
            Schema::table('iapp_device_transactions', function (Blueprint $table) {
                $table->dropColumn('employee_name');
            });
        }

        if (Schema::hasTable('iapp_employees')) {
            Schema::table('iapp_employees', function (Blueprint $table) {
                $table->dropIndex('iapp_employee_name_idx');
                $table->dropColumn(['location_name', 'company_name']);
            });
        }
    }
};
