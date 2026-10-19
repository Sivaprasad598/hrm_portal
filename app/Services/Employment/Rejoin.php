<?php

namespace App\Services\Employment;

use App\Models\Role;
use App\Models\RoleUser;
use App\Models\User;
use App\Services\Attendance\ShiftAssignments;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Bringing a former employee back, as the same employee.
 *
 * A rejoin is collected on its own form and saved as a draft, then confirmed.
 * Confirming, in one transaction:
 *   - closes the employment period that ended and opens a new one from the
 *     rejoining date, with the new terms (EmploymentPeriods)
 *   - updates the employee record: joining date, no exit or notice dates,
 *     codes, designation, department, branch, manager, employment type,
 *     CTC, Basic %, probation; the role; the account and sign-in back on
 *   - marks the old resignation / termination / exit records as closed by
 *     this rejoin, so they no longer close the account
 *   - starts the shift from the rejoining date
 *   - links the biometric code
 *   - starts leave afresh: allowances from the rejoining date, requests still
 *     pending from the old employment cancelled
 *
 * The new period collects its own joining documents (checklist, declaration,
 * resume) on Edit Employee, as a new joiner would.
 */
class Rejoin
{
    public const DRAFT = 'draft';
    public const CONFIRMED = 'confirmed';
    public const CANCELLED = 'cancelled';

    public function __construct(protected EmploymentPeriods $periods)
    {
    }

    /**
     * Whether this person can be rejoined now.
     *
     * @return array{blockers: array<int, string>, warnings: array<int, string>, period: ?object, settlement: ?object}
     */
    public function check(int $userId): array
    {
        $blockers = [];
        $warnings = [];
        $period = $this->periods->ensure($userId);
        $detail = DB::table('employee_details')->where('user_id', $userId)->first(['last_date', 'notice_period_end_date']);
        $leftOn = $period->left_on ?? ($detail->last_date ?? null) ?? ($detail->notice_period_end_date ?? null);

        if (!$leftOn) {
            $blockers[] = 'This employee has not left - there is no exit date on record. Only someone who has left can rejoin.';
        } elseif (substr((string) $leftOn, 0, 10) >= now()->toDateString()) {
            $blockers[] = 'Their last working day (' . Carbon::parse($leftOn)->format('d M Y') . ') has not passed yet. '
                . 'To keep them, withdraw the resignation instead.';
        }

        // The final settlement of the employment that ended must be finished first.
        $settlement = DB::table('settlements')->where('user_id', $userId)
            ->where(fn ($q) => $q->where('employment_period_id', $period->id)->orWhereNull('employment_period_id'))
            ->orderByDesc('id')->first(['id', 'status', 'net_payable', 'last_working_day', 'paid_at']);

        if ($settlement && in_array($settlement->status, ['draft', 'agreed'], true)) {
            $blockers[] = 'The final settlement for the last employment is still ' . $settlement->status
                . '. Pay it or cancel it under Payroll -> Settlements before the rejoin.';
        } elseif (!$settlement && $leftOn) {
            $warnings[] = 'No final settlement was made for the last employment. Any salary still owed for it should be settled separately.';
        }

        if ($period && (string) $period->eligible_for_rehire === '0') {
            $warnings[] = 'Marked not eligible for rehire' . ($period->not_eligible_reason ? ': ' . $period->not_eligible_reason : '')
                . '. A reason is needed to go ahead.';
        }

        $draft = DB::table('employee_rejoins')->where('user_id', $userId)->where('status', self::DRAFT)->exists();

        if ($draft) {
            $warnings[] = 'A rejoin draft is already open for this employee - it is shown below.';
        }

        return ['blockers' => $blockers, 'warnings' => $warnings, 'period' => $period, 'settlement' => $settlement];
    }

    /** The open draft, if any. */
    public function draftFor(int $userId): ?object
    {
        return DB::table('employee_rejoins')->where('user_id', $userId)->where('status', self::DRAFT)->orderByDesc('id')->first();
    }

