<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OT bank: overtime hours are banked and carried forward (no expiry), used to
 * cover LOP days or encashed when payroll is generated. See App\Services\Payroll\OtBank.
 *
 *   ot_ledger            one row per movement: earned (+, on the payslip that
 *                        closes the period), compensated (- hours, LOP days
 *                        covered), encashed (- hours, paid), adjusted (+/-,
 *                        HR / Admin, with a reason)
 *   attendance_settings  ot_mode (bank | pay), ot_compensate, ot_compensate_mode
 *                        (manual | auto), ot_encash, ot_bank_cap
 *   payslips.ot_summary  what the payslip did with the bank, as written
 *
 * Also: leave encashment is valued per day on the days of the salary period
 * (30 / 31) - leave_settings.encashment_divisor 0.
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('ot_ledger')) {
            Schema::create('ot_ledger', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->unsignedInteger('user_id')->index();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->date('period_start')->index();
                $table->string('type', 20);
                $table->decimal('hours', 8, 2);
                $table->decimal('days', 5, 2)->nullable();
                $table->decimal('amount', 12, 2)->nullable();
                $table->unsignedBigInteger('payslip_id')->nullable()->index();
                $table->string('note', 500)->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('attendance_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_settings', 'ot_mode')) {
                $table->string('ot_mode', 10)->default('bank');
                $table->boolean('ot_compensate')->default(true);
                $table->string('ot_compensate_mode', 10)->default('manual');
                $table->boolean('ot_encash')->default(true);
                $table->decimal('ot_bank_cap', 8, 2)->nullable();
            }
        });

        if (!Schema::hasColumn('payslips', 'ot_summary')) {
            Schema::table('payslips', function (Blueprint $table) {
                $table->text('ot_summary')->nullable();
            });
        }

        if (Schema::hasColumn('leave_settings', 'encashment_divisor')) {
            DB::table('leave_settings')->update(['encashment_divisor' => 0]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ot_ledger');

        Schema::table('attendance_settings', function (Blueprint $table) {
            foreach (['ot_mode', 'ot_compensate', 'ot_compensate_mode', 'ot_encash', 'ot_bank_cap'] as $c) {
                if (Schema::hasColumn('attendance_settings', $c)) {
                    $table->dropColumn($c);
                }
            }
        });

        if (Schema::hasColumn('payslips', 'ot_summary')) {
            Schema::table('payslips', fn (Blueprint $t) => $t->dropColumn('ot_summary'));
        }
    }

};
