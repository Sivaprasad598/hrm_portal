<?php

namespace App\Services\Leaves;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Closes a financial year for leave.
 *
 * For every employee and every limited leave type, works out what is left at
 * the end of the year and applies the type's rule to it:
 *
 *  - lapse          the balance is discarded
 *  - carry forward  the balance moves into next year, up to the type's ceiling;
 *                   anything above the ceiling is discarded or encashed,
 *                   whichever the type is set to
 *  - paid           the balance is encashed
 *
 * Encashed days are valued from the employee's salary using the company's
 * encashment settings, and written to a ledger that the next payslip pays out.
 *
 * The balance is computed here for an explicit financial-year window rather
 * than read from the live quota table. The quota reflects "today", and a
 * year-end run happens after the year has closed — by then the quota already
 * describes the new year. Every figure the calculation uses is returned with
 * the result, so the preview screen can show the working, not just the answer.
 *
 * Nothing is written by preview(). apply() writes once per financial year.
 */
class LeaveYearEnd
{

    public const RULE_LAPSE = 'lapse';
    public const RULE_CARRY = 'carry forward';
    public const RULE_PAID = 'paid';

    protected object $settings;

    public function __construct(
        protected int $companyId,
        protected int $yearStartMonth,
        protected string $leavesStartFrom = 'year_start'
    ) {
        $this->settings = DB::table('leave_settings')->where('company_id', $companyId)->first()
            ?? DB::table('leave_settings')->first()
            ?? (object) [];
    }

    public static function forCompany(object $company): self
    {
        return new self(
            (int) $company->id,
            (int) ($company->year_starts_from ?: 1),
            (string) ($company->leaves_start_from ?: 'year_start')
        );
    }

    // ------------------------------------------------------------------
    // Financial years
    // ------------------------------------------------------------------

    /**
     * The financial year containing a date.
     *
     * @return array{start: Carbon, end: Carbon, label: string}
     */
    public function financialYear(Carbon $date): array
    {
        $year = $date->month >= $this->yearStartMonth ? $date->year : $date->year - 1;
        $start = Carbon::create($year, $this->yearStartMonth, 1)->startOfDay();
        $end = $start->copy()->addYear()->subDay()->endOfDay();

        // A calendar year is named by its year; one that straddles two years
        // by both, the way it is written on Indian payroll documents.
        $label = $this->yearStartMonth === 1
            ? (string) $start->year
            : $start->year . '-' . substr((string) $end->year, 2);

        return ['start' => $start, 'end' => $end, 'label' => $label];
    }

    /**
     * Recent financial years, newest first, each marked closed or open.
     */
    public function yearOptions(int $count = 4): array
    {
        $closed = DB::table('leave_year_end_runs')
            ->where('company_id', $this->companyId)
            ->pluck('id', 'financial_year')
            ->all();

        $options = [];
        $cursor = now();

        for ($i = 0; $i < $count; $i++) {
            $fy = $this->financialYear($cursor);

            $options[] = $fy + [
                'closed' => isset($closed[$fy['label']]),
                'run_id' => $closed[$fy['label']] ?? null,
                'ended' => $fy['end']->isPast(),
            ];

            $cursor = $fy['start']->copy()->subDay();
        }

        return $options;
    }

    // ------------------------------------------------------------------
    // Preview
    // ------------------------------------------------------------------

