<?php

namespace App\Services\Attendance;

use App\Models\AttendanceReview;
use App\Services\Payroll\AttendanceDays;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Judges one date for every active employee, the morning after.
 *
 * Covers what the device Exceptions screen cannot see: that screen lists days
 * somebody punched; this one also catches the person who did not come in,
 * came late, left early, worked too few hours to earn the day, worked on a day
 * they had leave for, or whose leave was never approved.
 *
 * The day credit is AttendanceDays' own figure (1, 0.5 or 0), so what this
 * screen says a day is worth is exactly what payroll will pay for it.
 */
class DailyAttendanceReview
{

    protected string $tz;


    public function __construct()
    {
        $this->tz = DB::table('companies')->value('timezone') ?: 'Asia/Kolkata';
    }

    /**
     * Review a date and store one row per employee.
     *
     * @param  bool  $deviceComplete  false when the device pull for this date failed
     * @param  ?Carbon  $dataThrough  how far the device data reaches; a shift whose
     *                                window ends after it is still running, and is
     *                                not judged on its missing end (night shifts at 3 AM)
     * @return array<string, int>  status => count, plus 'exceptions'
     */
    public function review(Carbon $date, bool $deviceComplete = true, ?Carbon $dataThrough = null): array
    {
        $day = Carbon::parse($date->toDateString(), $this->tz)->startOfDay();
        $key = $day->toDateString();
        ShiftResolver::reset();   // shifts or assignments may have changed since the last run
        $counter = new AttendanceDays($this->tz);

        $employees = DB::table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->where('u.status', 'active')
            ->where(fn ($q) => $q->whereNull('ed.joining_date')->orWhereDate('ed.joining_date', '<=', $key))
            ->where(fn ($q) => $q->whereNull('ed.last_date')->orWhereDate('ed.last_date', '>=', $key))
            // Attendance-exempt (management paid in full) have no attendance to review.
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('employee_details', 'attendance_exempt'),
                fn ($q) => $q->where(fn ($w) => $w->whereNull('ed.attendance_exempt')->orWhere('ed.attendance_exempt', 0)))
            ->get(['u.id', 'u.company_id']);

        $mapped = DB::table('iapp_employees')->where('map_status', 'mapped')->whereNotNull('user_id')
            ->pluck('emp_code', 'user_id');
        $holiday = DB::table('holidays')->whereDate('date', $key)->exists();

        $summary = ['exceptions' => 0];

        foreach ($employees as $employee) {
            $row = $this->judge((int) $employee->id, $day, $counter, $mapped[$employee->id] ?? null, $holiday, $deviceComplete, $dataThrough);
            $row['company_id'] = $employee->company_id;

            $existing = AttendanceReview::withoutGlobalScopes()->where('date', $key)->where('user_id', $employee->id)->first();

            // A day HR has already dealt with keeps its note and resolution unless
            // the facts changed (late punches arriving, say), in which case it is
            // opened again.
            if ($existing && $existing->resolved_at && ($existing->status !== $row['status'] || $existing->flags != $row['flags'])) {
                $row['resolved_at'] = null;
                $row['resolved_by'] = null;
            }

            AttendanceReview::withoutGlobalScopes()->updateOrCreate(['date' => $key, 'user_id' => $employee->id], $row);

            $summary[$row['status']] = ($summary[$row['status']] ?? 0) + 1;
            $summary['exceptions'] += $row['is_exception'] ? 1 : 0;
        }

