<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Income tax deducted at source, as a figure per employee.
 *
 * Deliberately not a tax engine: declarations, the choice of regime, exemptions
 * and the annual projection are a piece of work that needs an accountant's
 * sign-off. What stops payslips being wrong today is somewhere to put the
 * monthly figure the accountant gives, so it is deducted, shown on the slip and
 * totalled for the year.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('monthly_tds', 12, 2)->nullable()->after('basic_for_payroll');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->decimal('tds', 12, 2)->default(0)->after('pt');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('monthly_tds');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn('tds');
        });
    }

};
