<?php

namespace App\Services\Attendance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * An employee's own shift, from a date.
 *
 * Assigning closes the previous assignment the day before and writes the new
 * shift into the roster (employee_shift_schedules) one row per day, from the
 * effective date to HORIZON_DAYS ahead; the daily `shifts:extend` command
 * keeps that horizon topped up. Writing the roster is what makes every screen
 * that already reads it - web clock-in, late marks, leave half-days, the
 * dashboard, exports - follow the employee's shift without each being taught
 * about assignments.
 *
 * Roster rows written here carry assignment_id. A row HR rostered by hand for a
 * date (no assignment_id) is left alone: a one-off change wins.
 *
 * Rows are inserted directly, not through the EmployeeShiftSchedule model, so
 * the employee is not sent a "shift scheduled" notification for every day.
 */
class ShiftAssignments
{
    public const HORIZON_DAYS = 60;

    /**
     * Give an employee a shift from a date. A null shift means "back to the
     * company default": the current assignment ends and nothing replaces it.
     */
    public function assign(int $userId, ?int $shiftId, string $from, ?int $by = null, ?string $note = null): void
    {
        $from = Carbon::parse($from)->toDateString();
        $current = $this->current($userId, $from);

        // Nothing to do when the same shift is already in force from on or before that date.
        if ($current && (int) $current->employee_shift_id === (int) $shiftId) {
            return;
        }

        DB::transaction(function () use ($userId, $shiftId, $from, $by, $note) {
            $companyId = DB::table('users')->where('id', $userId)->value('company_id');

            // Assignments starting on or after the date are replaced; the one
            // running across it ends the day before.
            $replaced = DB::table('employee_shift_assignments')->where('user_id', $userId)
                ->where('effective_from', '>=', $from)->pluck('id');
            DB::table('employee_shift_schedules')->whereIn('assignment_id', $replaced)->delete();
            DB::table('employee_shift_assignments')->whereIn('id', $replaced)->delete();

            DB::table('employee_shift_assignments')->where('user_id', $userId)
                ->where('effective_from', '<', $from)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from))
                ->update(['effective_to' => Carbon::parse($from)->subDay()->toDateString(), 'updated_at' => now()]);

            // The old shift's roster rows from the date on go.
            DB::table('employee_shift_schedules')->where('user_id', $userId)
                ->whereNotNull('assignment_id')->where('date', '>=', $from)->delete();

            if ($shiftId) {
                $id = DB::table('employee_shift_assignments')->insertGetId([
                    'company_id' => $companyId, 'user_id' => $userId, 'employee_shift_id' => $shiftId,
                    'effective_from' => $from, 'effective_to' => null, 'assigned_by' => $by, 'note' => $note,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                $this->materialise($id, $from, now()->addDays(self::HORIZON_DAYS)->toDateString());
            }
        });

        ShiftResolver::reset();
    }

    /** The assignment in force on a date, or null (company default). */
    public function current(int $userId, ?string $on = null): ?object
    {
        $on = $on ?: now()->toDateString();

        return DB::table('employee_shift_assignments as a')
            ->leftJoin('employee_shifts as s', 's.id', '=', 'a.employee_shift_id')
            ->where('a.user_id', $userId)
            ->where('a.effective_from', '<=', $on)
            ->where(fn ($q) => $q->whereNull('a.effective_to')->orWhere('a.effective_to', '>=', $on))
            ->orderByDesc('a.effective_from')
            ->first(['a.*', 's.shift_name', 's.office_start_time', 's.office_end_time', 's.color']);
    }

    /** Every assignment, newest first. */
    public function history(int $userId)
    {
        return DB::table('employee_shift_assignments as a')
            ->leftJoin('employee_shifts as s', 's.id', '=', 'a.employee_shift_id')
            ->leftJoin('users as u', 'u.id', '=', 'a.assigned_by')
            ->where('a.user_id', $userId)
            ->orderByDesc('a.effective_from')
            ->get(['a.*', 's.shift_name', 's.office_start_time', 's.office_end_time', 'u.name as assigned_by_name']);
    }

