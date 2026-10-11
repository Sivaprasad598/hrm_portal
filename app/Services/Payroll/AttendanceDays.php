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
     * @return array{working_days: int, days_present: float, breakdown: array<string, float>}
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

        $roster = $this->roster($userId, $from, $to);
        $defaultShift = $this->defaultShift($employee?->company_id);
        $holidays = $this->holidays($employee?->company_id, $from, $to);
        $leaveByDay = $this->leave($userId, $from, $to);
        // The shift that applied each day, so presence can be capped at its
        // length: the roster where there is one, the company default otherwise.
        $shiftByDay = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $shiftByDay[$key] = $roster[$key]->shift ?? $roster[$key] ?? $defaultShift;
            $cursor->addDay();
        }

        $worked = $this->workedSeconds($userId, $from, $to, $tz, $shiftByDay);

        $openDays = $defaultShift
            ? array_map('intval', json_decode((string) ($defaultShift->office_open_days ?? ''), true) ?: [])
            : [];

        $breakdown = ['worked' => 0.0, 'week_off' => 0, 'holiday' => 0, 'paid_leave' => 0.0,
            'lop_leave' => 0.0, 'absent' => 0.0, 'not_employed' => 0];
        $workingDays = 0;
        $daysPresent = 0.0;

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            if ($date->gt($today)) {
                continue;   // A day that has not happened yet is not paid for.
            }

            $workingDays++;
            $key = $date->toDateString();
            $shift = $roster[$key] ?? $defaultShift;

            if (($joinedOn && $key < $joinedOn) || ($leftOn && $key > $leftOn)) {
                // Outside employment only time actually punched is paid: attendance
                // before the joining date usually means the date is wrong.
                $credit = $this->attendanceCredit($worked[$key] ?? 0, $shift);
                $breakdown['worked'] += $credit;
                $breakdown['not_employed'] += 1 - $credit;
                $daysPresent += $credit;
                continue;
            }

            $weekOff = isset($roster[$key])
                ? $roster[$key]->shift_name === 'Day Off'
                : ($defaultShift !== null && !in_array((int) $date->format('N'), $openDays, true));

            if ($weekOff) {
                $breakdown['week_off']++;
                $daysPresent++;
                continue;
            }

            if (in_array($key, $holidays, true)) {
                $breakdown['holiday']++;
                $daysPresent++;
                continue;
            }

            $leave = $leaveByDay[$key] ?? ['covered' => 0.0];
            $paidLeave = (float) ($leave['paid'] ?? 0);
            $credit = min(1.0 - $leave['covered'], $this->attendanceCredit($worked[$key] ?? 0, $shift));

            $breakdown['paid_leave'] += $paidLeave;
            $breakdown['lop_leave'] += (float) ($leave['unpaid'] ?? 0);
            $breakdown['worked'] += $credit;
            $breakdown['absent'] += max(0.0, 1.0 - $leave['covered'] - $credit);
            $daysPresent += min(1.0, $paidLeave + $credit);
        }

        return ['working_days' => $workingDays, 'days_present' => $daysPresent, 'breakdown' => $breakdown];
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

    /** @return array<string, object> keyed by date */
    protected function roster(int $userId, Carbon $from, Carbon $to): array
    {
        return DB::table('employee_shift_schedules as ess')
            ->join('employee_shifts as es', 'es.id', '=', 'ess.employee_shift_id')
            ->where('ess.user_id', $userId)
            ->whereBetween('ess.date', [$from->toDateString(), $to->toDateString()])
            ->get(['ess.date', 'es.shift_name', 'es.office_open_days', 'es.min_working_hours_full_day',
                'es.min_working_hours_full_day_grace', 'es.min_working_hours_half_day',
                'es.min_working_hours_half_day_grace', 'es.office_start_time', 'es.office_end_time'])
            ->keyBy(fn ($r) => substr((string) $r->date, 0, 10))
            ->all();
    }

    protected function defaultShift(?int $companyId): ?object
    {
        return DB::table('attendance_settings as ats')
            ->join('employee_shifts as es', 'es.id', '=', 'ats.default_employee_shift')
            ->when($companyId, fn ($q) => $q->where('ats.company_id', $companyId))
            ->first(['es.shift_name', 'es.office_open_days', 'es.min_working_hours_full_day',
                'es.min_working_hours_full_day_grace', 'es.min_working_hours_half_day',
                'es.min_working_hours_half_day_grace', 'es.office_start_time', 'es.office_end_time']);
    }

    /** @return array<int, string> dates */
    protected function holidays(?int $companyId, Carbon $from, Carbon $to): array
    {
        return DB::table('holidays')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->unique()->values()->all();
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
    protected function workedSeconds(int $userId, Carbon $from, Carbon $to, string $tz, array $shifts = []): array
    {
        $fromUtc = $from->copy()->startOfDay()->utc();
        $toUtc = $to->copy()->endOfDay()->utc();
        $bounds = [];

        foreach (DB::table('attendances')->where('user_id', $userId)
            ->whereBetween('clock_in_time', [$fromUtc, $toUtc])
            ->get(['clock_in_time', 'clock_out_time']) as $row) {
            $in = Carbon::parse($row->clock_in_time, 'UTC');
            $key = $in->copy()->setTimezone($tz)->toDateString();

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