    /** Save the form as a draft (new or the open one). @return int draft id */
    public function saveDraft(int $userId, array $data, int $by): int
    {
        $check = $this->check($userId);
        $period = $check['period'];
        $leftOn = $period->left_on ? Carbon::parse($period->left_on) : null;
        $rejoinOn = Carbon::parse($data['rejoin_date']);

        $values = array_merge($this->pick($data), [
            'company_id' => DB::table('users')->where('id', $userId)->value('company_id'),
            'user_id' => $userId,
            'previous_period_id' => $period->id,
            'rejoin_date' => $rejoinOn->toDateString(),
            'break_days' => $leftOn ? max(0, $leftOn->diffInDays($rejoinOn) - 1) : null,
            'updated_at' => now(),
        ]);

        $draft = $this->draftFor($userId);

        if ($draft) {
            DB::table('employee_rejoins')->where('id', $draft->id)->update($values);

            return (int) $draft->id;
        }

        return (int) DB::table('employee_rejoins')->insertGetId($values + [
            'status' => self::DRAFT, 'created_by' => $by, 'created_at' => now(),
        ]);
    }

    /** Confirm a draft: the employee is back from the rejoining date. */
    public function confirm(int $rejoinId, int $by): void
    {
        $rejoin = DB::table('employee_rejoins')->find($rejoinId);

        if (!$rejoin || $rejoin->status !== self::DRAFT) {
            throw new \RuntimeException('Only a draft rejoin can be confirmed.');
        }

        $check = $this->check((int) $rejoin->user_id);

        if ($check['blockers']) {
            throw new \RuntimeException(implode(' ', $check['blockers']));
        }

        $period = $check['period'];

        if ($period->left_on && $rejoin->rejoin_date <= substr((string) $period->left_on, 0, 10)) {
            throw new \RuntimeException('The rejoining date must be after the last working day ('
                . Carbon::parse($period->left_on)->format('d M Y') . ').');
        }

        if ((string) $period->eligible_for_rehire === '0' && !trim((string) $rejoin->rehire_override_reason)) {
            throw new \RuntimeException('This employee was marked not eligible for rehire. Give the reason for rehiring.');
        }

        DB::transaction(function () use ($rejoin, $period, $check, $by) {
            $statusBefore = DB::table('employee_details')->where('user_id', $rejoin->user_id)->value('employment_status');

            $userId = (int) $rejoin->user_id;
            $user = User::withoutGlobalScopes()->findOrFail($userId);
            $detail = DB::table('employee_details')->where('user_id', $userId)->first();

            // ---- the period that ended: its exit and settlement on record
            $resignation = DB::table('resignations')->where('user_id', $userId)->whereNull('closed_by_rejoin_id')
                ->orderByDesc('id')->first(['id', 'reason']);
            $termination = DB::table('terminations')->where('user_id', $userId)->whereNull('closed_by_rejoin_id')
                ->orderByDesc('id')->first(['id', 'reason']);
            $exit = DB::table('employee_exits')->where('user_id', $userId)->whereNull('closed_by_rejoin_id')
                ->orderByDesc('id')->first(['id', 'exit_type', 'exit_reason']);

            DB::table('employment_periods')->where('id', $period->id)->update([
                'left_on' => $period->left_on ?: substr((string) ($detail->last_date ?? $detail->notice_period_end_date), 0, 10),
                'exit_type' => $period->exit_type ?: ($termination ? 'terminated' : ($resignation ? 'resigned' : ($exit->exit_type ?? 'other'))),
                'exit_reason' => $period->exit_reason ?: ($termination->reason ?? $resignation->reason ?? $exit->exit_reason ?? null),
                'resignation_id' => $period->resignation_id ?: ($resignation->id ?? null),
                'termination_id' => $period->termination_id ?: ($termination->id ?? null),
                'employee_exit_id' => $period->employee_exit_id ?: ($exit->id ?? null),
                'settlement_id' => $period->settlement_id ?: ($check['settlement']->id ?? null),
                'updated_at' => now(),
            ]);

            if ($check['settlement']) {
                DB::table('settlements')->where('id', $check['settlement']->id)->update(['employment_period_id' => $period->id]);
            }

            foreach (['resignations', 'terminations', 'employee_exits'] as $table) {
                DB::table($table)->where('user_id', $userId)->whereNull('closed_by_rejoin_id')
                    ->update(['closed_by_rejoin_id' => $rejoin->id]);
            }

            // ---- the joining checklist and declaration of the employment that
            // ended are kept with it; the new employment starts them empty and
            // collects them again (Edit Employee).
            $checklist = DB::table('emp_joining_checklists')->where('user_id', $userId)->first();
            $declaration = DB::table('emp_final_declaration')->where('user_id', $userId)->orderByDesc('id')->first();
            DB::table('employment_periods')->where('id', $period->id)->update([
                'joining_snapshot' => json_encode(['checklist' => $checklist, 'declaration' => $declaration]),
            ]);
            DB::table('emp_joining_checklists')->where('user_id', $userId)->update(array_fill_keys(
                array_keys(\App\Services\Employee\OnboardingDocuments::CHECKLIST), 0) + ['updated_at' => now()]);
            DB::table('emp_final_declaration')->where('user_id', $userId)->delete();

            // ---- the new period
            $newPeriodId = DB::table('employment_periods')->insertGetId([
                'company_id' => $rejoin->company_id, 'user_id' => $userId, 'period_no' => $period->period_no + 1,
                'joined_on' => $rejoin->rejoin_date, 'rejoin_id' => $rejoin->id,
                'employee_code' => $rejoin->employee_code ?: $detail->employee_id,
                'employee_card_id' => $rejoin->employee_card_id ?: $detail->employee_card_id,
                'designation_id' => $rejoin->designation_id ?: $detail->designation_id,
                'department_id' => $rejoin->department_id ?: $detail->department_id,
                'branch_id' => $rejoin->branch_id ?: $detail->branch_id,
                'employee_ctc' => $rejoin->employee_ctc ?? $user->employee_ctc,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            // ---- the employee record: the new employment
            DB::table('employee_details')->where('user_id', $userId)->update(array_filter([
                'employee_id' => $rejoin->employee_code,
                'employee_card_id' => $rejoin->employee_card_id,
                'designation_id' => $rejoin->designation_id,
                'department_id' => $rejoin->department_id,
                'branch_id' => $rejoin->branch_id,
                'reporting_to' => $rejoin->reporting_to,
                'employment_type' => $rejoin->employment_type,
                'iapp_emp_code' => $rejoin->iapp_emp_code,
            ], fn ($v) => $v !== null && $v !== '') + [
                'joining_date' => $rejoin->rejoin_date,
                'last_date' => null,
                'notice_period_start_date' => null,
                'notice_period_end_date' => null,
                'employment_status' => $rejoin->probation_applies ? 'probation' : 'confirmed',
                'probation_end_date' => $rejoin->probation_applies ? $rejoin->probation_end_date : null,
                'probation_notified_at' => null,
                'probation_review_status' => null,
                'probation_admin_remarks' => null,
                'probation_reviewed_by' => null,
                'probation_reviewed_at' => null,
                'updated_at' => now(),
            ]);

            $ctc = $rejoin->employee_ctc !== null ? (float) $rejoin->employee_ctc : $user->employee_ctc;
            DB::table('users')->where('id', $userId)->update([
                'status' => 'active', 'login' => 'enable', 'inactive_date' => null,
                'employee_ctc' => $ctc,
                'monthly_salary' => $ctc !== null ? round((float) $ctc / 12, 2) : null,
                'basic_for_payroll' => $rejoin->basic_for_payroll,
                'updated_at' => now(),
            ]);

            // ---- the role
            if ($rejoin->role_id) {
                $employeeRole = Role::where('name', 'employee')->first();
                RoleUser::where('user_id', $userId)->delete();
                $user->roles()->attach($employeeRole->id);

                if ((int) $employeeRole->id !== (int) $rejoin->role_id) {
                    $user->roles()->attach($rejoin->role_id);
                }

                $user->assignUserRolePermission($rejoin->role_id);
            }

            // ---- biometric: the code on the device points at this employee
            if ($rejoin->iapp_emp_code) {
                DB::table('iapp_employees')->where('emp_code', $rejoin->iapp_emp_code)
                    ->update(['user_id' => $userId, 'map_status' => 'mapped', 'updated_at' => now()]);
            }

            // ---- leave starts afresh: the old allowances (and anything carried
            // forward in them) go, and are worked out again from the rejoining date
            // after this transaction; requests still pending from the old employment go
            DB::table('employee_leave_quotas')->where('user_id', $userId)->delete();

            DB::table('leaves')->where('user_id', $userId)->where('status', 'pending')
                ->where('leave_date', '<', $rejoin->rejoin_date)
                ->update(['status' => 'rejected', 'reject_reason' => 'Employment ended before this leave; the employee has since rejoined.',
                    'updated_at' => now()]);

            // The Exit History shows the whole story: each exit, and each return.
            DB::table('employee_exit_histories')->insert([
                'user_id' => $rejoin->user_id,
                'company_id' => $rejoin->company_id,
                'previous_status' => $statusBefore ?: 'off_boarded',
                'new_status' => $rejoin->probation_applies ? 'probation' : 'confirmed',
                'action' => 'Rejoined on ' . Carbon::parse($rejoin->rejoin_date)->format('d M Y')
                    . ' (employment #' . ((int) $period->period_no + 1) . ')',
                'changed_by' => $by,
                'notes' => $rejoin->rejoin_reason ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            DB::table('employee_rejoins')->where('id', $rejoin->id)->update([
                'status' => self::CONFIRMED, 'new_period_id' => $newPeriodId,
                'confirmed_by' => $by, 'confirmed_at' => now(), 'updated_at' => now(),
            ]);
        });

        // After the transaction: the shift from the rejoining date, and leave
        // allowances worked out again from the new joining date.
        app(ShiftAssignments::class)->assign((int) $rejoin->user_id,
            $rejoin->employee_shift_id ? (int) $rejoin->employee_shift_id : null, $rejoin->rejoin_date, $by, 'Rejoined');

        try {
            Artisan::call('app:recalculate-leaves-quotas ' . (int) $rejoin->company_id . ' ' . (int) $rejoin->user_id);
        } catch (\Throwable $e) {
            logger()->warning('Leave allowances not recalculated after rejoin', ['user' => $rejoin->user_id, 'error' => $e->getMessage()]);
        }
    }

    public function cancel(int $rejoinId, int $by): void
    {
        DB::table('employee_rejoins')->where('id', $rejoinId)->where('status', self::DRAFT)->update([
            'status' => self::CANCELLED, 'cancelled_by' => $by, 'cancelled_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Every rejoin on record for an employee, newest first. */
    public function history(int $userId)
    {
        return DB::table('employee_rejoins as r')
            ->leftJoin('users as c', 'c.id', '=', 'r.confirmed_by')
            ->where('r.user_id', $userId)->orderByDesc('r.id')
            ->get(['r.*', 'c.name as confirmed_by_name']);
    }

    /** The form fields a rejoin keeps. */
    protected function pick(array $data): array
    {
        $fields = ['rejoin_reason', 'notes', 'employee_code', 'employee_card_id', 'iapp_emp_code', 'designation_id', 'department_id',
            'branch_id', 'reporting_to', 'role_id', 'employment_type', 'employee_ctc', 'basic_for_payroll', 'employee_shift_id',
            'probation_months', 'probation_end_date', 'skip_probation_reason', 'rehire_override_reason'];

        $out = [];

        foreach ($fields as $field) {
            $value = $data[$field] ?? null;
            $out[$field] = ($value === '' ? null : $value);
        }

        $out['probation_applies'] = !empty($data['probation_applies']);

        if (!$out['probation_applies']) {
            $out['probation_end_date'] = null;
            $out['probation_months'] = null;
        }

        return $out;
    }
}
