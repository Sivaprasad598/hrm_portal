<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->decimal('gross', 12, 2);
            $table->decimal('basic_pct', 5, 2);
            $table->decimal('hra_pct', 5, 2);
            $table->decimal('ca_threshold', 12, 2);
            $table->decimal('ca_high', 12, 2);
            $table->decimal('ca_low', 12, 2);
            $table->decimal('pt', 10, 2);
            $table->decimal('esi_threshold', 12, 2);
            $table->decimal('esi_pct', 5, 2);
            $table->decimal('pf_pct', 5, 2);
            $table->decimal('working_days', 4, 1)->unsigned()->default(0);
            $table->decimal('days_present', 4, 1)->unsigned()->default(0);
            $table->decimal('lop_days', 5, 1)->nullable();
            $table->decimal('basic', 12, 2);
            $table->decimal('hra', 12, 2);
            $table->decimal('ca', 12, 2);
            $table->decimal('others', 12, 2);
            $table->decimal('esi', 12, 2);
            $table->decimal('pf', 12, 2);
            $table->decimal('total_deductions', 12, 2);
            $table->decimal('net_salary', 12, 2);
            $table->decimal('per_day', 12, 2);
            $table->decimal('attendance_net', 12, 2);
            $table->string('emp_name', 100)->nullable();
            $table->string('emp_id', 50)->nullable();
            $table->string('emp_designation', 100)->nullable();
            $table->string('emp_pan', 20)->nullable();
            $table->string('period', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('emp_id');
            $table->index(['emp_id', 'period']);
        });

        // One-off backfill: bring across the payroll_calculations rows that were
        // already actual generated payslips (i.e. have an employee attached),
        // so payslips generated before this table existed remain viewable.
        // Calculator-only snapshots (no emp_id) stay in payroll_calculations.
        DB::statement("
            INSERT INTO payslips (
                gross, basic_pct, hra_pct, ca_threshold, ca_high, ca_low,
                pt, esi_threshold, esi_pct, pf_pct, working_days, days_present, lop_days,
                basic, hra, ca, others, esi, pf,
                total_deductions, net_salary, per_day, attendance_net,
                emp_name, emp_id, emp_designation, emp_pan, period, created_at
            )
            SELECT
                gross, basic_pct, hra_pct, ca_threshold, ca_high, ca_low,
                pt, esi_threshold, esi_pct, pf_pct, working_days, days_present, lop_days,
                basic, hra, ca, others, esi, pf,
                total_deductions, net_salary, per_day, attendance_net,
                emp_name, emp_id, emp_designation, emp_pan, period, created_at
            FROM payroll_calculations
            WHERE emp_id IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