    /**
     * What closing this financial year would do. Writes nothing.
     */
    public function preview(Carbon $fyStart, Carbon $fyEnd): array
    {
        $types = DB::table('leave_types')
            ->where('company_id', $this->companyId)
            ->whereNull('deleted_at')
            ->where('leavetype', '!=', 'unlimited')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $employees = DB::table('users')
            ->join('employee_details as ed', 'ed.user_id', '=', 'users.id')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.name', 'employee')
            ->where('users.company_id', $this->companyId)
            ->where('users.status', 'active')
            // Someone who has left is settled through their exit, not carried
            // into a year they will not work.
            ->where(fn ($q) => $q->whereNull('ed.employment_status')->orWhere('ed.employment_status', '!=', 'off_boarded'))
            ->whereDate('ed.joining_date', '<=', $fyEnd->toDateString())
            ->select(
                'users.id', 'users.name', 'ed.employee_id', 'ed.employee_card_id', 'ed.joining_date',
                'ed.probation_end_date', 'users.employee_ctc', 'users.basic_for_payroll'
            )
            ->distinct()
            ->orderBy('users.name')
            ->get();

        $userIds = $employees->pluck('id')->all();

        // The vendor's quota rows are the record of who is eligible for which
        // leave (gender, department, role and so on are applied when they are
        // created). No quota row, no entitlement.
        $quotas = DB::table('employee_leave_quotas')
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy(fn ($q) => $q->user_id . '|' . $q->leave_type_id);

        $used = $this->usedLeaves($userIds, $fyStart, $fyEnd);
        $opening = $this->openingBalances($userIds, $fyStart);

        $rows = [];
        $totals = ['employees' => 0, 'carried' => 0.0, 'lapsed' => 0.0, 'encashed_days' => 0.0, 'amount' => 0.0];
        $warnings = [];

        if ($this->leavesStartFrom === 'joining_date') {
            $warnings[] = 'Leave years are set to run from each employee\'s joining date. Year-end closes the '
                . 'company financial year, so switch "Count leaves from" to the start of the year under Leave Settings '
                . 'for the figures to line up.';
        }

        foreach ($employees as $employee) {
            $employeeRows = [];

            foreach ($types as $type) {
                $key = $employee->id . '|' . $type->id;
                $quota = $quotas->get($key);

                if (!$quota) {
                    continue;
                }

                $row = $this->evaluate($employee, $type, $quota, $fyStart, $fyEnd,
                    $opening[$key] ?? null, (float) ($used[$key] ?? 0));

                $employeeRows[] = $row;

                $totals['carried'] += $row['carried'];
                $totals['lapsed'] += $row['lapsed'];
                $totals['encashed_days'] += $row['encashed_days'];
                $totals['amount'] += $row['amount'];
            }

            if ($employeeRows === []) {
                continue;
            }

            $totals['employees']++;

            $rows[] = [
                'user_id' => (int) $employee->id,
                'name' => $employee->name,
                'employee_id' => $employee->employee_id,
                'joining_date' => $employee->joining_date ? substr((string) $employee->joining_date, 0, 10) : null,
                'types' => $employeeRows,
                'amount' => array_sum(array_column($employeeRows, 'amount')),
            ];
        }

        foreach (['carried', 'lapsed', 'encashed_days', 'amount'] as $k) {
            $totals[$k] = round($totals[$k], 2);
        }

        return [
            'rows' => $rows,
            'totals' => $totals,
            'warnings' => $warnings,
            'types' => $types->map(fn ($t) => [
                'id' => (int) $t->id,
                'name' => $t->type_name,
                'code' => $t->code,
                'rule' => $t->unused_leave,
                'max' => $t->carry_forward_max !== null ? (float) $t->carry_forward_max : null,
                'excess' => $t->carry_forward_excess,
                'allotment' => $t->leavetype,
                'count' => (float) $t->no_of_leaves,
            ])->values()->all(),
            'settings' => $this->settingsSummary(),
        ];
    }

