<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll as HR runs it (Payroll Settings -> Payroll Calculation):
 *  - payroll_options.deduct_late_comings: whether late comings cost days (on, as before, by default).
 *  - payroll_lop_days: LOP days HR decides for an employee and pay period (uploaded on Payroll Run).
 *    When there is one, the period is paid as its days less these - attendance does not decide it.
 * ("Reduce PF / ESI for unpaid days" is payroll_calculations.prorate_statutory, already there.)
 * Idempotent.
 */
return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasTable('payroll_options') && !Schema::hasColumn('payroll_options', 'deduct_late_comings')) {
            Schema::table('payroll_options', function (Blueprint $table) {
                $table->boolean('deduct_late_comings')->default(true);
            });
        }

        if (!Schema::hasTable('payroll_lop_days')) {
            Schema::create('payroll_lop_days', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('user_id');
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('lop_days', 5, 2)->default(0);
                $table->string('note', 255)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'period_start']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_lop_days');

        if (Schema::hasTable('payroll_options') && Schema::hasColumn('payroll_options', 'deduct_late_comings')) {
            Schema::table('payroll_options', fn (Blueprint $t) => $t->dropColumn('deduct_late_comings'));
        }
    }

};
