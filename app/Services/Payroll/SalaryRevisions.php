<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Salary revisions: hikes, promotions and corrections, approved by the CFO and
 * then the CEO, and from then on the pay payroll uses.
 *
 * A batch is an appraisal cycle (many employees) or one employee's revision;
 * it carries the effective date and the approval. Its revisions carry each
 * employee's CTC before and after, and any change of designation / department.
 *
 *   draft / rejected  -> submitted -> pending_cfo -> pending_ceo -> approved
 *
 * The effective date decides the first salary month paid at the new rate: the
 * first pay cycle starting on or after it (effective 15 Nov -> the cycle from
 * 21 Nov). Payroll for any month asks payFor() what was in force then, so an
 * old month re-run is paid at the old rate. When that month arrives the
 * employee's record is updated (applyDue): CTC, Basic %, designation.
 *
 * Approved late - a month already paid at the old rate - the difference is
 * paid as arrears, a separate earning on the next payroll not yet issued.
 */
class SalaryRevisions
{

    public const TYPES = ['appraisal' => 'Annual appraisal', 'promotion' => 'Promotion', 'special' => 'Special increment', 'correction' => 'Correction'];

    public const STATUSES = [
        'draft' => ['Draft', 'secondary'],
        'pending_cfo' => ['Awaiting CFO', 'warning'],
        'pending_ceo' => ['Awaiting CEO', 'warning'],
        'approved' => ['Approved', 'success'],
        'rejected' => ['Sent back', 'danger'],
    ];

    /** Batches that can still change. */
    public const OPEN = ['draft', 'pending_cfo', 'pending_ceo', 'rejected'];

    /** Approved revisions by employee, loaded once per request. */
    protected static ?array $approved = null;

    public static function reset(): void
    {
        static::$approved = null;
    }

    // ------------------------------------------------------------------ dates

    /** The first salary month paid at the new rate: the first cycle starting on or after the date. */
    public static function appliesFrom(Carbon $effective): Carbon
    {
        $cycle = PayPeriod::containing($effective);

        if ($cycle->start->copy()->startOfDay()->lt($effective->copy()->startOfDay())) {
            $cycle = PayPeriod::containing($cycle->end->copy()->addDay());
        }

        return $cycle->start->copy()->startOfDay();
    }

    public static function monthLabel(Carbon $appliesFrom): string
    {
        $cycle = PayPeriod::containing($appliesFrom);

        return Carbon::create($cycle->year, $cycle->month, 1)->format('F Y');
    }

    // ------------------------------------------------------------------- pay

    /**
     * CTC and Basic % in force for a salary month: before the first approved
     * revision still to come, what it replaced; otherwise the employee's record.
     *
     * @return array{0: float, 1: ?float}
     */
    public static function payFor(int $userId, PayPeriod $cycle, float $liveCtc, ?float $liveBasic): array
    {
        if (static::$approved === null) {
            static::$approved = DB::table('salary_revisions as r')->join('salary_revision_batches as b', 'b.id', '=', 'r.batch_id')
                ->where('b.status', 'approved')->orderBy('b.applies_from')->orderBy('r.id')
                ->get(['r.user_id', 'r.old_ctc', 'r.old_basic_pct', 'r.new_ctc', 'r.new_basic_pct', 'r.applied_at', 'b.applies_from'])->groupBy('user_id')->all();
        }

        $start = $cycle->start->copy()->startOfDay();
        $inForce = null;

        foreach (static::$approved[$userId] ?? [] as $r) {
            // The first revision still to come: this month is paid what it replaces.
            if (Carbon::parse($r->applies_from)->gt($start)) {
                return [(float) $r->old_ctc, $r->old_basic_pct !== null ? (float) $r->old_basic_pct : null];
            }

            $inForce = $r;
        }

        // In force but not on the record yet (a month looked at ahead of time): its new pay.
        if ($inForce && !$inForce->applied_at) {
            return [(float) $inForce->new_ctc, $inForce->new_basic_pct !== null ? (float) $inForce->new_basic_pct
                : ($inForce->old_basic_pct !== null ? (float) $inForce->old_basic_pct : $liveBasic)];
        }

        return [$liveCtc, $liveBasic];
    }