    /**
     * One employee, one leave type.
     */
    protected function evaluate(object $employee, object $type, object $quota, Carbon $fyStart, Carbon $fyEnd, ?float $opening, float $used): array
    {
        $notes = [];

        // Opening balance: what the previous year-end carried in. With no
        // previous run, whatever the vendor's own carry-forward left behind.
        if ($opening === null) {
            $opening = (float) ($quota->carry_forward_leaves ?? 0);
        }

        [$accrued, $eligibleFrom] = $this->accrual($employee, $type, $quota, $fyStart, $fyEnd, $notes);

        $balance = max(0.0, $opening + $accrued - $used);
        $rule = $type->unused_leave ?: self::RULE_LAPSE;

        $carried = 0.0;
        $lapsed = 0.0;
        $encashDays = 0.0;

        if ($rule === self::RULE_CARRY) {
            $max = $type->carry_forward_max !== null ? (float) $type->carry_forward_max : null;
            $carried = $max === null ? $balance : min($balance, $max);
            $excess = $balance - $carried;

            if ($excess > 0) {
                if ($type->carry_forward_excess === 'encash') {
                    $encashDays = $excess;
                    $notes[] = 'Above the ' . self::fmt($max) . '-day ceiling: ' . self::fmt($excess) . ' encashed';
                } else {
                    $lapsed = $excess;
                    $notes[] = 'Above the ' . self::fmt($max) . '-day ceiling: ' . self::fmt($excess) . ' discarded';
                }
            }
        } elseif ($rule === self::RULE_PAID) {
            $encashDays = $balance;
        } else {
            $lapsed = $balance;
        }

        $salary = $encashDays > 0 ? $this->salaryBase($employee) : ['base' => null, 'rate' => null, 'exact_rate' => null, 'source' => null];
        $amount = 0.0;

        if ($encashDays > 0) {
            if ($salary['rate'] === null) {
                $notes[] = 'No salary on record, so the encashed days cannot be valued. Add the CTC before closing the year.';
            } else {
                // From the unrounded rate: rounding the day rate first and then
                // multiplying drifts by paise (4.5 days came out ₹1,730.79
                // instead of ₹1,730.77).
                $amount = round($encashDays * $salary['exact_rate'], 2);
            }
        }

        return [
            'leave_type_id' => (int) $type->id,
            'type_name' => $type->type_name,
            'code' => $type->code,
            'rule' => $rule,
            'eligible_from' => $eligibleFrom?->toDateString(),
            'opening' => round($opening, 2),
            'accrued' => round($accrued, 2),
            'used' => round($used, 2),
            'balance' => round($balance, 2),
            'carried' => round($carried, 2),
            'lapsed' => round($lapsed, 2),
            'encashed_days' => round($encashDays, 2),
            'monthly_base' => $salary['base'],
            'rate' => $salary['rate'],
            'salary_source' => $salary['source'],
            'amount' => $amount,
            'notes' => $notes,
        ];
    }

    /**
     * Leave earned during the financial year.
     *
     * A month counts if the employee was eligible by the 15th of it. Monthly
     * types earn their number every such month (EL: 1 a month); yearly types
     * earn their annual number in proportion. The result is rounded to the
     * half day the same way the rest of the leave module rounds.
     *
     * @return array{0: float, 1: ?Carbon}
     */
    protected function accrual(object $employee, object $type, object $quota, Carbon $fyStart, Carbon $fyEnd, array &$notes): array
    {
        // A quota fixed by hand for this employee overrides the policy.
        if ((int) ($quota->leave_type_impact ?? 0) === 1) {
            $notes[] = 'Quota set manually for this employee';

            return [(float) $quota->no_of_leaves, null];
        }

        $eligibleFrom = $fyStart->copy();
        $joining = $employee->joining_date ? Carbon::parse($employee->joining_date)->startOfDay() : null;

        if ($joining) {
            $afterJoining = $joining->copy();

            if ($type->effective_after) {
                $afterJoining = $type->effective_type === 'months'
                    ? $afterJoining->addMonths((int) $type->effective_after)
                    : $afterJoining->addDays((int) $type->effective_after);
            }

            $eligibleFrom = $eligibleFrom->max($afterJoining);
        }

        // "After confirmation": not while on probation.
        if (!(int) $type->allowed_probation && $employee->probation_end_date) {
            $eligibleFrom = $eligibleFrom->max(Carbon::parse($employee->probation_end_date)->addDay()->startOfDay());
        }

        if ($eligibleFrom->gt($fyEnd)) {
            $notes[] = 'Not eligible during this year';

            return [0.0, $eligibleFrom];
        }

        $months = 0;
        $totalMonths = 0;

        for ($m = $fyStart->copy()->startOfMonth(); $m->lte($fyEnd); $m->addMonth()) {
            $totalMonths++;

            if ($eligibleFrom->lte($m->copy()->day(15)->endOfDay())) {
                $months++;
            }
        }

        $perYear = (float) $type->no_of_leaves;

        $accrued = $type->leavetype === 'monthly'
            ? $perYear * $months
            : ($totalMonths > 0 ? $perYear * $months / $totalMonths : 0.0);

        if ($months < $totalMonths) {
            $notes[] = 'Eligible for ' . $months . ' of ' . $totalMonths . ' months';
        }

        return [self::roundHalf($accrued), $eligibleFrom];
    }

