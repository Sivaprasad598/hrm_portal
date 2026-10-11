<?php

namespace App\Services\Iapp\Shifts;

use Carbon\Carbon;

/**
 * Measures one attended day against the shift that applied to it.
 *
 * Late and half-day follow the ERP's own rules (ImportAttendanceJob::
 * isLateArrival / isHalfDayArrival): late only on strict shifts, after start
 * plus the shift's late-mark grace; half day after the half-day mark time.
 * Minimum hours use the shift's full-day / half-day thresholds less their
 * grace.
 *
 * Read-only: evaluates, never writes.
 */
class ShiftCompliance
{

    public const STATUS_ON_TIME = 'on_time';
    public const STATUS_LATE = 'late';
    public const STATUS_HALF_DAY = 'half_day';
    /** Punches on a day off, week-off, holiday or full-day leave. */
    public const STATUS_OFF_DAY = 'off_day_worked';
    public const STATUS_FLEXIBLE = 'flexible';
    public const STATUS_NO_SHIFT = 'no_shift';

    public const LABELS = [
        self::STATUS_ON_TIME => 'On time',
        self::STATUS_LATE => 'Late',
        self::STATUS_HALF_DAY => 'Half day',
        self::STATUS_OFF_DAY => 'Worked off day',
        self::STATUS_FLEXIBLE => 'Flexible',
        self::STATUS_NO_SHIFT => 'No shift',
    ];

    /**
     * @param  string  $date  Y-m-d
     * @param  string|null  $firstIn  H:i:s clock time
     * @param  string|null  $lastOut  H:i:s clock time
     * @param  int  $insideMinutes  paired in/out time for the day
     * @param  array  $resolution  ShiftCalendar::resolve() result
     */
    public static function evaluate(string $date, ?string $firstIn, ?string $lastOut, int $insideMinutes, array $resolution): array
    {
        $shift = $resolution['shift'];

        $result = [
            'status' => self::STATUS_NO_SHIFT,
            'late' => false,
            'late_minutes' => 0,
            'half_day' => false,
            'early_exit' => false,
            'early_minutes' => 0,
            'overtime_minutes' => 0,
            'short_hours' => false,
            'required_minutes' => null,
            'exit_known' => $lastOut !== null,
        ];

        if (!$shift) {
            return $result;
        }

        if (!$resolution['working']) {
            // Not expected to work, so the whole worked span is extra time.
            $result['status'] = self::STATUS_OFF_DAY;
            $result['overtime_minutes'] = max(0, $insideMinutes);

            return $result;
        }

        $halfLeave = $resolution['reason'] === ShiftCalendar::REASON_HALF_LEAVE;
        $fullDay = self::requiredMinutes($shift->min_working_hours_full_day ?? null, $shift->min_working_hours_full_day_grace ?? 0);
        $halfDay = self::requiredMinutes($shift->min_working_hours_half_day ?? null, $shift->min_working_hours_half_day_grace ?? 0);

        if (($shift->shift_type ?? 'strict') === 'flexible') {
            $required = $halfLeave
                ? $halfDay
                : ((float) ($shift->flexible_total_hours ?? 0) > 0 ? (int) round($shift->flexible_total_hours * 60) : $fullDay);

            $result['status'] = self::STATUS_FLEXIBLE;
            $result['required_minutes'] = $required;
            $result['short_hours'] = $lastOut !== null && $required !== null && $insideMinutes < $required;

            return $result;
        }

        $start = $resolution['start_at'];
        $end = $resolution['end_at'];
        $in = $firstIn ? Carbon::parse($date . ' ' . $firstIn) : null;
        $out = $lastOut ? Carbon::parse($date . ' ' . $lastOut) : null;

        if ($in && $out && $out->lt($in)) {
            $out->addDay();
        }

        $result['status'] = self::STATUS_ON_TIME;

        // On a half-day leave the employee is only present for part of the
        // shift, and which half isn't reliable enough to judge arrival
        // against, so lateness is not counted.
        if ($in && $start && !$halfLeave) {
            $lateAfter = $start->copy()->addMinutes((int) ($shift->late_mark_duration ?? 0));

            if ($in->gt($lateAfter)) {
                $result['late'] = true;
                $result['late_minutes'] = (int) abs($start->diffInMinutes($in));
            }

            if (!empty($shift->halfday_mark_time)) {
                $mark = Carbon::parse($start->toDateString() . ' ' . $shift->halfday_mark_time);

                if ($in->gt($mark)) {
                    $result['half_day'] = true;
                }
            }
        }

        if ($out && $end) {
            if ($out->lt($end)) {
                $result['early_exit'] = true;
                $result['early_minutes'] = (int) abs($out->diffInMinutes($end));
            } elseif ($out->gt($end)) {
                $result['overtime_minutes'] = (int) abs($end->diffInMinutes($out));
            }
        }

        $required = $halfLeave ? $halfDay : $fullDay;
        $result['required_minutes'] = $required;
        // Without an exit punch the day's hours are unknown rather than short;
        // that day is already surfaced under Exceptions.
        $result['short_hours'] = $lastOut !== null && $required !== null && $insideMinutes < $required;

        $result['status'] = $result['half_day']
            ? self::STATUS_HALF_DAY
            : ($result['late'] ? self::STATUS_LATE : self::STATUS_ON_TIME);

        return $result;
    }

    /** Shift hours as minutes, less the grace. Null when the shift sets none. */
    protected static function requiredMinutes($hours, $grace): ?int
    {
        if ($hours === null || (float) $hours <= 0) {
            return null;
        }

        return max(0, (int) round((float) $hours * 60) - (int) $grace);
    }

}
