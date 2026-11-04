<?php

namespace App\Services\Leaves;

use App\Models\Leave;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Leave approval in steps (Leave Settings -> General -> Approval steps):
 *
 *   manager          the top manager above the applicant: up the Reporting To chain
 *                    (employee -> team lead -> manager -> ...), the highest active person
 *                    with the Manager role. Team leads are passed by; no manager in the
 *                    chain -> the step is passed over.
 *   ceo              a manager's own leave: the CEO in their chain, or any active CEO.
 *                    Runs with the manager step (one or the other, never both).
 *   department_head  the head of the applicant's department
 *   hr               anyone with Approve / Reject Leaves (HR, Admin)
 *
 * The ticked steps run in that order. A step with nobody for it - no manager,
 * no head, or only the applicant themselves (a department head's own leave) -
 * is passed over, as is a step whose approver already approved it at an
 * earlier one. The leave counts (balance, payroll) only once the last step
 * approves it; a rejection at any step ends it.
 *
 * HR may approve ahead of the manager / head, with a note. A step left
 * undecided for escalate_after_days moves on to the next.
 *
 * A multi-day application (one unique_id) moves through the steps together.
 */
class LeaveApprovals
{

    public const STEPS = ['manager' => 'Manager', 'ceo' => 'CEO', 'department_head' => 'Department head', 'hr' => 'HR'];

    public const DONE = 'done';

    protected static ?object $settings = null;

    /** user_id => [reporting_to, role names, active], loaded once per request (people()). */
    protected static ?array $people = null;

    public static function settings(): object
    {
        return static::$settings ??= (DB::table('leave_settings')->where('company_id', company()?->id)->first()
            ?? DB::table('leave_settings')->first() ?? (object) ['approval_levels' => null, 'escalate_after_days' => 2]);
    }

    public static function reset(): void
    {
        static::$settings = null;
        static::$people = null;
    }

    /** Everyone's place in the hierarchy: user_id => [reporting_to, role names, active]. */
    protected static function people(): array
    {
        if (static::$people === null) {
            $roles = DB::table('role_user as ru')->join('roles as r', 'r.id', '=', 'ru.role_id')->get(['ru.user_id', 'r.name'])
                ->groupBy('user_id')->map(fn ($rows) => $rows->pluck('name')->all());

            static::$people = DB::table('employee_details as e')->join('users as u', 'u.id', '=', 'e.user_id')
                ->get(['e.user_id', 'e.reporting_to', 'u.status'])
                ->mapWithKeys(fn ($r) => [(int) $r->user_id => [(int) $r->reporting_to, $roles->get($r->user_id, []), $r->status === 'active']])
                ->all();
        }

        return static::$people;
    }

    protected static function hasRole(int $userId, string $role): bool
    {
        return in_array($role, static::people()[$userId][1] ?? [], true);
    }

    /** The highest active person with $role up the applicant's Reporting To chain (loops guarded). */
    protected static function topInChain(int $applicantId, string $role): ?int
    {
        $people = static::people();
        $found = null;
        $seen = [$applicantId => true];
        $id = $people[$applicantId][0] ?? 0;

        while ($id && !isset($seen[$id]) && isset($people[$id])) {
            $seen[$id] = true;
            [$up, $roles, $active] = $people[$id];

            if ($active && in_array($role, $roles, true)) {
                $found = $id;
            }

            $id = $up;
        }

        return $found;
    }

    /** The manager who approves this employee's leave; null for a manager or CEO themselves, or no manager above. */
    public static function managerFor(int $applicantId): ?int
    {
        if (static::hasRole($applicantId, 'manager') || static::hasRole($applicantId, 'ceo')) {
            return null;
        }

        return static::topInChain($applicantId, 'manager');
    }

    /** Who approves a manager's own leave: the CEO in their chain, or else every active CEO. */
    public static function ceoFor(int $applicantId): array
    {
        if (!static::hasRole($applicantId, 'manager') || static::hasRole($applicantId, 'ceo')) {
            return [];
        }

        if ($ceo = static::topInChain($applicantId, 'ceo')) {
            return [$ceo];
        }

        return array_keys(array_filter(static::people(), fn ($p) => $p[2] && in_array('ceo', $p[1], true)));
    }

    /** The ticked steps, in order (HR alone when none is ticked). */
    public static function chain(): array
    {
        $ticked = json_decode((string) (static::settings()->approval_levels ?? ''), true) ?: [];
        // The CEO step goes with the manager step: a manager's own leave goes to the CEO
        if (in_array('manager', $ticked, true)) {
            $ticked[] = 'ceo';
        }

        $chain = array_values(array_filter(array_keys(self::STEPS), fn ($s) => in_array($s, $ticked, true)));

        return $chain ?: ['hr'];
    }

    public static function label(?string $step): string
    {
        return self::STEPS[$step] ?? '';
    }

    /** The step's approver, as said in a sentence: "the reporting manager", "the HR". */
    public static function who(?string $step): string
    {
        return $step === 'hr' ? 'HR' : strtolower(self::STEPS[$step] ?? '');
    }

    // ------------------------------------------------------------ who approves

    /** The people who decide a step for an applicant; [] when nobody (the step is passed over). */
    public static function approvers(string $step, int $applicantId): array
    {
        $ids = match ($step) {
            'manager' => array_filter([(int) static::managerFor($applicantId)]),
            'ceo' => static::ceoFor($applicantId),
            'department_head' => array_filter([(int) DB::table('employee_details as ed')->join('teams as t', 't.id', '=', 'ed.department_id')
                ->where('ed.user_id', $applicantId)->value('t.head_user_id')]),
            'hr' => static::hrIds(),
            default => [],
        };

        $active = DB::table('users')->whereIn('id', $ids)->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_diff($active, [$applicantId]));
    }

    /** Everyone who may approve or reject any leave (HR, Admin). */
    public static function hrIds(): array
    {
        return DB::table('user_permissions as up')->join('permissions as p', 'p.id', '=', 'up.permission_id')
            ->join('permission_types as t', 't.id', '=', 'up.permission_type_id')
            ->where('p.name', 'approve_or_reject_leaves')->where('t.name', 'all')->pluck('up.user_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** The next step after $after with somebody for it who has not approved it already; null when none is left. */
    public static function nextStep(int $applicantId, ?string $after, array $alreadyApproved = []): ?string
    {
        $chain = static::chain();
        $start = $after === null ? 0 : array_search($after, $chain, true) + 1;

        foreach (array_slice($chain, (int) $start) as $step) {
            if (array_diff(static::approvers($step, $applicantId), $alreadyApproved)) {
                return $step;
            }
        }

        return null;
    }

    /** The leave and the others applied with it (one multi-day application). */
    public static function group(Leave $leave): Collection
    {
        return $leave->unique_id
            ? Leave::where('unique_id', $leave->unique_id)->get()
            : collect([$leave]);
    }

    // ---------------------------------------------------------------- flow

    /** A new application waits at its first step. */
    public static function start(array $leaveIds): void
    {
        $leaves = Leave::whereIn('id', $leaveIds)->where('status', 'pending')->get();
        $first = $leaves->first();

        if (!$first) {
            return;
        }

        $step = static::nextStep((int) $first->user_id, null) ?? 'hr';
        Leave::whereIn('id', $leaves->pluck('id'))->update(['approval_step' => $step, 'approval_step_at' => now()]);
        static::log($first, $step, 'submitted', (int) $first->user_id);
        static::notifyStep($first->fresh(), $step);
    }

    /**
     * What this person may do on this leave:
     *   step      decide it - they are the approver of the step it waits at
     *   override  approve it ahead of the manager / head (HR, with a note)
     *   null      nothing
     */
    public static function canAct(Leave $leave, ?int $userId = null): ?string
    {
        $userId ??= (int) user()?->id;

        if ($leave->status !== 'pending' || !$leave->approval_step || $leave->approval_step === self::DONE || (int) $leave->user_id === $userId) {
            return null;
        }

        if (in_array($userId, static::approvers($leave->approval_step, (int) $leave->user_id), true)) {
            return 'step';
        }

        $isHr = in_array($userId, static::hrIds(), true) || in_array('admin', DB::table('role_user as ru')->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->where('ru.user_id', $userId)->pluck('r.name')->all(), true);

        return $isHr ? 'override' : null;
    }

    /** Whether the person decides some step of somebody's leave (they see their team's leave). */
    public static function isApprover(?int $userId = null): bool
    {
        $userId ??= (int) user()?->id;

        return DB::table('employee_details')->where('reporting_to', $userId)->exists()
            || DB::table('teams')->where('head_user_id', $userId)->exists()
            || static::hasRole($userId, 'manager') || static::hasRole($userId, 'ceo');
    }

    /** The people whose leave this person sees or approves: direct reports, their department, and as manager / CEO. */
    public static function teamUserIds(?int $userId = null): array
    {
        $userId ??= (int) user()?->id;
        $heads = DB::table('teams')->where('head_user_id', $userId)->pluck('id');

        // Direct reports (a team lead sees their team), the department they head, and
        // everyone further down whose leave comes to them as the top manager
        $ids = DB::table('employee_details')->where('reporting_to', $userId)
            ->orWhereIn('department_id', $heads)->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        if (static::hasRole($userId, 'manager') || static::hasRole($userId, 'ceo')) {
            foreach (array_keys(static::people()) as $employeeId) {
                if ($employeeId !== $userId
                    && (static::managerFor($employeeId) === $userId || in_array($userId, static::ceoFor($employeeId), true))) {
                    $ids[] = $employeeId;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Approve at the current step. Returns 'final' when this was the last step
     * (the caller then approves it as before: status, balance, notifications),
     * or 'advanced' when it moves on to the next step.
     */
    public static function approve(Leave $leave, ?string $note = null, bool $override = false): string
    {
        $mode = static::canAct($leave);

        if ((int) $leave->user_id === (int) user()?->id) {
            throw new RuntimeException('Your own leave is approved by somebody else.');
        }

        if (!$mode || ($mode === 'override' && !$override)) {
            throw new RuntimeException('This leave is waiting for the ' . static::who($leave->approval_step) . '.');
        }

        $step = $leave->approval_step;

        if ($mode === 'override') {
            if (trim((string) $note) === '') {
                throw new RuntimeException('Give a note for approving before the ' . static::who($step) . '.');
            }

            static::guardFinal($leave);
            static::log($leave, $step, 'override', null, $note);
            static::finish($leave, 'approved');

            return 'final';
        }

        $approvedBy = DB::table('leave_approvals')->where(static::groupKey($leave))->where('action', 'approved')->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $next = static::nextStep((int) $leave->user_id, $step, array_merge($approvedBy, [(int) user()?->id]));

        if ($next === null) {
            static::guardFinal($leave);
            static::log($leave, $step, 'approved', null, $note);
            static::finish($leave, 'approved');

            return 'final';
        }

        static::log($leave, $step, 'approved', null, $note);

        static::moveTo($leave, $next);
        static::notifyApplicant($leave, 'approved_step', static::label($step) . ' approved; now with ' . static::label($next) . '.');

        return 'advanced';
    }

    public static function reject(Leave $leave, ?string $reason = null): void
    {
        if ((int) $leave->user_id === (int) user()?->id) {
            throw new RuntimeException('Your own leave is decided by somebody else; withdraw it instead.');
        }

        if (!static::canAct($leave)) {
            throw new RuntimeException('This leave is waiting for the ' . static::who($leave->approval_step) . '.');
        }

        static::log($leave, $leave->approval_step, 'rejected', null, $reason);
        static::finish($leave, 'rejected');
    }

    /** Pending leaves waiting at a step this person decides (not HR's approving ahead), oldest first. */
    public static function waitingFor(?int $userId = null): Collection
    {
        $userId ??= (int) user()?->id;

        return Leave::with('user:id,name,image', 'type:id,type_name,color')
            ->where('status', 'pending')->whereNotNull('approval_step')->where('approval_step', '!=', self::DONE)
            ->where('user_id', '!=', $userId)->orderBy('leave_date')->get()
            ->filter(fn (Leave $leave) => in_array($userId, static::approvers($leave->approval_step, (int) $leave->user_id), true))
            ->values();
    }

    /** Steps left undecided too long move on to the next. @return int applications moved */
    public static function escalateDue(): int
    {
        $days = (int) (static::settings()->escalate_after_days ?? 0);

        if ($days <= 0) {
            return 0;
        }

        $moved = 0;
        $due = Leave::where('status', 'pending')->whereIn('approval_step', ['manager', 'department_head'])
            ->where('approval_step_at', '<=', now()->subDays($days))->get()
            ->unique(fn ($l) => $l->unique_id ?: 'id' . $l->id);

        foreach ($due as $leave) {
            $next = static::nextStep((int) $leave->user_id, $leave->approval_step);

            if ($next === null) {
                continue;
            }

            static::log($leave, $leave->approval_step, 'escalated', 0,
                'No decision in ' . $days . ' day(s): moved on to ' . static::label($next) . '.');
            static::moveTo($leave, $next);
            $moved++;
        }

        return $moved;
    }

    /** Who did what, oldest first. */
    public static function trail(Leave $leave): Collection
    {
        return DB::table('leave_approvals as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->where(static::groupKey($leave, 'a.'))->orderBy('a.id')->get(['a.*', 'u.name as user_name']);
    }

    // ------------------------------------------------------------- helpers

    /** The final approval waits for a document the leave type needs (Leave Settings -> Rules). */
    protected static function guardFinal(Leave $leave): void
    {
        if ($awaited = LeaveRules::documentAwaited($leave)) {
            throw new RuntimeException($awaited);
        }
    }

    protected static function finish(Leave $leave, ?string $outcome = null): void
    {
        Leave::whereIn('id', static::group($leave)->pluck('id'))->update(['approval_step' => self::DONE]);

        if ($outcome) {
            static::notifyEarlierApprovers($leave, $outcome);
        }
    }

    /** Those who approved an earlier step (the reporting manager...) hear how it ended. */
    protected static function notifyEarlierApprovers(Leave $leave, string $outcome): void
    {
        $ids = DB::table('leave_approvals')->where(static::groupKey($leave))->where('action', 'approved')
            ->whereNotNull('user_id')->pluck('user_id')->map(fn ($id) => (int) $id)->unique()
            ->reject(fn ($id) => $id === (int) user()?->id || $id === (int) $leave->user_id);

        $detail = ($outcome === 'approved' ? 'approved' : 'rejected') . ' by ' . (user()?->name ?: 'the approver') . '.';

        foreach (\App\Models\User::whereIn('id', $ids)->get() as $user) {
            $user->notify(new \App\Notifications\LeaveApprovalUpdate($leave, 'decided', $detail));
        }
    }

    protected static function moveTo(Leave $leave, string $step): void
    {
        Leave::whereIn('id', static::group($leave)->where('status', 'pending')->pluck('id'))->update(['approval_step' => $step, 'approval_step_at' => now()]);
        static::notifyStep($leave->fresh(), $step);
    }

    protected static function groupKey(Leave $leave, string $prefix = ''): \Closure
    {
        return fn ($q) => $leave->unique_id ? $q->where($prefix . 'unique_id', $leave->unique_id) : $q->where($prefix . 'leave_id', $leave->id);
    }

    protected static function log(Leave $leave, string $step, string $action, ?int $userId = null, ?string $note = null): void
    {
        DB::table('leave_approvals')->insert([
            'leave_id' => $leave->id, 'unique_id' => $leave->unique_id, 'step' => $step, 'action' => $action,
            'user_id' => $userId === 0 ? null : ($userId ?? user()?->id), 'note' => $note !== null ? mb_substr(trim($note), 0, 500) : null, 'created_at' => now(),
        ]);
    }

    /** The bell for the step's approvers (not email). */
    protected static function notifyStep(Leave $leave, string $step): void
    {
        $ids = static::approvers($step, (int) $leave->user_id);

        // The HR step: anyone with Approve Leaves may decide it, but the bell rings for
        // the HR team only (it rang for every admin and the CEOs too, ~15 people a leave)
        if ($step === 'hr') {
            $hrTeam = DB::table('role_user as ru')->join('roles as r', 'r.id', '=', 'ru.role_id')
                ->where('r.name', 'hr')->whereIn('ru.user_id', $ids)->pluck('ru.user_id')->map(fn ($id) => (int) $id)->all();
            $ids = $hrTeam ?: $ids;
        }

        foreach (\App\Models\User::whereIn('id', $ids)->get() as $user) {
            $user->notify(new \App\Notifications\LeaveApprovalUpdate($leave, 'waiting', static::label($step)));
        }
    }

    protected static function notifyApplicant(Leave $leave, string $event, string $detail): void
    {
        $user = \App\Models\User::find($leave->user_id);
        $user?->notify(new \App\Notifications\LeaveApprovalUpdate($leave, $event, $detail));
    }

}