    /** A month's pay at a CTC: the full month, nothing lost. */
    public static function monthly(float $ctc, ?float $basicPct): array
    {
        return (new SalaryCalculator())->forEmployee($ctc, $basicPct, 30, 30.0);
    }

    // ------------------------------------------------------------------ access

    public static function canPrepare(): bool
    {
        // The payroll Settings switch (Settings -> Access) decides; approvers see
        // the page for their own step (canView).
        return \App\Services\Access\PayrollAccess::can('settings');
    }

    /** The approval steps this person can act on: cfo, ceo (the Super Admin both). */
    public static function approverLevels(): array
    {
        $roles = user_roles();

        if (in_array('admin', $roles)) {
            return ['cfo', 'ceo'];
        }

        return array_values(array_intersect(['cfo', 'ceo'], $roles));
    }

    public static function canView(): bool
    {
        return static::canPrepare() || static::approverLevels() !== [];
    }

    // ---------------------------------------------------------------- batches

    /**
     * Create or change a draft: its details and its employees, all at once.
     *
     * @param array $rows each: user_id, type, new_ctc, new_basic_pct?, new_designation_id?, new_department_id?, reason?
     */
    public function save(?int $batchId, int $companyId, array $data, array $rows): int
    {
        $batch = $batchId ? DB::table('salary_revision_batches')->find($batchId) : null;

        if ($batch && !in_array($batch->status, ['draft', 'rejected'])) {
            throw new RuntimeException('Only a draft, or one sent back, can be changed.');
        }

        if (!$rows) {
            throw new RuntimeException('Add at least one employee.');
        }

        $effective = Carbon::parse($data['effective_date'])->startOfDay();
        $appliesFrom = static::appliesFrom($effective);
        $errors = [];
        $seen = [];

        foreach ($rows as $i => $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            $name = DB::table('users')->where('id', $userId)->value('name') ?: 'Row ' . ($i + 1);

            if (isset($seen[$userId])) {
                $errors[] = "$name is in the list twice.";
            }

            $seen[$userId] = true;
            $open = DB::table('salary_revisions as r')->join('salary_revision_batches as b', 'b.id', '=', 'r.batch_id')
                ->where('r.user_id', $userId)->whereIn('b.status', self::OPEN)->when($batchId, fn ($q) => $q->where('b.id', '<>', $batchId))
                ->value('b.name');

            if ($open) {
                $errors[] = "$name already has an open revision (\"$open\").";
            }

            if ((float) ($row['new_ctc'] ?? 0) <= 0) {
                $errors[] = "$name: give the new CTC.";
            }
        }

        if ($errors) {
            throw new RuntimeException(implode(' ', array_slice($errors, 0, 6)) . (count($errors) > 6 ? ' (and ' . (count($errors) - 6) . ' more)' : ''));
        }

        return DB::transaction(function () use ($batch, $companyId, $data, $rows, $effective, $appliesFrom) {
            $fields = [
                'company_id' => $companyId, 'name' => trim($data['name']), 'kind' => $data['kind'] ?? 'cycle',
                'effective_date' => $effective->toDateString(), 'applies_from' => $appliesFrom->toDateString(),
                'note' => $data['note'] ?? null, 'updated_at' => now(),
            ];

            if ($batch) {
                DB::table('salary_revision_batches')->where('id', $batch->id)->update($fields);
                $id = (int) $batch->id;
            } else {
                $id = DB::table('salary_revision_batches')->insertGetId($fields + ['status' => 'draft', 'created_by' => user()?->id, 'created_at' => now()]);
                $this->log($id, 'created');
            }

            $keep = [];

            foreach ($rows as $row) {
                $userId = (int) $row['user_id'];
                $before = $this->before($userId, $appliesFrom);
                $values = [
                    'company_id' => $companyId, 'batch_id' => $id, 'user_id' => $userId,
                    'type' => array_key_exists($row['type'] ?? '', self::TYPES) ? $row['type'] : 'appraisal',
                    'old_ctc' => $before['ctc'], 'old_basic_pct' => $before['basic'],
                    'new_ctc' => round((float) $row['new_ctc'], 2),
                    'new_basic_pct' => ($row['new_basic_pct'] ?? '') !== '' ? round((float) $row['new_basic_pct'], 2) : null,
                    'old_designation_id' => $before['designation_id'], 'old_department_id' => $before['department_id'],
                    'new_designation_id' => ($row['new_designation_id'] ?? '') !== '' && (int) $row['new_designation_id'] !== (int) $before['designation_id'] ? (int) $row['new_designation_id'] : null,
                    'new_department_id' => ($row['new_department_id'] ?? '') !== '' && (int) $row['new_department_id'] !== (int) $before['department_id'] ? (int) $row['new_department_id'] : null,
                    'reason' => isset($row['reason']) ? mb_substr(trim((string) $row['reason']), 0, 500) : null,
                    'updated_by' => user()?->id, 'updated_at' => now(),
                ];

                $existing = DB::table('salary_revisions')->where('batch_id', $id)->where('user_id', $userId)->value('id');

                if ($existing) {
                    DB::table('salary_revisions')->where('id', $existing)->update($values);
                    $keep[] = $existing;
                } else {
                    $keep[] = DB::table('salary_revisions')->insertGetId($values + ['created_by' => user()?->id, 'created_at' => now()]);
                }
            }

            DB::table('salary_revisions')->where('batch_id', $id)->whereNotIn('id', $keep)->delete();

            return $id;
        });
    }

