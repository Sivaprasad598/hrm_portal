<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Real dates for a pay period, so a cycle need not be a calendar month.
 *
 * The period was only ever a label ("August - 2026"), which is why payroll
 * could not run 21st to 20th: there was nowhere to say when a period began or
 * ended. The label stays for display and for the existing duplicate guard.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->date('period_start')->nullable()->after('period');
            $table->date('period_end')->nullable()->after('period_start');
            $table->index(['user_id', 'period_start']);
        });

        Schema::table('payroll_calculations', function (Blueprint $table) {
            // 1 means calendar months. 21 means the 21st to the 20th.
            $table->unsignedTinyInteger('period_start_day')->default(1)->after('pt_slabs');
        });

        // Everything issued so far was a calendar month; fill the dates in from
        // the label so old payslips are not left without a period.
        foreach (DB::table('payslips')->whereNull('period_start')->get(['id', 'period', 'created_at']) as $slip) {
            $when = $slip->period ? strtotime('first day of ' . str_replace(' - ', ' ', $slip->period)) : false;
            $when = $when ?: strtotime((string) $slip->created_at);

            if (!$when) {
                continue;
            }

            DB::table('payslips')->where('id', $slip->id)->update([
                'period_start' => date('Y-m-01', $when),
                'period_end' => date('Y-m-t', $when),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'period_start']);
            $table->dropColumn(['period_start', 'period_end']);
        });

        Schema::table('payroll_calculations', function (Blueprint $table) {
            $table->dropColumn('period_start_day');
        });
    }

};
