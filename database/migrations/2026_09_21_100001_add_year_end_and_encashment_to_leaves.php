<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Year-end leave processing and leave encashment.
 *
 * The leave module already knew the three fates of unused leave — lapse, carry
 * forward, paid — but two things were missing. There was no ceiling on how much
 * could be carried forward, so an accruing leave like EL would pile up without
 * limit; and "paid" worked out the days owed and then stopped, because nothing
 * ever put that money on a payslip.
 *
 * This adds, all configurable per company:
 *
 *  - leave_types: a leave code, so payroll can recognise a leave without
 *    matching on its name; a carry-forward ceiling; and what happens to the
 *    part above the ceiling (discarded or encashed).
 *  - leave_settings: how an encashed day is valued — which salary figure, and
 *    what it is divided by.
 *  - leave_year_end_runs / leave_year_end_items: one run per financial year,
 *    with the full working for every employee and leave type, so the outcome
 *    can be checked afterwards.
 *  - leave_encashments: the money owed, as a ledger. Each row is paid on
 *    exactly one payslip and records which one, so nothing is paid twice.
 *  - payslips: the encashment paid on that slip, kept apart from net salary so
 *    every existing payslip figure is left exactly as it was.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_types', 'code')) {
                // EL / CL / SL / ML / LOP. Payroll reads this before falling back
                // to guessing from the name.
                $table->string('code', 10)->nullable()->after('type_name');
            }

            if (!Schema::hasColumn('leave_types', 'carry_forward_max')) {
                // Most that may be carried into the next year. Null = no limit.
                $table->decimal('carry_forward_max', 8, 2)->nullable()->after('unused_leave');
            }

            if (!Schema::hasColumn('leave_types', 'carry_forward_excess')) {
                // What happens to days above the ceiling.
                $table->string('carry_forward_excess', 10)->default('discard')->after('carry_forward_max');
            }
        });

        Schema::table('leave_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_settings', 'encashment_basis')) {
                // Which monthly salary figure an encashed day is valued from.
                $table->string('encashment_basis', 10)->default('basic')->after('manager_permission');
            }

            if (!Schema::hasColumn('leave_settings', 'encashment_divisor')) {
                // Per-day value = monthly figure ÷ this. 26 is the usual working-day basis.
                $table->decimal('encashment_divisor', 5, 2)->default(26)->after('encashment_basis');
            }

            if (!Schema::hasColumn('leave_settings', 'encashment_salary_source')) {
                // 'ctc': annual CTC ÷ 12, basic taken as a percentage of that.
                // 'last_payslip': the figure on the employee's latest payslip.
                $table->string('encashment_salary_source', 20)->default('ctc')->after('encashment_divisor');
            }

            if (!Schema::hasColumn('leave_settings', 'encashment_basic_pct')) {
                // Basic as a % of monthly gross, used with the 'ctc' source when
                // the employee has no percentage of their own.
                $table->decimal('encashment_basic_pct', 5, 2)->default(50)->after('encashment_salary_source');
            }
        });

        if (!Schema::hasTable('leave_year_end_runs')) {
            Schema::create('leave_year_end_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->string('financial_year', 20);
                $table->date('fy_start');
                $table->date('fy_end');
                $table->json('summary')->nullable();
                $table->unsignedInteger('run_by')->nullable();
                $table->timestamp('run_at')->nullable();
                $table->timestamps();

                // A financial year is closed once; a second run would carry the
                // same leave forward twice and pay it twice.
                $table->unique(['company_id', 'financial_year'], 'leave_year_end_runs_company_fy_unique');
            });
        }

        if (!Schema::hasTable('leave_year_end_items')) {
            Schema::create('leave_year_end_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('run_id')->index();
                $table->unsignedInteger('user_id')->index();
                $table->unsignedInteger('leave_type_id');
                $table->decimal('opening', 8, 2)->default(0);
                $table->decimal('accrued', 8, 2)->default(0);
                $table->decimal('used', 8, 2)->default(0);
                $table->decimal('balance', 8, 2)->default(0);
                $table->string('rule', 20);
                $table->decimal('carried', 8, 2)->default(0);
                $table->decimal('lapsed', 8, 2)->default(0);
                $table->decimal('encashed_days', 8, 2)->default(0);
                $table->decimal('encashment_amount', 12, 2)->default(0);
                $table->json('notes')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'leave_type_id'], 'leave_year_end_items_user_type');
            });
        }

        if (!Schema::hasTable('leave_encashments')) {
            Schema::create('leave_encashments', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->unsignedInteger('user_id')->index();
                $table->unsignedInteger('leave_type_id');
                $table->unsignedBigInteger('run_id')->nullable()->index();
                $table->string('financial_year', 20);
                $table->decimal('days', 8, 2);
                $table->string('basis', 10);
                $table->decimal('divisor', 5, 2);
                $table->decimal('monthly_base', 12, 2);
                $table->decimal('rate', 12, 2);
                $table->decimal('amount', 12, 2);
                // pending → paid on a payslip. A revoked payslip returns it to
                // pending so the next payslip picks it up again.
                $table->string('status', 10)->default('pending')->index();
                $table->unsignedBigInteger('payslip_id')->nullable()->index();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('payslips', function (Blueprint $table) {
            if (!Schema::hasColumn('payslips', 'leave_encashment')) {
                $table->decimal('leave_encashment', 12, 2)->default(0)->after('net_salary');
            }

            if (!Schema::hasColumn('payslips', 'encashed_days')) {
                $table->decimal('encashed_days', 8, 2)->default(0)->after('leave_encashment');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            foreach (['encashed_days', 'leave_encashment'] as $column) {
                if (Schema::hasColumn('payslips', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('leave_encashments');
        Schema::dropIfExists('leave_year_end_items');
        Schema::dropIfExists('leave_year_end_runs');

        Schema::table('leave_settings', function (Blueprint $table) {
            foreach (['encashment_basic_pct', 'encashment_salary_source', 'encashment_divisor', 'encashment_basis'] as $column) {
                if (Schema::hasColumn('leave_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('leave_types', function (Blueprint $table) {
            foreach (['carry_forward_excess', 'carry_forward_max', 'code'] as $column) {
                if (Schema::hasColumn('leave_types', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

};
