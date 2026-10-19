<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * How many days of a pay period an employee is paid for.
 *
 * Working Days is every day of the period that has happened; Days Present is
 * the paid ones — worked (half for a half day), week-offs, holidays and paid
 * leave. Absence, LOP leave and days outside employment earn nothing, so
 * unpaid days are simply Working − Present.
 *
 * The period is any stretch of dates, so a cycle running the 21st to the 20th
 * counts exactly like a calendar month. This is the same counting the payslip
 * generator does; both read it from here so an estimate and the payslip that
 * follows can never disagree.
 */
class AttendanceDays
{

    public function __construct(protected ?string $timezone = null)
    {
        $this->timezone = $timezone ?: (DB::table('companies')->value('timezone') ?: 'Asia/Kolkata');
    }

    /**
     * A calendar month, for callers that think in months.
     *
     * @return array{working_days: int, days_present: float, breakdown: array<string, float>}
     */
    public function forMonth(int $userId, int $month, int $year): array
    {
        $start = Carbon::create($year, $month, 1, 0, 0, 0, $this->timezone);

        return $this->forRange($userId, $start, $start->copy()->endOfMonth());
    }

    /**
     * @return array{working_days: int, days_present: float, breakdown: array<string, float>, days: array<int, array>}
     *
     * "days" is the same count day by day: what each date was taken as, the
     * share of a day it was paid for, the shift, and the first and last punch.
     * The attendance screens explain a payslip from it, so what they show is
     * exactly what was counted.
     */
    public function forRange(int $userId, Carbon $from, Carbon $to): array
    {
        $tz = $this->timezone;
        $from = $from->copy()->setTimezone($tz)->startOfDay();
        $to = $to->copy()->setTimezone($tz)->startOfDay();
        $today = Carbon::now($tz)->startOfDay();

        $employee = DB::table('employee_details')->where('user_id', $userId)
            ->first(['joining_date', 'last_date', 'company_id']);
        $joinedOn = $employee?->joining_date ? substr((string) $employee->joining_date, 0, 10) : null;
        $leftOn = $employee?->last_date ? substr((string) $employee->last_date, 0, 10) : null;

        // The shift that applied each day - the roster, the employee's own
        // assignment, or the company default (ShiftResolver) - which decides
        // whether the day is worked (its working days), how long counts as a
        // full day, and which hours belong to the day for an overnight shift.
        $resolver = \App\Services\Attendance\ShiftResolver::shared($employee?->company_id);
        $holidays = $this->holidays($employee?->company_id, $from, $to);
        $leaveByDay = $this->leave($userId, $from, $to);
        $shiftByDay = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $shiftByDay[$key] = $resolver->shiftOn($userId, $key);
            $cursor->addDay();
        }

        $worked = $this->workedSeconds($userId, $from, $to, $tz, $shiftByDay, $resolver);

        // Someone who left and rejoined: the days between their employments are
        // not employed, and days of an earlier employment already paid in its
        // final settlement are not paid again. Null for one employment only.
        $payable = app(\App\Services\Employment\EmploymentPeriods::class)
            ->payableDays($userId, $from->toDateString(), $to->toDateString());

        $breakdown = ['worked' => 0.0, 'week_off' => 0, 'holiday' => 0, 'paid_leave' => 0.0,
            'lop_leave' => 0.0, 'absent' => 0.0, 'not_employed' => 0];
        $workingDays = 0;
        $daysPresent = 0.0;
        $days = [];

