<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Statutory settings the payroll calculator was missing.
 *
 * Provident fund was taken on the whole of basic with no wage ceiling, the
 * employer's ESI was assumed to be the same as the employee's, and professional
 * tax was one flat figure for every salary. All three are now settings, with
 * the usual Indian values as defaults, and the employer's contributions are
 * stored on the payslip instead of being inferred from the employee's.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::table('payroll_calculations', function (Blueprint $table) {
            // Basic above this is ignored for PF. 0 means no ceiling.
            $table->decimal('pf_wage_ceiling', 12, 2)->default(15000)->after('pf_pct');
            // The employer's share of ESI, which is larger than the employee's.
            $table->decimal('esi_employer_pct', 5, 2)->default(3.25)->after('pf_wage_ceiling');
            // Professional tax by salary band: [{"upto": 15000, "amount": 0}, ...]
            // with the last band's "upto" left null, meaning "and above".
            $table->json('pt_slabs')->nullable()->after('esi_employer_pct');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->decimal('employer_pf', 12, 2)->nullable()->after('pf');
            $table->decimal('employer_esi', 12, 2)->nullable()->after('employer_pf');
            $table->decimal('pf_wage_ceiling', 12, 2)->nullable()->after('pf_pct');
            $table->decimal('esi_employer_pct', 5, 2)->nullable()->after('pf_wage_ceiling');
        });

        // Andhra Pradesh bands, where this company is: nothing up to 15,000,
        // 150 to 20,000, 200 above that. Editable on the calculator.
        $slabs = json_encode([
            ['upto' => 15000, 'amount' => 0],
            ['upto' => 20000, 'amount' => 150],
            ['upto' => null, 'amount' => 200],
        ]);

        DB::table('payroll_calculations')->update(['pt_slabs' => $slabs]);

        // Payslips already issued keep what they were paid on: the employer's
        // share matched the employee's, and PF had no ceiling.
        DB::statement('UPDATE payslips SET employer_pf = pf, employer_esi = esi, pf_wage_ceiling = 0 WHERE employer_pf IS NULL');
    }

    public function down(): void
    {
        Schema::table('payroll_calculations', function (Blueprint $table) {
            $table->dropColumn(['pf_wage_ceiling', 'esi_employer_pct', 'pt_slabs']);
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['employer_pf', 'employer_esi', 'pf_wage_ceiling', 'esi_employer_pct']);
        });
    }

};
