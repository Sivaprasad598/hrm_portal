<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give a payslip an identity of its own.
 *
 * Until now a payslip knew only the employee's card id as text, so it detached
 * from the employee if that id was ever changed, belonged to no company, was
 * referred to by database id alone, and did not record who issued it.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            // Printed on the slip and quoted in correspondence: AROW/2026-27/000123.
            $table->string('payslip_number', 40)->nullable()->after('id');
            $table->unsignedBigInteger('user_id')->nullable()->after('emp_id');
            $table->unsignedBigInteger('company_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('generated_by')->nullable()->after('created_at');
            $table->timestamp('generated_at')->nullable()->after('generated_by');

            $table->unique('payslip_number');
            $table->index(['company_id', 'period']);
            $table->index('user_id');
        });

        // Attach the payslips already on file to their employee and company.
        DB::statement("
            UPDATE payslips p
              JOIN employee_details ed ON CAST(ed.employee_card_id AS CHAR) = CAST(p.emp_id AS CHAR)
               SET p.user_id = ed.user_id,
                   p.company_id = ed.company_id
             WHERE p.user_id IS NULL
        ");

        // Existing slips keep their issue date; the number is filled in oldest
        // first so the sequence follows the order they were actually created.
        DB::statement('UPDATE payslips SET generated_at = created_at WHERE generated_at IS NULL');

        $sequence = [];

        foreach (DB::table('payslips')->orderBy('id')->get(['id', 'period', 'created_at']) as $slip) {
            $year = static::financialYearLabel($slip->period, $slip->created_at);
            $sequence[$year] = ($sequence[$year] ?? 0) + 1;

            DB::table('payslips')->where('id', $slip->id)->update([
                'payslip_number' => sprintf('AROW/%s/%06d', $year, $sequence[$year]),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropUnique(['payslip_number']);
            $table->dropIndex(['company_id', 'period']);
            $table->dropIndex(['user_id']);
            $table->dropColumn(['payslip_number', 'user_id', 'company_id', 'generated_by', 'generated_at']);
        });
    }

    /**
     * The financial year a period falls in, as "2026-27". April to March,
     * matching the leave year; the created date is the fallback for a period
     * that cannot be read.
     */
    public static function financialYearLabel(?string $period, $createdAt): string
    {
        $date = $period ? strtotime('first day of ' . str_replace(' - ', ' ', $period)) : false;
        $date = $date ?: strtotime((string) $createdAt) ?: time();

        $year = (int) date('Y', $date);
        $start = (int) date('n', $date) >= 4 ? $year : $year - 1;

        return $start . '-' . substr((string) ($start + 1), -2);
    }

};
