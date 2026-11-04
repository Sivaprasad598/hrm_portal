<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRegularisation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Attendance corrections (an employee asking for a day's clock-in / out to be
 * put right) in two steps, as leave (App\Services\Leaves\LeaveApprovals):
 *
 *   manager  the employee's Reporting To
 *   hr       anyone who may edit everybody's attendance (HR, Admin)
 *
 * With no manager, or a manager who is the applicant, it goes to HR straight
 * away. HR may also decide while it waits for the manager. Nobody decides
 * their own request. Only the final HR approval writes the attendance.
 */
class CorrectionApprovals
{

    public const STEPS = ['manager' => 'Reporting manager', 'hr' => 'HR'];

    public const DONE = 'done';

    public static function label(?string $step): string
    {
        return self::STEPS[$step] ?? '';
    }

    // ------------------------------------------------------------ who approves

    /** Who decides a step for an applicant; [] when nobody (the step is passed over). */
    public static function approvers(string $step, int $applicantId): array
    {
        $ids = match ($step) {
            'manager' => array_filter([(int) DB::table('employee_details')->where('user_id', $applicantId)->value('reporting_to')]),
            'hr' => static::hrIds(),
            default => [],
        };

        $active = DB::table('users')->whereIn('id', $ids)->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_diff($active, [$applicantId]));
    }

    /** Everyone who may edit everybody's attendance (HR, Admin). */
    public static function hrIds(): array
    {
        return DB::table('user_permissions as up')->join('permissions as p', 'p.id', '=', 'up.permission_id')
            ->join('permission_types as t', 't.id', '=', 'up.permission_type_id')
            ->where('p.name', 'edit_attendance')->where('t.name', 'all')
            ->pluck('up.user_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    public static function isHr(?int $userId = null): bool
    {
        $userId ??= (int) user()?->id;

        return in_array($userId, static::hrIds(), true) || in_array('admin', static::roles($userId), true);
    }

    /** The people who report to this person (their team). */
    public static function teamUserIds(?int $userId = null): array
    {
        $userId ??= (int) user()?->id;

        return DB::table('employee_details as e')->join('users as u', 'u.id', '=', 'e.user_id')
            ->where('e.reporting_to', $userId)->where('u.status', 'active')->where('u.id', '!=', $userId)
            ->pluck('u.id')->map(fn ($id) => (int) $id)->values()->all();
    }

    // ---------------------------------------------------------------- flow

    /** A new request waits at its first step; its approvers hear of it. */
    public static function start(AttendanceRegularisation $request): void
    {
        $step = static::approvers('manager', (int) $request->user_id) ? 'manager' : 'hr';

        static::moveTo($request, $step);
        static::log($request, $step, 'submitted', (int) $request->user_id);
    }

    /**
     * What this person may do on this request:
     *   step      decide it - they are the approver of the step it waits at
     *   override  decide it ahead of the manager (HR)
     *   null      nothing
     */
    public static function canAct(AttendanceRegularisation $request, ?int $userId = null): ?string
    {
        $userId ??= (int) user()?->id;
        $step = $request->approval_step ?: 'hr';

        if ($request->status !== 'pending' || $step === self::DONE || (int) $request->user_id === $userId) {
            return null;
        }

        if (in_array($userId, static::approvers($step, (int) $request->user_id), true)) {
            return 'step';
        }

        return static::isHr($userId) ? 'override' : null;
    }

    /** Whether this person may open the request: theirs, their team's, or anyone's for HR. */
    public static function canView(AttendanceRegularisation $request, ?int $userId = null): bool
    {
        $userId ??= (int) user()?->id;

        return (int) $request->user_id === $userId
            || static::isHr($userId)
            || user()?->permission('view_attendance') === 'all'
            || in_array((int) $request->user_id, static::teamUserIds($userId), true);
    }

    /**
     * Approve at the current step. 'final' when it is decided (the caller then
     * writes the attendance and tells the employee, as before), 'advanced' when
     * it moves on to HR.
     */
    public static function approve(AttendanceRegularisation $request, ?string $note = null): string
    {
        $mode = static::canAct($request);

        if (!$mode) {
            throw new RuntimeException((int) $request->user_id === (int) user()?->id
                ? 'Your own request is decided by somebody else.'
                : 'This request is waiting for the ' . static::who($request->approval_step) . '.');
        }

        $step = $request->approval_step ?: 'hr';

        if ($step === 'manager' && $mode === 'step' && static::approvers('hr', (int) $request->user_id)
            && array_diff(static::approvers('hr', (int) $request->user_id), [(int) user()?->id])) {
            static::log($request, $step, 'approved', null, $note);
            static::moveTo($request, 'hr');
            static::notifyApplicant($request, 'approved_step', 'Reporting manager approved; now with HR.');

            return 'advanced';
        }

        static::log($request, $step, $mode === 'override' ? 'override' : 'approved', null, $note);
        static::finish($request, 'approved');

        return 'final';
    }

    public static function reject(AttendanceRegularisation $request, ?string $reason = null): void
    {
        if (!static::canAct($request)) {
            throw new RuntimeException('This request is waiting for the ' . static::who($request->approval_step) . '.');
        }

        static::log($request, $request->approval_step ?: 'hr', 'rejected', null, $reason);
        static::finish($request, 'rejected');
    }

    /** Requests waiting for this person to decide (their step, not HR's override). */
    public static function waitingFor(?int $userId = null): Collection
    {
        $userId ??= (int) user()?->id;
        $team = static::teamUserIds($userId);
        $isHr = static::isHr($userId);

        if (!$team && !$isHr) {
            return collect();
        }

        return AttendanceRegularisation::with('user:id,name,image')
            ->where('status', 'pending')->where('user_id', '!=', $userId)
            ->where(function ($q) use ($team, $isHr) {
                if ($team) {
                    $q->orWhere(fn ($w) => $w->where('approval_step', 'manager')->whereIn('user_id', $team));
                }

                if ($isHr) {
                    $q->orWhere(fn ($w) => $w->where('approval_step', 'hr')->orWhereNull('approval_step'));
                }
            })
            ->orderBy('date')->get();
    }

    /** The badge on the Requests tab: everything pending for HR, else their own and those waiting for them. */
    public static function pendingCount(?int $userId = null): int
    {
        $userId ??= (int) user()?->id;

        if (static::isHr($userId)) {
            return AttendanceRegularisation::where('status', 'pending')->count();
        }

        return AttendanceRegularisation::where('status', 'pending')->where('user_id', $userId)->count() + static::waitingFor($userId)->count();
    }

    /** Who did what, oldest first. */
    public static function trail(AttendanceRegularisation $request): Collection
    {
        return DB::table('attendance_regularisation_approvals as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.regularisation_id', $request->id)->orderBy('a.id')->get(['a.*', 'u.name as user_name']);
    }

    public static function who(?string $step): string
    {
        return ($step ?: 'hr') === 'hr' ? 'HR' : 'reporting manager';
    }

    // ------------------------------------------------------------- helpers

    protected static function finish(AttendanceRegularisation $request, string $outcome): void
    {
        $request->forceFill(['approval_step' => self::DONE])->save();

        // The manager who approved earlier hears how it ended
        $ids = DB::table('attendance_regularisation_approvals')->where('regularisation_id', $request->id)->where('action', 'approved')
            ->whereNotNull('user_id')->pluck('user_id')->map(fn ($id) => (int) $id)->unique()
            ->reject(fn ($id) => $id === (int) user()?->id || $id === (int) $request->user_id);

        foreach (User::whereIn('id', $ids)->get() as $user) {
            $user->notify(new \App\Notifications\AttendanceCorrectionUpdate($request, 'decided', $outcome . ' by ' . (user()?->name ?: 'HR') . '.'));
        }
    }

    /** The request waits at $step; the step's approvers get the usual "request" notice. */
    protected static function moveTo(AttendanceRegularisation $request, string $step): void
    {
        $request->forceFill(['approval_step' => $step, 'approval_step_at' => now()])->save();

        $approvers = User::whereIn('id', static::approvers($step, (int) $request->user_id))->get();

        if ($approvers->isNotEmpty()) {
            event(new \App\Events\RequestRegulariseEvent($request, $approvers));
        }
    }

    protected static function notifyApplicant(AttendanceRegularisation $request, string $event, string $detail): void
    {
        User::find($request->user_id)?->notify(new \App\Notifications\AttendanceCorrectionUpdate($request, $event, $detail));
    }

    protected static function log(AttendanceRegularisation $request, string $step, string $action, ?int $userId = null, ?string $note = null): void
    {
        DB::table('attendance_regularisation_approvals')->insert([
            'regularisation_id' => $request->id, 'step' => $step, 'action' => $action,
            'user_id' => $userId ?? user()?->id,
            'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
            'created_at' => now(),
        ]);
    }

    protected static function roles(int $userId): array
    {
        return DB::table('role_user as ru')->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->where('ru.user_id', $userId)->pluck('r.name')->all();
    }

}