    /**
     * Approved leave taken in the window, half days counted as half.
     *
     * @return array<string, float>  "user|type" => days
     */
    protected function usedLeaves(array $userIds, Carbon $fyStart, Carbon $fyEnd): array
    {
        if ($userIds === []) {
            return [];
        }

        $used = [];

        $rows = DB::table('leaves')
            ->whereIn('user_id', $userIds)
            ->where('status', 'approved')
            ->whereBetween('leave_date', [$fyStart->toDateString(), $fyEnd->toDateString()])
            ->get(['user_id', 'leave_type_id', 'duration']);

        foreach ($rows as $row) {
            $key = $row->user_id . '|' . $row->leave_type_id;
            $used[$key] = ($used[$key] ?? 0) + ($row->duration === 'half day' ? 0.5 : 1.0);
        }

        return $used;
    }

    /**
     * What the most recent earlier year-end carried into this year.
     *
     * @return array<string, float>  "user|type" => days
     */
    protected function openingBalances(array $userIds, Carbon $fyStart): array
    {
        if ($userIds === []) {
            return [];
        }

        $previousRun = DB::table('leave_year_end_runs')
            ->where('company_id', $this->companyId)
            ->where('fy_end', '<', $fyStart->toDateString())
            ->orderByDesc('fy_end')
            ->value('id');

        if (!$previousRun) {
            return [];
        }

        return DB::table('leave_year_end_items')
            ->where('run_id', $previousRun)
            ->whereIn('user_id', $userIds)
            ->get(['user_id', 'leave_type_id', 'carried'])
            ->mapWithKeys(fn ($r) => [$r->user_id . '|' . $r->leave_type_id => (float) $r->carried])
            ->all();
    }

    /**
     * The monthly salary figure an encashed day is valued from, and the rate.
     *
     * @return array{base: ?float, rate: ?float, source: ?string}
     */
    public function salaryBase(object $employee): array
    {
        $basis = $this->settings->encashment_basis ?? 'basic';
        // 0 = the days in the salary period (30 / 31); otherwise a fixed number.
        $divisor = self::divisorDays($this->settings->encashment_divisor ?? 0);
        $source = $this->settings->encashment_salary_source ?? 'ctc';

        $base = null;
        $label = null;

        if ($source === 'last_payslip') {
            $slip = DB::table('payslips')
                ->where('emp_id', (string) $employee->employee_card_id)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->first(['basic', 'gross', 'period']);

            if ($slip) {
                $base = (float) ($basis === 'gross' ? $slip->gross : $slip->basic);
                $label = ucfirst($basis) . ' on the ' . $slip->period . ' payslip';
            }
        } elseif ((float) $employee->employee_ctc > 0) {
            $gross = (float) $employee->employee_ctc / 12;

            if ($basis === 'gross') {
                $base = $gross;
                $label = 'CTC ÷ 12';
            } else {
                // The employee's own basic %, where one is set; otherwise the
                // company default.
                $own = (float) $employee->basic_for_payroll;
                $pct = ($own > 0 && $own <= 100) ? $own : (float) ($this->settings->encashment_basic_pct ?? 50);
                $base = $gross * $pct / 100;
                $label = 'CTC ÷ 12 × ' . self::fmt($pct) . '% basic';
            }
        }

        if ($base === null || $base <= 0) {
            return ['base' => null, 'rate' => null, 'exact_rate' => null, 'source' => null];
        }

        return [
            'base' => round($base, 2),
            'rate' => round($base / $divisor, 2),
            'exact_rate' => $base / $divisor,
            'source' => $label . ' ÷ ' . self::fmt($divisor) . ((float) ($this->settings->encashment_divisor ?? 0) == 0 ? ' (days in the salary period)' : ''),
        ];
    }

    /** The encashment divisor in days: 0 means the days of the salary period running now. */
    public static function divisorDays($setting): float
    {
        $value = (float) $setting;

        return $value >= 1 ? $value : (float) \App\Services\Payroll\PayPeriod::containing(now())->days();
    }

