<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The payroll calculation sheet's rules, as settings.
 *
 *  - pf_employer_pct: the employer's PF %, set apart from the employee's
 *    (it was assumed equal). Empty means "same as the employee's".
 *  - prorate_statutory: PF and ESI are charged on the days paid
 *    (PF x present / days), as the sheet's "PF after LOP" and "ESI after LOP".
 *  - round_net: the final net salary is rounded to the rupee, and PF / ESI
 *    after LOP to the rupee, as the sheet does.
 *
 * And the sheet's rates on the settings in use: Basic 40% of gross, and PF on
 * Basic up to 25,000.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('payroll_calculations', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_calculations', 'pf_employer_pct')) {
                $table->decimal('pf_employer_pct', 5, 2)->nullable()->after('pf_pct');
            }

            if (!Schema::hasColumn('payroll_calculations', 'prorate_statutory')) {
                $table->boolean('prorate_statutory')->default(true)->after('pf_wage_ceiling');
            }

            if (!Schema::hasColumn('payroll_calculations', 'round_net')) {
                $table->boolean('round_net')->default(true)->after('prorate_statutory');
            }
        });

        Schema::table('payslips', function (Blueprint $table) {
            // What the slip was worked out under, kept with it like the other rates
            if (!Schema::hasColumn('payslips', 'pf_employer_pct')) {
                $table->decimal('pf_employer_pct', 5, 2)->nullable()->after('pf_pct');
            }

            if (!Schema::hasColumn('payslips', 'prorate_statutory')) {
                $table->boolean('prorate_statutory')->nullable()->after('pf_wage_ceiling');
            }
        });

        $latest = DB::table('payroll_calculations')->orderByDesc('id')->value('id');

        if ($latest) {
            DB::table('payroll_calculations')->where('id', $latest)->update([
                'basic_pct' => 40, 'pf_wage_ceiling' => 25000, 'pf_employer_pct' => 12,
                'prorate_statutory' => true, 'round_net' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('payroll_calculations', function (Blueprint $table) {
            $table->dropColumn(['pf_employer_pct', 'prorate_statutory', 'round_net']);
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['pf_employer_pct', 'prorate_statutory']);
        });
    }
};
