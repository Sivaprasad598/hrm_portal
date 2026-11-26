<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll Run -> an employee's own settings for one pay period, over the company's
 * (Payroll Settings -> Deduct late comings / Reduce PF / ESI for unpaid days).
 * NULL in a column = follow the company setting. Idempotent.
 */
return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasTable('payroll_employee_switches')) {
            return;
        }

        Schema::create('payroll_employee_switches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->boolean('deduct_late_comings')->nullable();
            $table->boolean('prorate_statutory')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'period_start'], 'payroll_emp_switch_user_period');
            $table->index('period_start', 'payroll_emp_switch_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_employee_switches');
    }

};
