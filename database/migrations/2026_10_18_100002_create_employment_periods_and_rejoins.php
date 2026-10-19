<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leaving and coming back, as the same employee.
 *
 *  - employment_periods: one row per stretch of employment - joined, left,
 *    why, the settlement that closed it, and the terms at the time. The
 *    employee record shows the current period; earlier ones are history.
 *  - employee_rejoins: the rejoin itself - collected on its own form as a
 *    draft, then confirmed - with the new terms, probation, and who did it.
 *  - employee_docs / settlements .employment_period_id: which period a
 *    document or a settlement belongs to, so a rejoin collects its own
 *    documents and a later exit can be settled again.
 *  - resignations / terminations / employee_exits .closed_by_rejoin_id: an
 *    exit record from a period that has ended in a rejoin no longer closes
 *    the account.
 *
 * Every existing employee gets their first period, from the joining and exit
 * dates already on file.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('employment_periods')) {
            Schema::create('employment_periods', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->unsignedInteger('user_id');
                $table->unsignedSmallInteger('period_no')->default(1);
                $table->date('joined_on')->nullable();
                $table->date('left_on')->nullable();
                $table->string('exit_type', 30)->nullable();          // resigned | terminated | other
                $table->string('exit_reason', 500)->nullable();
                $table->boolean('eligible_for_rehire')->nullable();
                $table->string('not_eligible_reason', 500)->nullable();
                $table->unsignedBigInteger('resignation_id')->nullable();
                $table->unsignedBigInteger('termination_id')->nullable();
                $table->unsignedBigInteger('employee_exit_id')->nullable();
                $table->unsignedBigInteger('settlement_id')->nullable();
                $table->unsignedBigInteger('rejoin_id')->nullable();   // the rejoin that started this period
                // the terms while it lasted
                $table->string('employee_code', 100)->nullable();
                $table->string('employee_card_id', 100)->nullable();
                $table->unsignedInteger('designation_id')->nullable();
                $table->unsignedInteger('department_id')->nullable();
                $table->unsignedInteger('branch_id')->nullable();
                $table->decimal('employee_ctc', 14, 2)->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'period_no']);
                $table->index(['user_id', 'joined_on']);
            });
        }

        if (!Schema::hasTable('employee_rejoins')) {
            Schema::create('employee_rejoins', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->unsignedInteger('user_id')->index();
                $table->unsignedBigInteger('previous_period_id')->nullable();
                $table->unsignedBigInteger('new_period_id')->nullable();
                $table->string('status', 15)->default('draft');        // draft | confirmed | cancelled
                $table->date('rejoin_date');
                $table->unsignedInteger('break_days')->nullable();
                $table->string('rejoin_reason', 500)->nullable();
                $table->text('notes')->nullable();
                // the new terms
                $table->string('employee_code', 100)->nullable();
                $table->string('employee_card_id', 100)->nullable();
                $table->string('iapp_emp_code', 100)->nullable();
                $table->unsignedInteger('designation_id')->nullable();
                $table->unsignedInteger('department_id')->nullable();
                $table->unsignedInteger('branch_id')->nullable();
                $table->unsignedInteger('reporting_to')->nullable();
                $table->unsignedInteger('role_id')->nullable();
                $table->string('employment_type', 30)->nullable();
                $table->decimal('employee_ctc', 14, 2)->nullable();
                $table->decimal('basic_for_payroll', 8, 2)->nullable();
                $table->unsignedBigInteger('employee_shift_id')->nullable();
                $table->boolean('probation_applies')->default(true);
                $table->unsignedSmallInteger('probation_months')->nullable();
                $table->date('probation_end_date')->nullable();
                $table->string('skip_probation_reason', 500)->nullable();
                $table->string('rehire_override_reason', 500)->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('confirmed_by')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->unsignedInteger('cancelled_by')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
            });
        }

        foreach (['employee_docs', 'settlements'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'employment_period_id')) {
                    $table->unsignedBigInteger('employment_period_id')->nullable()->index();
                }
            });
        }

        foreach (['resignations', 'terminations', 'employee_exits'] as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (!Schema::hasColumn($tableName, 'closed_by_rejoin_id')) {
                        $table->unsignedBigInteger('closed_by_rejoin_id')->nullable();
                    }
                });
            }
        }

        // ---- every existing employee's first period
        $details = DB::table('employee_details as ed')
            ->join('users as u', 'u.id', '=', 'ed.user_id')
            ->get(['ed.user_id', 'ed.company_id', 'ed.joining_date', 'ed.last_date', 'ed.employee_id', 'ed.employee_card_id',
                'ed.designation_id', 'ed.department_id', 'ed.branch_id', 'ed.employment_status', 'u.employee_ctc']);

        foreach ($details as $d) {
            if (DB::table('employment_periods')->where('user_id', $d->user_id)->exists()) {
                continue;
            }

            $leftOn = $d->last_date ? substr((string) $d->last_date, 0, 10) : null;
            $resignation = DB::table('resignations')->where('user_id', $d->user_id)->where('status', 'approved')
                ->orderByDesc('last_working_date')->first(['id', 'reason']);
            $termination = Schema::hasTable('terminations')
                ? DB::table('terminations')->where('user_id', $d->user_id)->orderByDesc('last_working_date')->first(['id', 'reason']) : null;

            $periodId = DB::table('employment_periods')->insertGetId([
                'company_id' => $d->company_id, 'user_id' => $d->user_id, 'period_no' => 1,
                'joined_on' => $d->joining_date ? substr((string) $d->joining_date, 0, 10) : null,
                'left_on' => $leftOn,
                'exit_type' => $leftOn ? ($termination ? 'terminated' : ($resignation ? 'resigned' : 'other')) : null,
                'exit_reason' => $leftOn ? ($termination->reason ?? $resignation->reason ?? null) : null,
                'resignation_id' => $resignation->id ?? null,
                'termination_id' => $termination->id ?? null,
                'employee_code' => $d->employee_id, 'employee_card_id' => $d->employee_card_id,
                'designation_id' => $d->designation_id, 'department_id' => $d->department_id, 'branch_id' => $d->branch_id,
                'employee_ctc' => $d->employee_ctc,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            DB::table('employee_docs')->where('user_id', $d->user_id)->whereNull('employment_period_id')
                ->update(['employment_period_id' => $periodId]);
            DB::table('settlements')->where('user_id', $d->user_id)->whereNull('employment_period_id')
                ->update(['employment_period_id' => $periodId]);
        }
    }

    public function down(): void
    {
        foreach (['resignations', 'terminations', 'employee_exits'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'closed_by_rejoin_id')) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('closed_by_rejoin_id'));
            }
        }

        foreach (['employee_docs', 'settlements'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('employment_period_id'));
        }

        Schema::dropIfExists('employee_rejoins');
        Schema::dropIfExists('employment_periods');
    }
};