        // One line per date, for the screens that explain the count.
        $day = function (string $key, string $status, float $paid, ?object $shift, array $extra = []) use (&$days, $worked) {
            $edge = $this->bounds[$key] ?? null;
            $days[] = $extra + [
                'date' => $key,
                'status' => $status,
                'paid' => $paid,
                'shift' => $shift->shift_name ?? null,
                'shift_hours' => $shift && $shift->office_start_time
                    ? substr((string) $shift->office_start_time, 0, 5) . '–' . substr((string) $shift->office_end_time, 0, 5) : null,
                'first_in' => $edge ? Carbon::createFromTimestamp($edge['first'], 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i') : null,
                'last_out' => $edge && $edge['last'] > $edge['first'] ? Carbon::createFromTimestamp($edge['last'], 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i') : null,
                'seconds' => (int) ($worked[$key] ?? 0),
            ];
        };

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            if ($date->gt($today)) {
                $day($date->toDateString(), 'upcoming', 0.0, $shiftByDay[$date->toDateString()] ?? null);

                continue;   // A day that has not happened yet is not paid for.
            }

            $workingDays++;
            $key = $date->toDateString();
            $shift = $shiftByDay[$key];

            $outside = $payable !== null
                ? !($payable[$key] ?? false)
                : (($joinedOn && $key < $joinedOn) || ($leftOn && $key > $leftOn));

            if ($outside) {
                // Outside employment only time actually punched is paid: attendance
                // before the joining date usually means the date is wrong. Between
                // two employments nothing is: those days were either not worked or
                // already paid in the earlier employment's settlement.
                $credit = $payable !== null ? 0.0 : $this->attendanceCredit($worked[$key] ?? 0, $shift);
                $breakdown['worked'] += $credit;
                $breakdown['not_employed'] += 1 - $credit;
                $daysPresent += $credit;
                $day($key, 'not_employed', $credit, $shift);
                continue;
            }

            // Off by the shift's own working days ("Day Off" is never worked).
            $weekOff = !\App\Services\Attendance\ShiftResolver::worksOn($shift, $key);

            if ($weekOff) {
                $breakdown['week_off']++;
                $daysPresent++;
                $day($key, 'week_off', 1.0, $shift);
                continue;
            }

            if (in_array($key, $holidays, true)) {
                $breakdown['holiday']++;
                $daysPresent++;
                $day($key, 'holiday', 1.0, $shift, ['note' => $this->holidayNames[$key] ?? null]);
                continue;
            }

            $leave = $leaveByDay[$key] ?? ['covered' => 0.0];
            $paidLeave = (float) ($leave['paid'] ?? 0);
            $credit = min(1.0 - $leave['covered'], $this->attendanceCredit($worked[$key] ?? 0, $shift));

            $breakdown['paid_leave'] += $paidLeave;
            $breakdown['lop_leave'] += (float) ($leave['unpaid'] ?? 0);
            $breakdown['worked'] += $credit;
            $breakdown['absent'] += max(0.0, 1.0 - $leave['covered'] - $credit);
            $paid = min(1.0, $paidLeave + $credit);
            $daysPresent += $paid;

            // What the day was, in one word, for the screens.
            $unpaidLeave = (float) ($leave['unpaid'] ?? 0);
            $status = match (true) {
                $credit >= 1.0 => 'present',
                $paidLeave >= 1.0 => 'paid_leave',
                $unpaidLeave >= 1.0 => 'lop_leave',
                $credit > 0 && $leave['covered'] > 0 => 'half_day_leave',
                $credit > 0 => 'half_day',
                $paidLeave > 0 || $unpaidLeave > 0 => 'half_leave',
                default => 'absent',
            };
            $day($key, $status, $paid, $shift, ['leave_paid' => $paidLeave, 'leave_unpaid' => $unpaidLeave, 'credit' => $credit]);
        }

        return ['working_days' => $workingDays, 'days_present' => $daysPresent, 'breakdown' => $breakdown, 'days' => $days];
    }

    /** Credit for the time worked on a day: 1, 0.5 or nothing, by the shift's thresholds. */
    protected function attendanceCredit(float $seconds, ?object $shift): float
    {
        if (!$shift || $seconds <= 0) {
            return 0.0;
        }

        $full = $half = null;

        if ($shift->min_working_hours_full_day !== null && $shift->min_working_hours_half_day !== null) {
            $full = ($shift->min_working_hours_full_day * 3600) - (($shift->min_working_hours_full_day_grace ?? 0) * 60);
            $half = ($shift->min_working_hours_half_day * 3600) - (($shift->min_working_hours_half_day_grace ?? 0) * 60);
        } elseif ($shift->office_start_time && $shift->office_end_time) {
            $s = strtotime($shift->office_start_time);
            $e = strtotime($shift->office_end_time);
            $length = $e > $s ? ($e - $s) : ($e + 86400 - $s);
            $full = $length * 0.5;
            $half = $length * 0.25;
        }

        if ($full !== null && $seconds >= $full) {
            return 1.0;
        }

        return ($half !== null && $seconds >= $half) ? 0.5 : 0.0;
    }

    /** Holiday names by date, from the last holidays() call. */
    protected array $holidayNames = [];

    /** First and last punch per day (unix seconds), from the last workedSeconds() call. */
    protected array $bounds = [];

    /** @return array<int, string> dates */
    protected function holidays(?int $companyId, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('holidays')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['date', 'occassion']);

        $this->holidayNames = [];

        foreach ($rows as $row) {
            $this->holidayNames[substr((string) $row->date, 0, 10)] = $row->occassion;
        }

