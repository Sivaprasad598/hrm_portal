<?php

namespace App\Services\Attendance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Which shift an employee works on a date, and which stretch of time counts
 * as that date.
 *
 * The shift, in order:
 *   1. the roster for that date (HR's own entries, and the rows written from
 *      the employee's assignment - see ShiftAssignments)
 *   2. the employee's assignment in force that date (for dates beyond the
 *      roster's rolling horizon)
 *   3. the company's default shift
 *
 * The day: a shift belongs to the date it starts on. A shift whose end time
 * is earlier than its start (22:00 - 06:00) runs into the next morning, so
 * that date's window runs on past midnight - to halfway between the shift's
 * end and the next shift's start, and never more than 6 hours past the end -
 * and the next date's window begins there. Where neither a date nor the one
 * before it is an overnight shift, the window is the calendar day, exactly as
 * before.
 *
 * Everything that measures a day - the biometric sync, the daily review,
 * payroll's day count, late marks - asks here, so they agree.
 */
class ShiftResolver
{
    /** How far past an overnight shift's end its punches still belong to it. */
    public const MAX_SPILL_SECONDS = 6 * 3600;

    protected array $shifts = [];
    protected ?object $defaultShift = null;
    protected array $roster = [];        // [userId][date] => shift object (with roster times)
    protected array $assignments = [];   // [userId] => list of assignment rows
    protected array $loaded = [];        // [userId] => true

    public function __construct(protected ?int $companyId = null)
    {
        $this->shifts = DB::table('employee_shifts')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get()->keyBy('id')->all();

        $defaultId = DB::table('attendance_settings')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->value('default_employee_shift');

        $this->defaultShift = $defaultId && isset($this->shifts[$defaultId])
            ? $this->decorate($this->shifts[$defaultId], 'default') : null;
    }

    /** @var array<int, self> */
    protected static array $instances = [];

    /** One per company for the life of a request or command. */
    public static function shared(?int $companyId = null): self
    {
        return self::$instances[(int) $companyId] ??= new self($companyId);
    }

    /** Drop every cached copy - after shifts, assignments or the roster change. */
    public static function reset(): void
    {
        self::$instances = [];
    }

    /** Forget cached roster rows (after an assignment or roster change). */
    public function forget(?int $userId = null): void
    {
        if ($userId === null) {
            $this->roster = $this->assignments = $this->loaded = [];

            return;
        }

        unset($this->roster[$userId], $this->assignments[$userId], $this->loaded[$userId]);
    }

    /**
     * The shift for an employee on a date, with ->source (roster | assignment
     * | default), ->day_start / ->day_end (local 'Y-m-d H:i:s', the shift's own
     * hours on that date) and ->overnight.
     */
    public function shiftOn(int $userId, string $date): ?object
    {
        $this->load($userId);

        if (isset($this->roster[$userId][$date])) {
            return $this->withHours($this->roster[$userId][$date], $date);
        }

        foreach ($this->assignments[$userId] as $assignment) {
            if ($assignment->effective_from <= $date && ($assignment->effective_to === null || $assignment->effective_to >= $date)) {
                $shift = $this->shifts[$assignment->employee_shift_id] ?? null;

                if ($shift) {
                    return $this->withHours($this->decorate($shift, 'assignment'), $date);
                }
            }
        }

        return $this->defaultShift ? $this->withHours($this->defaultShift, $date) : null;
    }

    public function defaultShift(): ?object
    {
        return $this->defaultShift;
    }

    /** A shift that ends earlier on the clock than it starts runs past midnight. */
    public static function isOvernight(?object $shift): bool
    {
        if (!$shift || self::isDayOff($shift) || !$shift->office_start_time || !$shift->office_end_time) {
            return false;
        }

        return strtotime($shift->office_end_time) <= strtotime($shift->office_start_time);
    }

    public static function isDayOff(?object $shift): bool
    {
        return $shift && (strtolower(trim((string) $shift->shift_name)) === 'day off');
    }

    /**
     * Whether the shift is worked on that date, by its own working days. As
     * everywhere else in the app (LeaveHelper, ShiftCalendar), a shift with no
     * working days ticked is never worked, and "Day Off" never is. A shift
     * worked every day ticks all seven.
     */
    public static function worksOn(?object $shift, string $date): bool
    {
        if (!$shift) {
            return true;
        }

        if (self::isDayOff($shift)) {
            return false;
        }

        $days = array_map('intval', json_decode((string) ($shift->office_open_days ?? ''), true) ?: []);

        return in_array((int) Carbon::parse($date)->format('N'), $days, true);
    }

    /**
     * The stretch of local time that belongs to a date for this employee.
     *
     * @return array{0: Carbon, 1: Carbon} local start and end (inclusive)
     */
    public function dayWindow(int $userId, string $date, string $tz): array
    {
        $start = Carbon::parse($date . ' 00:00:00', $tz);
        $end = Carbon::parse($date . ' 23:59:59', $tz);

        $today = $this->shiftOn($userId, $date);
        $previous = $this->shiftOn($userId, Carbon::parse($date)->subDay()->toDateString());

        if (self::isOvernight($today)) {
            $next = $this->shiftOn($userId, Carbon::parse($date)->addDay()->toDateString());
            $end = $this->cut(Carbon::parse($today->day_end, $tz), $this->startOf($next, $tz))->subSecond();
        }

        if (self::isOvernight($previous)) {
            $cut = $this->cut(Carbon::parse($previous->day_end, $tz), $this->startOf($today, $tz));
            $start = $cut->greaterThan($start) ? $cut : $start;
        }

        return [$start, $end];
    }

    /** The same window in UTC, for querying clock_in_time. */
    public function dayWindowUtc(int $userId, string $date, string $tz): array
    {
        [$start, $end] = $this->dayWindow($userId, $date, $tz);

        return [$start->copy()->utc(), $end->copy()->utc()];
    }

    /**
     * The date a moment belongs to for this employee: the day before, when it
     * falls in the morning tail of an overnight shift that started then.
     */
    public function shiftDayOf(int $userId, Carbon $moment, string $tz): string
    {
        $local = $moment->copy()->setTimezone($tz);
        $date = $local->toDateString();
        $previous = Carbon::parse($date)->subDay()->toDateString();

        if (self::isOvernight($this->shiftOn($userId, $previous))) {
            [, $end] = $this->dayWindow($userId, $previous, $tz);

            if ($local->lessThanOrEqualTo($end)) {
                return $previous;
            }
        }

        return $date;
    }

    /** Length of the shift in seconds; a day when it is not known. */
    public static function lengthSeconds(?object $shift): int
    {
        if (!$shift || !$shift->office_start_time || !$shift->office_end_time) {
            return 86400;
        }

        $start = strtotime($shift->office_start_time);
        $end = strtotime($shift->office_end_time);
        $length = $end > $start ? $end - $start : $end + 86400 - $start;

        return $length > 0 ? $length : 86400;
    }

    // -------------------------------------------------------------------------

    /** Halfway from one shift's end to the next shift's start, at most MAX_SPILL past the end. */
    protected function cut(Carbon $end, ?Carbon $nextStart): Carbon
    {
        $spill = self::MAX_SPILL_SECONDS;

        if ($nextStart && $nextStart->greaterThan($end)) {
            $spill = min($spill, intdiv($nextStart->getTimestamp() - $end->getTimestamp(), 2));
        }

        return $end->copy()->addSeconds($spill);
    }

    protected function startOf(?object $shift, string $tz): ?Carbon
    {
        return $shift && !self::isDayOff($shift) && $shift->office_start_time ? Carbon::parse($shift->day_start, $tz) : null;
    }

    protected function load(int $userId): void
    {
        if (isset($this->loaded[$userId])) {
            return;
        }

        $this->roster[$userId] = [];

        foreach (DB::table('employee_shift_schedules')->where('user_id', $userId)
            ->get(['date', 'employee_shift_id', 'assignment_id', 'shift_start_time', 'shift_end_time']) as $row) {
            $shift = $this->shifts[$row->employee_shift_id] ?? null;

            if ($shift) {
                $this->roster[$userId][substr((string) $row->date, 0, 10)] = $this->decorate($shift, $row->assignment_id ? 'assignment' : 'roster');
            }
        }

        $this->assignments[$userId] = DB::table('employee_shift_assignments')->where('user_id', $userId)
            ->orderByDesc('effective_from')
            ->get(['employee_shift_id', 'effective_from', 'effective_to'])
            ->map(function ($row) {
                $row->effective_from = substr((string) $row->effective_from, 0, 10);
                $row->effective_to = $row->effective_to ? substr((string) $row->effective_to, 0, 10) : null;

                return $row;
            })->all();

        $this->loaded[$userId] = true;
    }

    protected function decorate(object $shift, string $source): object
    {
        $copy = clone $shift;
        $copy->source = $source;

        return $copy;
    }

    protected function withHours(object $shift, string $date): object
    {
        $copy = clone $shift;
        $copy->overnight = self::isOvernight($shift);
        $copy->day_start = $shift->office_start_time ? $date . ' ' . $shift->office_start_time : $date . ' 00:00:00';
        $copy->day_end = $shift->office_end_time
            ? ($copy->overnight ? Carbon::parse($date)->addDay()->toDateString() : $date) . ' ' . $shift->office_end_time
            : $date . ' 23:59:59';

        return $copy;
    }
}
