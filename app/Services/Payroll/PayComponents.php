<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pay that sits outside the standard salary.
 *
 * Earnings (overtime, a bonus, arrears) are added to what the employee is paid;
 * deductions (an advance being recovered, a loan instalment) come off it. Both
 * belong to a pay period, and once a payslip is issued they are attached to it,
 * so the same month's overtime cannot be paid twice.
 *
 * A standing arrangement — "₹30,000 advanced, ₹5,000 a month" — produces one
 * line per period automatically, and stops of its own accord when the balance
 * reaches nil.
 */
class PayComponents
{

    /**
     * Everything owed or recovered for one employee in one period, with any
     * instalment due from a standing arrangement drawn in first.
     *
     * @return array{earnings: array, deductions: array, earning_total: float, deduction_total: float}
     */
    public function forPeriod(int $userId, Carbon $periodStart, bool $drawInstalments = true): array
    {
        if ($drawInstalments) {
            $this->applyRules($userId, $periodStart);
            $this->drawInstalments($userId, $periodStart);
        }

        $lines = DB::table('payslip_components')
            ->where('user_id', $userId)
            ->whereDate('period_start', $periodStart->toDateString())
            ->orderBy('id')
            ->get();

        $earnings = $lines->where('kind', 'earning')->values()->all();
        $deductions = $lines->where('kind', 'deduction')->values()->all();

        return [
            'earnings' => $earnings,
            'deductions' => $deductions,
            'earning_total' => array_sum(array_map(fn ($l) => (float) $l->amount, $earnings)),
            'deduction_total' => array_sum(array_map(fn ($l) => (float) $l->amount, $deductions)),
        ];
    }