        return array_keys($this->holidayNames);
    }

    /** @return array<string, array<string, float>> keyed by date */
    protected function leave(int $userId, Carbon $from, Carbon $to): array
    {
        // Which leave is unpaid: the code decides where one is set (LOP alone is
        // unpaid), otherwise the name or the type's own paid flag.
        $unpaid = [];

        foreach (DB::table('leave_types')->get(['id', 'type_name', 'code', 'paid']) as $type) {
            $code = strtoupper(trim((string) $type->code));
            $unpaid[(int) $type->id] = $code !== ''
                ? $code === 'LOP'
                : (strtolower(trim($type->type_name)) === 'lop' || (string) $type->paid === '0');
        }

        $byDay = [];

        foreach (DB::table('leaves')->where('user_id', $userId)->where('status', 'approved')
            ->whereBetween('leave_date', [$from->toDateString(), $to->toDateString()])
            ->get(['leave_date', 'duration', 'leave_type_id']) as $leave) {
            $key = substr((string) $leave->leave_date, 0, 10);
            $portion = $leave->duration === 'half day' ? 0.5 : 1.0;
            $kind = ($unpaid[(int) $leave->leave_type_id] ?? false) ? 'unpaid' : 'paid';
            $byDay[$key]['covered'] = min(1.0, ($byDay[$key]['covered'] ?? 0) + $portion);
            $byDay[$key][$kind] = ($byDay[$key][$kind] ?? 0) + $portion;
        }

        return $byDay;
    }

    /** Seconds worked per local day, summing every session. @return array<string, float> */
    /**
     * How long each day counts as worked.
     *
     * Measured as presence — earliest punch to latest punch — not as the sum of
     * paired sessions. A door feed cannot be relied on to pair: an exit swipe
     * with no matching entry produces a zero-length session, and a forgotten
     * exit leaves one running until the next punch, sometimes a day later. Both
     * happen in the real data, and summing pairs got the answer wrong in both
     * directions — one employee scored 0.04 hours on a day he was on site from
     * 10:38 to 18:24, while two unclosed sessions ran past 25 hours and bought
     * a full day each.
     *
     * The biometric module already measures it this way: DayTimeline computes
     * presence_minutes, and AttendanceSync notes that hours are judged on
     * presence "not paired door sessions". This brings payroll into line.
     *
     * Capped at the shift's length, so an unclosed session can never earn more
     * than the day it belongs to.
     *
     * @param  array<string, object>  $shifts  the shift that applied, keyed by date
     */
    protected function workedSeconds(int $userId, Carbon $from, Carbon $to, string $tz, array $shifts = [],
        ?\App\Services\Attendance\ShiftResolver $resolver = null): array
    {
        // A day either side: an overnight shift's morning tail falls on the next
        // calendar day, and the first day's early hours may belong to the day before.
        $fromUtc = $from->copy()->subDay()->startOfDay()->utc();
        $toUtc = $to->copy()->addDay()->endOfDay()->utc();
        $bounds = [];

        foreach (DB::table('attendances')->where('user_id', $userId)
            ->whereBetween('clock_in_time', [$fromUtc, $toUtc])
            ->get(['clock_in_time', 'clock_out_time']) as $row) {
            $in = Carbon::parse($row->clock_in_time, 'UTC');
            // The day the session belongs to: its shift's day, not just its calendar date.
            $key = $resolver ? $resolver->shiftDayOf($userId, $in, $tz) : $in->copy()->setTimezone($tz)->toDateString();

            if (!isset($shifts[$key]) && $resolver) {
                continue;   // outside the range asked for
            }

            // An open session still proves arrival, so it sets the start even
            // though it cannot set the end.
            $out = $row->clock_out_time ? Carbon::parse($row->clock_out_time, 'UTC') : null;

            $bounds[$key]['first'] = isset($bounds[$key]['first'])
                ? min($bounds[$key]['first'], $in->getTimestamp())
                : $in->getTimestamp();

            $last = max($in->getTimestamp(), $out?->getTimestamp() ?? $in->getTimestamp());
            $bounds[$key]['last'] = isset($bounds[$key]['last'])
                ? max($bounds[$key]['last'], $last)
                : $last;
        }

        $seconds = [];
        $this->bounds = $bounds;

        foreach ($bounds as $date => $edge) {
            $span = max(0, $edge['last'] - $edge['first']);
            $seconds[$date] = min($span, $this->shiftSeconds($shifts[$date] ?? null));
        }

        return $seconds;
    }

    /**
     * A shift's length in seconds, or a day if the shift is unknown — the cap
     * should never be the thing that denies a day.
     */
    protected function shiftSeconds(?object $shift): int
    {
        if (!$shift || !$shift->office_start_time || !$shift->office_end_time) {
            return 86400;
        }

        $start = strtotime($shift->office_start_time);
        $end = strtotime($shift->office_end_time);

        $length = $end > $start ? ($end - $start) : ($end + 86400 - $start);

        return $length > 0 ? $length : 86400;
    }

}