    public function settingsSummary(): array
    {
        return [
            'basis' => $this->settings->encashment_basis ?? 'basic',
            'divisor' => self::divisorDays($this->settings->encashment_divisor ?? 0),
            'divisor_label' => (float) ($this->settings->encashment_divisor ?? 0) >= 1
                ? self::fmt((float) $this->settings->encashment_divisor)
                : self::fmt(self::divisorDays(0)) . ' (days in the salary period)',
            'source' => $this->settings->encashment_salary_source ?? 'ctc',
            'basic_pct' => (float) ($this->settings->encashment_basic_pct ?? 50),
        ];
    }

    /**
     * A closed year exactly as it was written, in the same shape as preview(),
     * so the screen shows past and pending years with one table.
     */
    public function stored(int $runId): array
    {
        $run = DB::table('leave_year_end_runs')->where('id', $runId)->first();

        $items = DB::table('leave_year_end_items as i')
            ->join('users as u', 'u.id', '=', 'i.user_id')
            ->leftJoin('employee_details as ed', 'ed.user_id', '=', 'i.user_id')
            ->join('leave_types as t', 't.id', '=', 'i.leave_type_id')
            ->where('i.run_id', $runId)
            ->orderBy('u.name')
            ->orderBy('t.id')
            ->get(['i.*', 'u.name', 'ed.employee_id', 'ed.joining_date', 't.type_name', 't.code']);

        $paid = DB::table('leave_encashments')->where('run_id', $runId)
            ->get(['user_id', 'leave_type_id', 'status', 'payslip_id'])
            ->keyBy(fn ($e) => $e->user_id . '|' . $e->leave_type_id);

        $rows = [];

        foreach ($items->groupBy('user_id') as $userId => $group) {
            $first = $group->first();
            $types = [];

            foreach ($group as $i) {
                $ledger = $paid->get($userId . '|' . $i->leave_type_id);

                $types[] = [
                    'leave_type_id' => (int) $i->leave_type_id,
                    'type_name' => $i->type_name,
                    'code' => $i->code,
                    'rule' => $i->rule,
                    'eligible_from' => null,
                    'opening' => (float) $i->opening,
                    'accrued' => (float) $i->accrued,
                    'used' => (float) $i->used,
                    'balance' => (float) $i->balance,
                    'carried' => (float) $i->carried,
                    'lapsed' => (float) $i->lapsed,
                    'encashed_days' => (float) $i->encashed_days,
                    'monthly_base' => null,
                    'rate' => null,
                    'salary_source' => null,
                    'amount' => (float) $i->encashment_amount,
                    'notes' => json_decode((string) $i->notes, true) ?: [],
                    // Whether the money has actually reached a payslip yet.
                    'payout' => $ledger ? ($ledger->status === 'paid' ? 'paid' : 'pending') : null,
                ];
            }

            $rows[] = [
                'user_id' => (int) $userId,
                'name' => $first->name,
                'employee_id' => $first->employee_id,
                'joining_date' => $first->joining_date ? substr((string) $first->joining_date, 0, 10) : null,
                'types' => $types,
                'amount' => array_sum(array_column($types, 'amount')),
            ];
        }

        $summary = json_decode((string) ($run->summary ?? '{}'), true) ?: [];

        return [
            'rows' => $rows,
            'totals' => [
                'employees' => count($rows),
                'carried' => (float) $items->sum('carried'),
                'lapsed' => (float) $items->sum('lapsed'),
                'encashed_days' => (float) $items->sum('encashed_days'),
                'amount' => (float) $items->sum('encashment_amount'),
            ],
            'warnings' => [],
            'settings' => $summary['settings'] ?? $this->settingsSummary(),
            'run' => $run,
        ];
    }

    // ------------------------------------------------------------------
    // Apply
    // ------------------------------------------------------------------