    /** HR sends it to the CFO. */
    public function submit(int $batchId): void
    {
        $batch = $this->batch($batchId, ['draft', 'rejected'], 'Only a draft, or one sent back, can be sent for approval.');

        if (!DB::table('salary_revisions')->where('batch_id', $batchId)->exists()) {
            throw new RuntimeException('There is nobody in it.');
        }

        // The pay before the revision, as it stands now.
        $this->refreshBefore($batch);

        DB::table('salary_revision_batches')->where('id', $batchId)->update([
            'status' => 'pending_cfo', 'submitted_at' => now(), 'rejected_by' => null, 'rejected_at' => null, 'reject_reason' => null,
            'cfo_by' => null, 'cfo_at' => null, 'ceo_by' => null, 'ceo_at' => null, 'updated_at' => now(),
        ]);
        $this->log($batchId, 'submitted');
        $this->notifyApprovers($batchId, 'cfo');
    }

    /** Back to draft before anyone has approved it. */
    public function withdraw(int $batchId): void
    {
        $this->batch($batchId, ['pending_cfo'], 'It can be withdrawn only while it waits for the CFO.');
        DB::table('salary_revision_batches')->where('id', $batchId)->update(['status' => 'draft', 'submitted_at' => null, 'updated_at' => now()]);
        $this->log($batchId, 'withdrawn');
    }

    /** The CFO, then the CEO. The CEO's approval makes it final. */
    public function approve(int $batchId, ?string $note = null): string
    {
        $batch = $this->batch($batchId, ['pending_cfo', 'pending_ceo'], 'It is not waiting for an approval.');
        $level = $batch->status === 'pending_cfo' ? 'cfo' : 'ceo';
        $me = user()->id;

        if (!in_array($level, static::approverLevels())) {
            throw new RuntimeException('It is waiting for the ' . strtoupper($level) . '.');
        }

        // Maker and checker are different people (the Super Admin aside).
        if ((int) $batch->created_by === (int) $me && !in_array('admin', user_roles())) {
            throw new RuntimeException('You prepared it, so somebody else has to approve it.');
        }

        if ($level === 'cfo') {
            DB::table('salary_revision_batches')->where('id', $batchId)->update(['status' => 'pending_ceo', 'cfo_by' => $me, 'cfo_at' => now(), 'updated_at' => now()]);
            $this->log($batchId, 'approved_cfo', $note);
            $this->notifyApprovers($batchId, 'ceo');

            return 'Approved; it now goes to the CEO.';
        }

        DB::transaction(function () use ($batch, $batchId, $me, $note) {
            $this->refreshBefore($batch);
            DB::table('salary_revision_batches')->where('id', $batchId)->update(['status' => 'approved', 'ceo_by' => $me, 'ceo_at' => now(), 'updated_at' => now()]);
            $this->log($batchId, 'approved_ceo', $note);
            static::reset();
            $this->numberLetters($batchId);
            $this->arrears($batchId);
        });

        static::reset();
        $this->applyDue();
        $this->notifyPreparer($batchId, true);

        return 'Approved. The new pay applies from ' . static::monthLabel(Carbon::parse($batch->applies_from)) . ' payroll; the letters are ready.';
    }

