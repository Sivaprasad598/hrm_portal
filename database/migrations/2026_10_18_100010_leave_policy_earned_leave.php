<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The leave policy:
 *
 *   Earned Leave  1 a month (12)  carried forward, at most 30   after confirmation
 *   Casual Leave  8 a year        not carried forward           after confirmation
 *   Sick Leave    8 a year        not carried forward           from the joining date
 *                                                               28 in all
 *
 * Earned Leave is the only leave paid out on leaving, up to 30 days, and is
 * used (and paid out) only after 2 years of service. A day of leave is paid at
 * the monthly salary / 26.
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::table('leave_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_settings', 'earned_leave_type_id')) {
                $table->unsignedInteger('earned_leave_type_id')->nullable();
            }
            if (!Schema::hasColumn('leave_settings', 'earned_leave_min_service_years')) {
                $table->decimal('earned_leave_min_service_years', 4, 1)->default(2);
            }
            if (!Schema::hasColumn('leave_settings', 'earned_leave_encash_max_days')) {
                $table->decimal('earned_leave_encash_max_days', 6, 2)->nullable()->default(30);
            }
        });

        $now = now();

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            $types = DB::table('leave_types')->where('company_id', $companyId)->whereNull('deleted_at')->get();
            $find = fn (string $code, string $name) => $types->first(fn ($t) => strtoupper((string) $t->code) === $code)
                ?? $types->first(fn ($t) => stripos($t->type_name, $name) !== false);

            if ($el = $find('EL', 'Earned')) {
                DB::table('leave_types')->where('id', $el->id)->update([
                    'leavetype' => 'monthly', 'no_of_leaves' => 1, 'paid' => 1,
                    'unused_leave' => 'carry forward', 'carry_forward_max' => 30, 'carry_forward_excess' => 'discard',
                    'encashed' => 1, 'allowed_probation' => 0, 'monthly_limit' => 0, 'updated_at' => $now,
                ]);
            }

            if ($cl = $find('CL', 'Casual')) {
                DB::table('leave_types')->where('id', $cl->id)->update([
                    'leavetype' => 'yearly', 'no_of_leaves' => 8, 'paid' => 1,
                    'unused_leave' => 'lapse', 'carry_forward_max' => null, 'encashed' => 0,
                    'allowed_probation' => 0, 'monthly_limit' => 0, 'updated_at' => $now,
                ]);
            }

            if ($sl = $find('SL', 'Sick')) {
                DB::table('leave_types')->where('id', $sl->id)->update([
                    'leavetype' => 'yearly', 'no_of_leaves' => 8, 'paid' => 1,
                    'unused_leave' => 'lapse', 'carry_forward_max' => null, 'encashed' => 0,
                    'allowed_probation' => 1, 'monthly_limit' => 0, 'updated_at' => $now,
                ]);
            }

            // Not in the policy. Retired only where nobody has ever taken it,
            // so no leave on record loses its type.
            foreach ($types as $type) {
                $inPolicy = in_array($type->id, array_filter([$el->id ?? null, $cl->id ?? null, $sl->id ?? null]), true)
                    || (int) $type->paid === 0;   // LOP stays: it is how unpaid leave is recorded

                if (!$inPolicy && !DB::table('leaves')->where('leave_type_id', $type->id)->exists()) {
                    DB::table('leave_types')->where('id', $type->id)->update(['deleted_at' => $now]);
                    DB::table('employee_leave_quotas')->where('leave_type_id', $type->id)->delete();
                }
            }

            DB::table('leave_settings')->where('company_id', $companyId)->update([
                'earned_leave_type_id' => $el->id ?? null,
                'earned_leave_min_service_years' => 2,
                'earned_leave_encash_max_days' => 30,
                // A day of leave is the monthly salary / 26.
                'encashment_basis' => 'gross',
                'encashment_divisor' => 26,
                'encashment_salary_source' => 'ctc',
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('leave_settings', function (Blueprint $table) {
            foreach (['earned_leave_type_id', 'earned_leave_min_service_years', 'earned_leave_encash_max_days'] as $column) {
                if (Schema::hasColumn('leave_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

};