    /**
     * Close the financial year. Once only.
     *
     * The figures are recalculated here, never taken from the screen, so what
     * is written is exactly what the rules produce at the moment of closing.
     *
     * @return array{ok: bool, message: string, run_id: ?int}
     */
    public function apply(Carbon $fyStart, Carbon $fyEnd, string $label, ?int $userId): array
    {
        if (DB::table('leave_year_end_runs')->where('company_id', $this->companyId)->where('financial_year', $label)->exists()) {
            return ['ok' => false, 'message' => "Financial year {$label} has already been closed.", 'run_id' => null];
        }

        $preview = $this->preview($fyStart, $fyEnd);

        $runId = DB::transaction(function () use ($preview, $fyStart, $fyEnd, $label, $userId) {
            $runId = DB::table('leave_year_end_runs')->insertGetId([
                'company_id' => $this->companyId,
                'financial_year' => $label,
                'fy_start' => $fyStart->toDateString(),
                'fy_end' => $fyEnd->toDateString(),
                'summary' => json_encode($preview['totals'] + ['settings' => $preview['settings']]),
                'run_by' => $userId,
                'run_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($preview['rows'] as $employee) {
                foreach ($employee['types'] as $row) {
                    DB::table('leave_year_end_items')->insert([
                        'run_id' => $runId,
                        'user_id' => $employee['user_id'],
                        'leave_type_id' => $row['leave_type_id'],
                        'opening' => $row['opening'],
                        'accrued' => $row['accrued'],
                        'used' => $row['used'],
                        'balance' => $row['balance'],
                        'rule' => $row['rule'],
                        'carried' => $row['carried'],
                        'lapsed' => $row['lapsed'],
                        'encashed_days' => $row['encashed_days'],
                        'encashment_amount' => $row['amount'],
                        'notes' => json_encode($row['notes']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Money owed goes on the ledger for the next payslip.
                    if ($row['amount'] > 0) {
                        DB::table('leave_encashments')->insert([
                            'company_id' => $this->companyId,
                            'user_id' => $employee['user_id'],
                            'leave_type_id' => $row['leave_type_id'],
                            'run_id' => $runId,
                            'financial_year' => $label,
                            'days' => $row['encashed_days'],
                            'basis' => $preview['settings']['basis'],
                            'divisor' => $preview['settings']['divisor'],
                            'monthly_base' => $row['monthly_base'],
                            'rate' => $row['rate'],
                            'amount' => $row['amount'],
                            'status' => 'pending',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // Carried leave goes where the leave module already looks
                    // for it: the quota's carry_forward_leaves, which the quota
                    // recalculation adds to the employee's visible balance.
                    DB::table('employee_leave_quotas')
                        ->where('user_id', $employee['user_id'])
                        ->where('leave_type_id', $row['leave_type_id'])
                        ->update([
                            'carry_forward_leaves' => $row['rule'] === self::RULE_CARRY ? $row['carried'] : 0,
                            'overutilised_leaves' => 0,
                            'updated_at' => now(),
                        ]);
                }
            }

            return $runId;
        });

        // Outside the transaction: the recalculation is the leave module's own
        // command and manages its own writes.
        try {
            Artisan::call('app:recalculate-leaves-quotas ' . $this->companyId);
        } catch (\Throwable $e) {
            // The year is closed either way; the balances catch up on the next
            // recalculation.
            report($e);
        }

        return [
            'ok' => true,
            'message' => sprintf(
                'Financial year %s closed: %s days carried forward, %s lapsed, %s encashed (₹%s) for %d employee(s).',
                $label,
                self::fmt($preview['totals']['carried']),
                self::fmt($preview['totals']['lapsed']),
                self::fmt($preview['totals']['encashed_days']),
                number_format($preview['totals']['amount'], 2),
                $preview['totals']['employees']
            ),
            'run_id' => $runId,
        ];
    }

    // ------------------------------------------------------------------

    /**
     * Round to the half day the way the rest of the leave module does:
     * anything up to .5 becomes .5, anything above becomes the next whole day.
     */
    public static function roundHalf(float $value): float
    {
        $whole = floor($value);
        $fraction = round($value - $whole, 4);

        if ($fraction <= 0) {
            return $whole;
        }

        return $fraction <= 0.5 ? $whole + 0.5 : $whole + 1;
    }

    public static function fmt(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

}