    public function reject(int $batchId, string $reason): void
    {
        $batch = $this->batch($batchId, ['pending_cfo', 'pending_ceo'], 'It is not waiting for an approval.');
        $level = $batch->status === 'pending_cfo' ? 'cfo' : 'ceo';

        if (!in_array($level, static::approverLevels())) {
            throw new RuntimeException('It is waiting for the ' . strtoupper($level) . '.');
        }

        if (trim($reason) === '') {
            throw new RuntimeException('Say why it is sent back.');
        }

        DB::table('salary_revision_batches')->where('id', $batchId)->update(['status' => 'rejected', 'rejected_by' => user()->id,
            'rejected_at' => now(), 'reject_reason' => $reason, 'updated_at' => now()]);
        $this->log($batchId, 'rejected', $reason);
        $this->notifyPreparer($batchId, false, $reason);
    }

    /** The letters become visible to the employees on their profile. */
    public function release(int $batchId): void
    {
        $this->batch($batchId, ['approved'], 'Letters can be released once it is approved.');
        DB::table('salary_revision_batches')->where('id', $batchId)->update(['released_at' => now(), 'updated_at' => now()]);
        $this->log($batchId, 'released');
    }

    public function delete(int $batchId): void
    {
        $this->batch($batchId, ['draft', 'rejected'], 'Only a draft, or one sent back, can be deleted.');
        DB::transaction(function () use ($batchId) {
            DB::table('salary_revisions')->where('batch_id', $batchId)->delete();
            DB::table('salary_revision_logs')->where('batch_id', $batchId)->delete();
            DB::table('salary_revision_batches')->where('id', $batchId)->delete();
        });
    }

    // ------------------------------------------------------------ applying it

