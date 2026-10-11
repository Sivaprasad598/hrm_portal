<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give a payslip a lifecycle, so the same month cannot be generated twice.
 *
 * Generating previously appended a row with no check at all — one employee
 * accumulated 23 rows for a single month representing two actual outcomes, and
 * only the newest was ever shown. A payslip now has a status: an active one
 * holds that employee's month, and generating another is refused until the
 * first is revoked with a reason or removed.
 *
 * Revoking keeps the row. It is pay history, and a mistake that reached someone
 * needs to stay visible along with why it was withdrawn — so `revoke_reason` is
 * required by the screen, and who did it and when are recorded beside it.
 *
 * `deleted_at` is a soft delete, deliberately: "delete" removes a slip from
 * every list and frees the month, but the row survives underneath. Nothing in
 * an HR system should shred a salary record beyond recovery on one click.
 */
return new class extends Migration
{

    public function up(): void
    {
        if (!Schema::hasTable('payslips')) {
            return;
        }

        Schema::table('payslips', function (Blueprint $table) {
            if (!Schema::hasColumn('payslips', 'status')) {
                // 'active' holds the month; 'revoked' releases it.
                $table->string('status', 20)->default('active')->after('period');
            }

            if (!Schema::hasColumn('payslips', 'revoke_reason')) {
                $table->text('revoke_reason')->nullable()->after('status');
            }

            if (!Schema::hasColumn('payslips', 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable()->after('revoke_reason');
            }

            if (!Schema::hasColumn('payslips', 'revoked_by')) {
                $table->unsignedBigInteger('revoked_by')->nullable()->after('revoked_at');
            }

            if (!Schema::hasColumn('payslips', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('revoked_by');
            }
        });

        // The lookup the duplicate guard makes on every generation.
        if (!$this->hasIndex('payslips_emp_period_status_idx')) {
            Schema::table('payslips', function (Blueprint $table) {
                $table->index(['emp_id', 'period', 'status'], 'payslips_emp_period_status_idx');
            });
        }

        // Existing rows predate the guard. Only the newest per employee and
        // period stays active; the rest were superseded saves, not real
        // payslips, so they are marked as such rather than left to block
        // generation forever.
        $this->supersedeOlderRows();
    }

    private function supersedeOlderRows(): void
    {
        $latest = \Illuminate\Support\Facades\DB::table('payslips')
            ->selectRaw('emp_id, period, MAX(id) AS keep_id')
            ->groupBy('emp_id', 'period')
            ->pluck('keep_id')
            ->all();

        if ($latest === []) {
            return;
        }

        \Illuminate\Support\Facades\DB::table('payslips')
            ->whereNotIn('id', $latest)
            ->update([
                'status' => 'superseded',
                'revoke_reason' => 'Superseded automatically: an older save of the same employee and period, '
                    . 'kept for history when duplicate generation was prevented.',
                'revoked_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('payslips')) {
            return;
        }

        if ($this->hasIndex('payslips_emp_period_status_idx')) {
            Schema::table('payslips', function (Blueprint $table) {
                $table->dropIndex('payslips_emp_period_status_idx');
            });
        }

        Schema::table('payslips', function (Blueprint $table) {
            foreach (['status', 'revoke_reason', 'revoked_at', 'revoked_by', 'deleted_at'] as $column) {
                if (Schema::hasColumn('payslips', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function hasIndex(string $name): bool
    {
        return collect(Schema::getIndexes('payslips'))
            ->contains(fn ($index) => ($index['name'] ?? null) === $name);
    }

};