    /**
     * Add a one-off line: overtime for the month, a bonus, arrears.
     *
     * The label is written on to the line rather than looked up later, so
     * renaming an element type never changes a payslip already issued.
     */
    public function add(int $userId, Carbon $periodStart, string $kind, string $label, float $amount,
        ?string $note = null, ?int $elementTypeId = null): int
    {
        return DB::table('payslip_components')->insertGetId([
            'company_id' => DB::table('employee_details')->where('user_id', $userId)->value('company_id'),
            'user_id' => $userId,
            'period_start' => $periodStart->toDateString(),
            'kind' => $kind === 'earning' ? 'earning' : 'deduction',
            'element_type_id' => $elementTypeId,
            'label' => $label,
            'amount' => round($amount, 2),
            'note' => $note,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * The same element for a group of employees in one go — a festival bonus,
     * a deduction everyone owes.
     *
     * Anyone whose payslip for the period is already paid is left out and named
     * in the result, because a paid month cannot gain another line.
     *
     * @param  array<int, float>  $amounts  employee id => amount
     * @return array{added: int, skipped: array<int, string>}
     */
    public function addMany(array $amounts, PayPeriod $cycle, string $kind, string $label,
        ?string $note = null, ?int $elementTypeId = null): array
    {
        $added = 0;
        $skipped = [];

        foreach ($amounts as $userId => $amount) {
            $amount = round((float) $amount, 2);

            if ($amount <= 0) {
                continue;
            }

            $blocked = $this->issuedPeriod((int) $userId, $cycle);

            if ($blocked !== null) {
                $skipped[] = $blocked;
                continue;
            }

            $this->add((int) $userId, $cycle->start, $kind, $label, $amount, $note, $elementTypeId);
            $added++;
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    /**
     * Why this employee's period cannot take another line, or null if it can.
     *
     * A payslip that is already issued cannot gain one: lines are attached to a
     * payslip when it is issued, so anything added afterwards would show on the
     * payroll screen and never on the slip itself.
     *
     * The payslip is found by its period label, which every payslip carries,
     * rather than by date — slips issued before the cycle dates existed have
     * no start date on record.
     */
    public function issuedPeriod(int $userId, PayPeriod $cycle): ?string
    {
        $slip = DB::table('payslips as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('p.user_id', $userId)
            ->where('p.period', $cycle->label)
            ->where('p.status', 'active')
            ->whereNull('p.deleted_at')
            ->first(['u.name', 'p.approval_status']);

        if (!$slip) {
            return null;
        }

        return $slip->name . ($slip->approval_status === 'paid'
            ? ' — that month is already paid'
            : ' — a payslip for that month is already issued');
    }

    /**
     * Start a standing arrangement: so much advanced, so much taken each month.
     *
     * A total of nil means open ended — it keeps going until it is stopped,
     * which suits a recurring allowance or an indefinite recovery.
     */
    public function openArrangement(int $userId, string $kind, string $label, ?float $total, float $instalment,
        Carbon $startsOn, ?string $note = null, ?int $elementTypeId = null): int
    {
        return DB::table('payroll_arrangements')->insertGetId([
            'company_id' => DB::table('employee_details')->where('user_id', $userId)->value('company_id'),
            'user_id' => $userId,
            'kind' => $kind === 'earning' ? 'earning' : 'deduction',
            'element_type_id' => $elementTypeId,
            'label' => $label,
            'total_amount' => $total !== null && $total > 0 ? round($total, 2) : null,
            'instalment' => round($instalment, 2),
            'recovered' => 0,
            'starts_on' => $startsOn->toDateString(),
            'status' => 'active',
            'note' => $note,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Stop an arrangement taking any more instalments.
     *
     * Instalments already taken stay where they are; a line on a payslip not
     * yet issued can still be removed one at a time if that is wanted.
     */
    public function cancelArrangement(int $arrangementId): bool
    {
        return DB::table('payroll_arrangements')->where('id', $arrangementId)
            ->whereIn('status', ['active', 'completed'])
            ->update(['status' => 'cancelled', 'updated_at' => now()]) > 0;
    }

    /**
     * Remove a line, as long as its payslip has not been issued.
     */
    public function remove(int $componentId): bool
    {
        $line = DB::table('payslip_components')->find($componentId);

        if (!$line || $line->payslip_id) {
            return false;
        }

        // An instalment taken back is owed again — but an arrangement somebody
        // has stopped stays stopped.
        if ($line->arrangement_id) {
            DB::table('payroll_arrangements')->where('id', $line->arrangement_id)
                ->update([
                    'recovered' => DB::raw('GREATEST(0, recovered - ' . (float) $line->amount . ')'),
                    'status' => DB::raw("CASE WHEN status = 'cancelled' THEN 'cancelled' ELSE 'active' END"),
                ]);
        }

        // A line a standing rule produced is deliberately *not* un-recorded in
        // pay_element_rule_applications: removing it means "not this month for
        // this person", and it must not reappear next time a screen is opened.
        return DB::table('payslip_components')->where('id', $componentId)->delete() > 0;
    }

    /**
     * Attach a period's lines to the payslip that paid them, and move any
     * arrangement's balance along.
     */
    public function attachToPayslip(int $userId, Carbon $periodStart, int $payslipId): void
    {
        DB::table('payslip_components')
            ->where('user_id', $userId)
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereNull('payslip_id')
            ->update(['payslip_id' => $payslipId, 'updated_at' => now()]);
    }

    /**
     * Release a revoked payslip's lines, so a replacement picks them up again.
     */
    public function releaseFromPayslip(int $payslipId): void
    {
        DB::table('payslip_components')->where('payslip_id', $payslipId)
            ->update(['payslip_id' => null, 'updated_at' => now()]);
    }

    /**
     * Give this employee whatever the standing rules say they should get.
     *
     * A rule produces one ordinary line per period. Once applied it is recorded
     * against the rule, employee and period, so opening a screen twice does not
     * pay twice — and so a line somebody deliberately removed stays removed
     * rather than reappearing the next time the screen is opened.
     *
     * Two rules reaching the same person both apply, each as its own line.
     */
    public function applyRules(int $userId, Carbon $periodStart): void
    {
        $period = $periodStart->toDateString();

        $rules = DB::table('pay_element_rules as r')
            ->join('pay_element_types as t', 't.id', '=', 'r.element_type_id')
            ->where('r.status', 'active')
            ->where('t.is_active', true)
            ->whereDate('r.starts_on', '<=', $period)
            ->where(fn ($q) => $q->whereNull('r.ends_on')->orWhereDate('r.ends_on', '>=', $period))
            ->orderBy('r.id')
            ->get(['r.id', 'r.company_id', 'r.scope', 'r.scope_id', 'r.amount', 'r.note',
                'r.element_type_id', 't.name as element_name', 't.kind']);

        if ($rules->isEmpty()) {
            return;
        }

        $already = DB::table('pay_element_rule_applications')
            ->where('user_id', $userId)->whereDate('period_start', $period)
            ->pluck('rule_id')->all();

        foreach ($rules as $rule) {
            if (in_array($rule->id, $already) || !$this->ruleReaches($rule, $userId)) {
                continue;
            }

            $amount = round((float) $rule->amount, 2);

            if ($amount <= 0) {
                continue;
            }

            DB::table('payslip_components')->insert([
                'company_id' => $rule->company_id,
                'user_id' => $userId,
                'period_start' => $period,
                'kind' => $rule->kind,
                'element_type_id' => $rule->element_type_id,
                'rule_id' => $rule->id,
                'label' => $rule->element_name,
                'amount' => $amount,
                'note' => $rule->note ?: 'Standing instruction',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('pay_element_rule_applications')->insert([
                'rule_id' => $rule->id,
                'user_id' => $userId,
                'period_start' => $period,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Whether a rule covers this employee.
     */
    protected function ruleReaches(object $rule, int $userId): bool
    {
        return match ($rule->scope) {
            'everyone' => true,
            'employee' => (int) $rule->scope_id === $userId,
            'department' => DB::table('employee_details')->where('user_id', $userId)
                ->where('department_id', $rule->scope_id)->exists(),
            'designation' => DB::table('employee_details')->where('user_id', $userId)
                ->where('designation_id', $rule->scope_id)->exists(),
            'group' => DB::table('pay_element_group_members')->where('group_id', $rule->scope_id)
                ->where('user_id', $userId)->exists(),
            default => false,
        };
    }

    /**
     * Take this period's instalment from each standing arrangement.
     *
     * Only once per period: a line already drawn is left alone, so opening the
     * screen twice does not take the money twice. The last instalment is
     * trimmed to whatever is actually left owing.
     */
    protected function drawInstalments(int $userId, Carbon $periodStart): void
    {
        $arrangements = DB::table('payroll_arrangements')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereDate('starts_on', '<=', $periodStart->copy()->endOfMonth()->toDateString())
            ->get();

        foreach ($arrangements as $arrangement) {
            $already = DB::table('payslip_components')
                ->where('arrangement_id', $arrangement->id)
                ->whereDate('period_start', $periodStart->toDateString())
                ->exists();

            if ($already) {
                continue;
            }

            // No total means open ended: the instalment is taken every period
            // until somebody stops it.
            $openEnded = $arrangement->total_amount === null;
            $outstanding = $openEnded ? null : round((float) $arrangement->total_amount - (float) $arrangement->recovered, 2);

            if (!$openEnded && $outstanding <= 0) {
                DB::table('payroll_arrangements')->where('id', $arrangement->id)->update(['status' => 'completed']);
                continue;
            }

            $amount = $openEnded ? (float) $arrangement->instalment : min((float) $arrangement->instalment, $outstanding);

            DB::table('payslip_components')->insert([
                'company_id' => $arrangement->company_id,
                'user_id' => $userId,
                'period_start' => $periodStart->toDateString(),
                'kind' => $arrangement->kind,
                'element_type_id' => $arrangement->element_type_id,
                'label' => $arrangement->label,
                'amount' => round($amount, 2),
                'arrangement_id' => $arrangement->id,
                'note' => $openEnded
                    ? 'Monthly, until it is stopped'
                    : 'Instalment — ' . number_format($outstanding - $amount, 2) . ' left after this',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $recovered = round((float) $arrangement->recovered + $amount, 2);

            DB::table('payroll_arrangements')->where('id', $arrangement->id)->update([
                'recovered' => $recovered,
                // An open-ended arrangement is never finished by itself.
                'status' => !$openEnded && $recovered >= (float) $arrangement->total_amount ? 'completed' : 'active',
                'updated_at' => now(),
            ]);
        }
    }

}
