<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per employee: whether PF, ESI and Professional Tax apply (on for everyone unless switched off - staff paid by
 * hand, housekeeping), and how the salary is paid (bank transfer or cash). Read by App\Services\Payroll\Statutory.
 * Idempotent.
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::table('employee_details', function (Blueprint $table) {
            foreach (['pf_applicable', 'esi_applicable', 'pt_applicable'] as $column) {
                if (!Schema::hasColumn('employee_details', $column)) {
                    $table->boolean($column)->default(true);
                }
            }
            if (!Schema::hasColumn('employee_details', 'salary_paid_by')) {
                $table->string('salary_paid_by', 10)->default('bank');   // bank | cash
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_details', function (Blueprint $table) {
            foreach (['pf_applicable', 'esi_applicable', 'pt_applicable', 'salary_paid_by'] as $column) {
                if (Schema::hasColumn('employee_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

};