    /** Write roster rows for an assignment's days in [from, to], skipping dates HR rostered by hand. */
    public function materialise(int $assignmentId, string $from, string $to): int
    {
        $assignment = DB::table('employee_shift_assignments')->find($assignmentId);
        $shift = $assignment ? DB::table('employee_shifts')->find($assignment->employee_shift_id) : null;

        if (!$assignment || !$shift) {
            return 0;
        }

        $start = max($from, substr((string) $assignment->effective_from, 0, 10));
        $end = $assignment->effective_to ? min($to, substr((string) $assignment->effective_to, 0, 10)) : $to;

        if ($start > $end) {
            return 0;
        }

        $taken = DB::table('employee_shift_schedules')->where('user_id', $assignment->user_id)
            ->whereBetween('date', [$start, $end])->pluck('date')
            ->map(fn ($d) => substr((string) $d, 0, 10))->flip()->all();

        $overnight = ShiftResolver::isOvernight($shift);
        $rows = [];

        for ($day = Carbon::parse($start); $day->toDateString() <= $end; $day->addDay()) {
            $date = $day->toDateString();

            if (isset($taken[$date])) {
                continue;
            }

            $rows[] = [
                'company_id' => $assignment->company_id,
                'user_id' => $assignment->user_id,
                'date' => $date,
                'employee_shift_id' => $shift->id,
                'assignment_id' => $assignment->id,
                'shift_start_time' => $date . ' ' . $shift->office_start_time,
                'shift_end_time' => ($overnight ? $day->copy()->addDay()->toDateString() : $date) . ' ' . $shift->office_end_time,
                'added_by' => $assignment->assigned_by,
                'last_updated_by' => $assignment->assigned_by,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('employee_shift_schedules')->insert($chunk);
        }

        return count($rows);
    }

    /** Top every open assignment's roster up to the horizon. @return int rows written */
    public function extendAll(): int
    {
        $to = now()->addDays(self::HORIZON_DAYS)->toDateString();
        $written = 0;

        foreach (DB::table('employee_shift_assignments')->where(fn ($q) => $q->whereNull('effective_to')
            ->orWhere('effective_to', '>=', now()->toDateString()))->get(['id', 'user_id', 'effective_from']) as $assignment) {
            $last = DB::table('employee_shift_schedules')->where('assignment_id', $assignment->id)->max('date');
            $from = $last ? Carbon::parse($last)->addDay()->toDateString() : substr((string) $assignment->effective_from, 0, 10);
            $written += $this->materialise($assignment->id, $from, $to);
        }

        return $written;
    }

    /** After a shift's hours change: its assignment roster rows from today on take the new hours. */
    public function refreshHours(int $shiftId): void
    {
        $shift = DB::table('employee_shifts')->find($shiftId);

        if (!$shift) {
            return;
        }

        $overnight = ShiftResolver::isOvernight($shift);

        foreach (DB::table('employee_shift_schedules')->where('employee_shift_id', $shiftId)
            ->whereNotNull('assignment_id')->where('date', '>=', now()->toDateString())
            ->get(['id', 'date']) as $row) {
            $date = substr((string) $row->date, 0, 10);
            DB::table('employee_shift_schedules')->where('id', $row->id)->update([
                'shift_start_time' => $date . ' ' . $shift->office_start_time,
                'shift_end_time' => ($overnight ? Carbon::parse($date)->addDay()->toDateString() : $date) . ' ' . $shift->office_end_time,
                'updated_at' => now(),
            ]);
        }

        ShiftResolver::reset();
    }

    /** How many employees each shift is assigned to today: [shift_id => count]. */
    public static function usage(): array
    {
        $today = now()->toDateString();

        return DB::table('employee_shift_assignments')
            ->where('effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->selectRaw('employee_shift_id, count(distinct user_id) as n')
            ->groupBy('employee_shift_id')->pluck('n', 'employee_shift_id')->all();
    }
}
