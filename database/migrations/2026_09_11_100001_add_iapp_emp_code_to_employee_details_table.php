<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores the biometric enrolment code on the ERP employee record.
     *
     * Employees are enrolled on the biometric devices before they are created
     * in the ERP, so the device code is the employee's original identity and
     * belongs on the employee itself rather than only in a side table.
     *
     * Purely additive: the column is nullable, nothing existing reads or writes
     * it, and no existing behaviour changes. The unique index is scoped to the
     * company and ignores NULLs, so unmapped employees are unaffected while a
     * single device code can never be attached to two people.
     *
     * This is the ERP side only. Nothing is ever written back to the biometric
     * platform — see IappClient, which refuses every write-shaped path.
     */
    public function up(): void
    {
        if (!Schema::hasTable('employee_details')) {
            return;
        }

        if (!Schema::hasColumn('employee_details', 'iapp_emp_code')) {
            Schema::table('employee_details', function (Blueprint $table) {
                // Long enough for the codes actually in use (ACPL_DRONE001,
                // ACPL_HYD-01, ARH-001) with plenty of headroom.
                $table->string('iapp_emp_code', 100)->nullable()->after('employee_card_id');
            });
        }

        if (!Schema::hasColumn('employee_details', 'iapp_linked_at')) {
            Schema::table('employee_details', function (Blueprint $table) {
                $table->timestamp('iapp_linked_at')->nullable()->after('iapp_emp_code');
            });
        }

        Schema::table('employee_details', function (Blueprint $table) {
            $table->unique(['company_id', 'iapp_emp_code'], 'employee_details_iapp_emp_code_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_details')) {
            return;
        }

        Schema::table('employee_details', function (Blueprint $table) {
            $table->dropUnique('employee_details_iapp_emp_code_unique');
            $table->dropColumn(['iapp_emp_code', 'iapp_linked_at']);
        });
    }
};
