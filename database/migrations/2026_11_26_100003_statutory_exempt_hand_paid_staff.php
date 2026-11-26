<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR (26 Nov 2026): staff paid by hand - no PF, no ESI, no PT, salary in cash, and not on biometric
 * (full salary every month; leave still counts). By employee code; anyone not on record is skipped.
 * Idempotent (setting the same values again changes nothing).
 */
return new class extends Migration {

    private const CODES = ['ACPL0025', 'ACPL0173', 'ACPL0106', 'ACPL0026', 'ACPL0231', 'ACPL0222', 'ACPL0076',
        'ACPL0507', 'ACPL0437', 'ACPL0620', 'ACPL0621', 'ACPL0651', 'ACPL0645'];

    public function up(): void
    {
        if (!Schema::hasColumn('employee_details', 'pf_applicable')) {
            return;
        }

        $values = ['pf_applicable' => 0, 'esi_applicable' => 0, 'pt_applicable' => 0, 'salary_paid_by' => 'cash'];

        if (Schema::hasColumn('employee_details', 'attendance_exempt')) {
            $values['attendance_exempt'] = 1;
        }

        DB::table('employee_details')->whereIn('employee_id', self::CODES)->update($values);

        // As the OCT-2026 calculation sheet deducts them: PT for these, and ESI too for ACPL0076.
        DB::table('employee_details')->whereIn('employee_id', self::PT_APPLIES)->update(['pt_applicable' => 1]);
        DB::table('employee_details')->whereIn('employee_id', self::ESI_APPLIES)->update(['esi_applicable' => 1]);
    }

    private const PT_APPLIES = ['ACPL0173', 'ACPL0231', 'ACPL0025', 'ACPL0026', 'ACPL0076'];

    private const ESI_APPLIES = ['ACPL0076'];

    public function down(): void
    {
        // Left as set: switching them back is done on each employee's record.
    }

};