        return $summary;
    }

    /**
     * Re-judge one employee's day after something changed it - a leave approved,
     * an attendance row corrected - so the Daily Review follows by itself instead
     * of waiting for somebody to press "Review again". Only for a day that has
     * already been reviewed; a day not yet reviewed is the daily run's job.
     */
    public function refresh(int $userId, Carbon $date): void
    {
        $key = Carbon::parse($date->toDateString(), $this->tz)->toDateString();
        $existing = AttendanceReview::withoutGlobalScopes()->where('date', $key)->where('user_id', $userId)->first();

        if (!$existing) {
            return;
        }

        $run = DB::table('attendance_daily_runs')->where('date', $key)->first(['device_complete']);
        $empCode = DB::table('iapp_employees')->where('user_id', $userId)->where('map_status', 'mapped')->value('emp_code');
        $row = $this->judge($userId, Carbon::parse($key, $this->tz), new AttendanceDays($this->tz), $empCode,
            DB::table('holidays')->whereDate('date', $key)->exists(), (bool) ($run->device_complete ?? true));

        if ($existing->resolved_at && ($existing->status !== $row['status'] || $existing->flags != $row['flags'])) {
            $row['resolved_at'] = null;
            $row['resolved_by'] = null;
        }

        $existing->forceFill($row)->save();
    }

    /** @return array<string, mixed> */
    protected function judge(int $userId, Carbon $day, AttendanceDays $counter, ?string $empCode, bool $holiday, bool $deviceComplete, ?Carbon $dataThrough = null): array
    {
        $key = $day->toDateString();
        $count = $counter->forRange($userId, $day, $day);
        $breakdown = $count['breakdown'];

        // The day's overtime goes into the employee's OT bank straight away
        // (and again whenever the day is judged afresh after a correction).
        if (\Illuminate\Support\Facades\Schema::hasTable('ot_daily')) {
            (new \App\Services\Payroll\OtDaily())->store($userId,
                (int) DB::table('employee_details')->where('user_id', $userId)->value('company_id'), $count);
        }
        $credit = (float) $count['days_present'];

        // What was recorded: ERP attendance in the day's own window - the
        // calendar day, or for an overnight shift the hours that belong to it.
        $resolver = ShiftResolver::shared();
        [$windowStart, $windowEnd] = $resolver->dayWindowUtc($userId, $key, $this->tz);
        $rows = DB::table('attendances')->where('user_id', $userId)
            ->whereBetween('clock_in_time', [$windowStart, $windowEnd])
            ->get(['id', 'clock_in_time', 'clock_out_time']);

        $first = $last = null;
        $open = false;

        foreach ($rows as $r) {
            $in = Carbon::parse($r->clock_in_time, 'UTC');
            $out = $r->clock_out_time ? Carbon::parse($r->clock_out_time, 'UTC') : null;
            $open = $open || $out === null;
            $first = $first ? min($first, $in) : $in;
            $last = $last ? max($last, $out ?? $in) : ($out ?? $in);
        }

        $presence = ($first && $last) ? (int) abs($last->diffInMinutes($first)) : 0;

        $linked = $rows->isNotEmpty() && DB::table('iapp_attendance_links')
            ->whereIn('attendance_id', $rows->pluck('id'))->where('link_type', 'created')->exists();
        $source = $rows->isEmpty() ? 'none' : ($linked ? 'device' : 'web');

        // The device's own view of the day, for punch problems.
        $device = $empCode ? DB::table('iapp_attendance_records')->where('emp_id', $empCode)
            ->whereDate('attendance_date', $key)->first(['raw_punch_count', 'punch_count', 'last_out', 'first_in']) : null;

        $leave = DB::table('leaves as l')->leftJoin('leave_types as t', 't.id', '=', 'l.leave_type_id')
            ->where('l.user_id', $userId)->whereDate('l.leave_date', $key)
            ->whereIn('l.status', ['approved', 'pending'])
            ->get(['l.status', 'l.duration']);
        $approvedFull = $leave->where('status', 'approved')->where('duration', '!=', 'half day')->isNotEmpty();
        $pending = $leave->where('status', 'pending')->isNotEmpty();

        $shift = $this->shiftFor($userId, $key);

        // The device's summary is per calendar day, so for a shift that runs past
        // midnight it holds only half the shift; the attendance rows read in the
        // shift's own window above are the whole of it.
        if ($shift && !empty($shift->overnight)) {
            $device = null;
        }
        $flags = [];

        if (!$empCode) {
            $flags[] = 'not_on_device';
        }

        if (!$deviceComplete && $empCode) {
            $flags[] = 'device_incomplete';
        }

        $seen = $rows->isNotEmpty();

        if ($seen) {
            if ($presence === 0 || ($device && (int) $device->punch_count <= 1)) {
                $flags[] = 'single_punch';
            }
            elseif (($device && !$device->last_out) || $open || ($last && $last->greaterThan($windowEnd))) {
                // No exit, or a session only closed after the day's window: a
                // forgotten clock-out, which would otherwise pass for a full day.
                $flags[] = 'missing_exit';
            }
        }

        if ($first && $shift && $shift->office_start_time && $shift->late_mark_duration !== null) {
            $lateAfter = Carbon::parse($shift->day_start, $this->tz)->addMinutes((int) $shift->late_mark_duration);

            // To the minute, like ImportAttendanceJob::isLateArrival().
            if ($first->copy()->setTimezone($this->tz)->startOfMinute()->gt($lateAfter)) {
                $flags[] = 'late';
            }
        }

        if ($last && $presence > 0 && $shift && $shift->office_end_time) {
            // The shift's own end - the next morning, for an overnight shift.
            $earlyBefore = Carbon::parse($shift->day_end, $this->tz)->subMinutes((int) ($shift->late_mark_duration ?? 0));

            if ($last->copy()->setTimezone($this->tz)->lt($earlyBefore)) {
                $flags[] = 'left_early';
            }
        }

        // The data stops before this shift does (a night shift reviewed at 3 AM):
        // its end is not known yet, so its missing end is not held against it.
        // The day is judged again the following night, once the shift is over.
        $inProgress = $dataThrough && $windowEnd->gt($dataThrough->copy()->setTimezone('UTC'));

        if ($inProgress) {
            $flags = array_values(array_diff($flags, ['missing_exit', 'single_punch', 'left_early']));
            $flags[] = 'shift_in_progress';
        }

        // Half a day's approved leave covers the other half: arriving after a
        // morning off is not late, and leaving for an afternoon off is not early.
        if ($leave->where('status', 'approved')->where('duration', 'half day')->isNotEmpty()) {
            $flags = array_values(array_diff($flags, ['late', 'left_early']));
        }

        // What the day was.
        if ($breakdown['week_off'] > 0 || $breakdown['holiday'] > 0 || $holiday) {
            $status = $seen
                ? AttendanceReview::WORKED_OFF_DAY
                : ($breakdown['holiday'] > 0 || $holiday ? AttendanceReview::HOLIDAY : AttendanceReview::WEEK_OFF);
            // Nobody is late or early on a day they did not have to work.
            $flags = array_values(array_diff($flags, ['late', 'left_early']));
        }
        elseif ($approvedFull) {
            $status = $presence >= 60 ? AttendanceReview::LEAVE_WORKED : AttendanceReview::ON_LEAVE;
            $flags = array_values(array_diff($flags, ['late', 'left_early']));
        }
        elseif ($pending && !$seen) {
            $status = AttendanceReview::LEAVE_PENDING;
        }
        elseif (!$seen) {
            $status = AttendanceReview::ABSENT;
        }
        elseif ($credit >= 1.0) {
            $status = AttendanceReview::PRESENT;
        }
        elseif ($credit >= 0.5) {
            $status = AttendanceReview::HALF_DAY;
        }
        else {
            $status = AttendanceReview::SHORT_HOURS;
        }

        $quiet = [AttendanceReview::PRESENT, AttendanceReview::ON_LEAVE, AttendanceReview::WEEK_OFF, AttendanceReview::HOLIDAY];
        $flagsThatMatter = array_diff($flags, ['not_on_device', 'device_incomplete', 'shift_in_progress']);
        $isException = !in_array($status, $quiet, true) || $flagsThatMatter !== [];

        // A running shift has only part of its hours yet; short or half is not a verdict.
        if ($inProgress && $seen && in_array($status, [AttendanceReview::SHORT_HOURS, AttendanceReview::HALF_DAY], true)) {
            $isException = $flagsThatMatter !== [];
        }

        return [
            'status' => $status,
            'flags' => $flags,
            'is_exception' => $isException,
            'source' => $source,
            'first_in' => $first,
            'last_out' => $last,
            'presence_minutes' => $presence,
            'punches' => $device ? (int) $device->raw_punch_count : $rows->count(),
            'credit' => $credit,
        ];
    }

    /** The roster, the employee's own shift, or the company default (ShiftResolver). */
    protected function shiftFor(int $userId, string $date): ?object
    {
        return ShiftResolver::shared()->shiftOn($userId, $date);
    }

}
