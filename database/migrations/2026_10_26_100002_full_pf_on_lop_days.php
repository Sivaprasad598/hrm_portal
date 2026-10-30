<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PF and ESI are charged for the whole month, LOP days included, as on the
 * company's payslips (Payroll Settings -> "charge PF and ESI on the days paid"
 * turned off). Payslips already issued keep the setting they were made with.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payroll_calculations', 'prorate_statutory')) {
            DB::table('payroll_calculations')->update(['prorate_statutory' => 0]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payroll_calculations', 'prorate_statutory')) {
            DB::table('payroll_calculations')->update(['prorate_statutory' => 1]);
        }
    }
};