    /**
     * Approved revisions whose first salary month has come: the employee's
     * record now carries the new CTC, Basic % and designation.
     */
    public function applyDue(): int
    {
        $today = PayPeriod::containing(now())->start->toDateString();
        $due = DB::table('salary_revisions as r')->join('salary_revision_batches as b', 'b.id', '=', 'r.batch_id')
            ->where('b.status', 'approved')->whereNull('r.applied_at')->where('b.applies_from', '<=', $today)
            ->orderBy('b.applies_from')->orderBy('r.id')
            ->get(['r.*', 'b.applies_from', 'b.effective_date']);

        foreach ($due as $r) {
            DB::transaction(function () use ($r) {
                $user = ['employee_ctc' => $r->new_ctc, 'updated_at' => now()];

                if ($r->new_basic_pct !== null) {
                    $user['basic_for_payroll'] = $r->new_basic_pct;
                }

                DB::table('users')->where('id', $r->user_id)->update($user);

                if ($r->new_designation_id || $r->new_department_id) {
                    // Through the model, so the employment history follows.
                    $detail = \App\Models\EmployeeDetails::where('user_id', $r->user_id)->first();

                    if ($detail) {
                        $previous = ['designation' => $detail->designation_id, 'department' => $detail->department_id];
                        $detail->designation_id = $r->new_designation_id ?: $detail->designation_id;
                        $detail->department_id = $r->new_department_id ?: $detail->department_id;
                        $detail->save();

                        DB::table('promotions')->insert([
                            'company_id' => $r->company_id, 'employee_id' => $r->user_id, 'date' => $r->effective_date,
                            'previous_designation_id' => $previous['designation'], 'current_designation_id' => $detail->designation_id,
                            'previous_department_id' => $previous['department'], 'current_department_id' => $detail->department_id,
                            'send_notification' => 'no', 'is_send' => 0, 'promotion' => $r->type === 'correction' ? 0 : 1,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }

                DB::table('salary_revisions')->where('id', $r->id)->update(['applied_at' => now()]);
            });
        }

        if ($due->isNotEmpty()) {
            static::reset();
        }

        return $due->count();
    }

    /**
     * Months already paid at the old rate, from the first month of the new one:
     * the difference in what was earned (the days paid that month), paid as one
     * earning on the next payroll not yet issued.
     */
    protected function arrears(int $batchId): void
    {
        $batch = DB::table('salary_revision_batches')->find($batchId);
        $calc = new SalaryCalculator();

        foreach (DB::table('salary_revisions')->where('batch_id', $batchId)->get() as $r) {
            $card = DB::table('employee_details')->where('user_id', $r->user_id)->value('employee_card_id');
            $cycle = PayPeriod::containing(Carbon::parse($batch->applies_from));
            $amount = 0.0;
            $months = [];

            while ($cycle->start->lte(now()) && $card) {
                $slip = DB::table('payslips')->where('emp_id', (string) $card)->where('period', $cycle->label)
                    ->where('status', 'active')->whereNull('deleted_at')->first(['working_days', 'days_present']);

                if (!$slip) {
                    break;
                }

                $wd = (int) round((float) $slip->working_days) ?: $cycle->days();
                $dp = (float) $slip->days_present;
                $earned = fn ($ctc, $basic) => (function ($p) { return $p['gross'] - $p['lop_amount']; })($calc->forEmployee((float) $ctc, $basic !== null ? (float) $basic : null, $wd, $dp));
                $diff = $earned($r->new_ctc, $r->new_basic_pct ?? $r->old_basic_pct) - $earned($r->old_ctc, $r->old_basic_pct);

                if (abs($diff) >= 0.5) {
                    $amount += $diff;
                    $months[] = Carbon::create($cycle->year, $cycle->month, 1)->format('M Y');
                }

                $cycle = PayPeriod::containing($cycle->end->copy()->addDay());
            }

            if ($months === [] || abs($amount) < 1) {
                continue;
            }

            // The next salary month this employee has not been paid for yet.
            $target = $cycle;

            while (DB::table('payslips')->where('emp_id', (string) $card)->where('period', $target->label)->where('status', 'active')->whereNull('deleted_at')->exists()) {
                $target = PayPeriod::containing($target->end->copy()->addDay());
            }

            $label = 'Salary arrears (' . (count($months) > 1 ? $months[0] . ' – ' . end($months) : $months[0]) . ')';
            $componentId = (new PayComponents())->add((int) $r->user_id, $target->start, $amount > 0 ? 'earning' : 'deduction', $label,
                abs(round($amount)), 'Salary revision: ' . $batch->name);

            DB::table('salary_revisions')->where('id', $r->id)->update([
                'arrears_amount' => round($amount), 'arrears_component_id' => $componentId,
                'arrears_note' => 'for ' . (count($months) > 1 ? $months[0] . ' – ' . end($months) : $months[0]) . ', will be '
                    . ($amount > 0 ? 'paid along with' : 'recovered from') . ' your salary for ' . Carbon::create($target->year, $target->month, 1)->format('F Y'),
            ]);
        }
    }

    /** Reference numbers for the letters: prefix / financial year / running number. */
    protected function numberLetters(int $batchId): void
    {
        $settings = $this->settings();
        $date = now();
        $fy = $date->month >= 4 ? $date->year . '-' . substr($date->year + 1, 2) : ($date->year - 1) . '-' . substr($date->year, 2);
        $prefix = trim($settings->letter_prefix ?: 'HR/SR', '/') . '/' . $fy . '/';
        $last = (int) DB::table('salary_revisions')->where('letter_no', 'like', $prefix . '%')
            ->selectRaw('MAX(CAST(SUBSTRING(letter_no, ?) AS UNSIGNED)) AS n', [strlen($prefix) + 1])->value('n');

        $rows = DB::table('salary_revisions as r')->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.batch_id', $batchId)->whereNull('r.letter_no')->orderBy('u.name')->pluck('r.id');

        foreach ($rows as $id) {
            DB::table('salary_revisions')->where('id', $id)->update(['letter_no' => $prefix . str_pad((string) ++$last, 4, '0', STR_PAD_LEFT), 'letter_date' => $date->toDateString()]);
        }
    }

    // ---------------------------------------------------------------- helpers

    /** Pay and position just before the revision's first month. */
    public function before(int $userId, Carbon $appliesFrom): array
    {
        $u = DB::table('users as u')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')->where('u.id', $userId)
            ->first(['u.employee_ctc', 'u.basic_for_payroll', 'ed.designation_id', 'ed.department_id']);
        $live = (float) ($u->employee_ctc ?? 0);
        $basic = isset($u->basic_for_payroll) && $u->basic_for_payroll !== null ? (float) $u->basic_for_payroll : null;
        [$ctc, $basicPct] = static::payFor($userId, PayPeriod::containing($appliesFrom->copy()->subDay()), $live, $basic);

        // A revision approved earlier but not yet in force, falling before this one, is what this one builds on.
        $pending = DB::table('salary_revisions as r')->join('salary_revision_batches as b', 'b.id', '=', 'r.batch_id')
            ->where('r.user_id', $userId)->where('b.status', 'approved')->whereNull('r.applied_at')
            ->where('b.applies_from', '<', $appliesFrom->toDateString())->orderByDesc('b.applies_from')->first(['r.new_ctc', 'r.new_basic_pct', 'r.new_designation_id', 'r.new_department_id']);

        return [
            'ctc' => $pending ? (float) $pending->new_ctc : $ctc,
            'basic' => $pending && $pending->new_basic_pct !== null ? (float) $pending->new_basic_pct : $basicPct,
            'designation_id' => $pending && $pending->new_designation_id ? $pending->new_designation_id : ($u->designation_id ?? null),
            'department_id' => $pending && $pending->new_department_id ? $pending->new_department_id : ($u->department_id ?? null),
        ];
    }

    protected function refreshBefore(object $batch): void
    {
        $appliesFrom = Carbon::parse($batch->applies_from);

        foreach (DB::table('salary_revisions')->where('batch_id', $batch->id)->get() as $r) {
            $b = $this->before((int) $r->user_id, $appliesFrom);
            DB::table('salary_revisions')->where('id', $r->id)->update([
                'old_ctc' => $b['ctc'], 'old_basic_pct' => $b['basic'],
                'old_designation_id' => $b['designation_id'], 'old_department_id' => $b['department_id'],
            ]);
        }
    }

    public function settings(?int $companyId = null): object
    {
        $companyId = $companyId ?? company()?->id;
        $row = DB::table('salary_revision_settings')->where('company_id', $companyId)->first();

        if (!$row) {
            DB::table('salary_revision_settings')->insert(['company_id' => $companyId, 'letter_prefix' => 'HR/SR',
                'signatory_designation' => 'Head - Human Resources', 'created_at' => now(), 'updated_at' => now()]);
            $row = DB::table('salary_revision_settings')->where('company_id', $companyId)->first();
        }

        return $row;
    }

    protected function batch(int $id, array $statuses, string $message): object
    {
        $batch = DB::table('salary_revision_batches')->find($id);

        if (!$batch) {
            throw new RuntimeException('That revision is no longer there.');
        }

        if (!in_array($batch->status, $statuses)) {
            throw new RuntimeException($message);
        }

        return $batch;
    }

    protected function log(int $batchId, string $action, ?string $note = null): void
    {
        DB::table('salary_revision_logs')->insert(['batch_id' => $batchId, 'action' => $action, 'user_id' => user()?->id, 'note' => $note, 'created_at' => now()]);
    }

    /** The bell for whoever approves next (no e-mail). */
    protected function notifyApprovers(int $batchId, string $level): void
    {
        $batch = DB::table('salary_revision_batches')->find($batchId);
        $users = \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', $level))->where('status', 'active')->get();

        foreach ($users as $user) {
            $user->notify(new \App\Notifications\SalaryRevisionUpdate($batch, 'pending', strtoupper($level)));
        }
    }

    protected function notifyPreparer(int $batchId, bool $approved, ?string $reason = null): void
    {
        $batch = DB::table('salary_revision_batches')->find($batchId);
        $user = $batch->created_by ? \App\Models\User::find($batch->created_by) : null;

        if ($user && $user->id !== user()?->id) {
            $user->notify(new \App\Notifications\SalaryRevisionUpdate($batch, $approved ? 'approved' : 'rejected', $reason));
        }
    }

}
