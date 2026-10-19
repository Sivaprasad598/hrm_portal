<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The calculation sheet (Calculation Formulas tab), as it is:
 *
 *   Basic                 = Gross x 40%
 *   HRA                   = Basic x 40%
 *   Conveyance Allowance  = IF(Basic > 20000, 1600, 800)
 *   Leave Travel Allowance = ROUNDUP(Basic x 10%)
 *   Children Education Allowance = IF(Basic < 15000, 1200, 2400)
 *   Special Allowance     = Gross - (Basic + HRA + CA + LTA + CEA)
 *   PF                    = IF(Basic < 25000, Basic x 24%, 25000 x 24%)
 *   ESI                   = IF(Gross <= 21000, Gross x 4%, 0)
 *   PT                    = IF(Gross > 20000, 200, IF(Gross > 15000, 150, 0))
 *
 * LTA and CEA are new components, each a setting; the slip's "others" column
 * is now the Special Allowance. CA can be judged on Basic (the sheet) or Gross.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_calculations', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_calculations', 'ca_basis')) {
                $table->string('ca_basis', 10)->default('basic')->after('ca_low');
                $table->decimal('lta_pct', 6, 2)->default(10)->after('ca_basis');
                $table->decimal('cea_threshold', 12, 2)->default(15000)->after('lta_pct');
                $table->decimal('cea_low', 12, 2)->default(1200)->after('cea_threshold');
                $table->decimal('cea_high', 12, 2)->default(2400)->after('cea_low');
                $table->decimal('lta', 12, 2)->nullable()->after('ca');
                $table->decimal('cea', 12, 2)->nullable()->after('lta');
            }
        });

        Schema::table('payslips', function (Blueprint $table) {
            if (!Schema::hasColumn('payslips', 'lta')) {
                $table->decimal('lta', 12, 2)->default(0)->after('ca');
                $table->decimal('cea', 12, 2)->default(0)->after('lta');
            }
        });

        // The settings in force follow the sheet.
        $latest = DB::table('payroll_calculations')->orderByDesc('id')->value('id');

        if ($latest) {
            DB::table('payroll_calculations')->where('id', $latest)->update([
                'basic_pct' => 40, 'hra_pct' => 40,
                'ca_basis' => 'basic', 'ca_threshold' => 20000, 'ca_high' => 1600, 'ca_low' => 800,
                'lta_pct' => 10, 'cea_threshold' => 15000, 'cea_low' => 1200, 'cea_high' => 2400,
                'esi_threshold' => 21000, 'esi_pct' => 0.75, 'esi_employer_pct' => 3.25,
                'pf_pct' => 12, 'pf_employer_pct' => 12, 'pf_wage_ceiling' => 25000,
                'pt_slabs' => json_encode([['upto' => 15000, 'amount' => 0], ['upto' => 20000, 'amount' => 150], ['upto' => null, 'amount' => 200]]),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            foreach (['lta', 'cea'] as $c) {
                if (Schema::hasColumn('payslips', $c)) {
                    $table->dropColumn($c);
                }
            }
        });

        Schema::table('payroll_calculations', function (Blueprint $table) {
            foreach (['ca_basis', 'lta_pct', 'cea_threshold', 'cea_low', 'cea_high', 'lta', 'cea'] as $c) {
                if (Schema::hasColumn('payroll_calculations', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
