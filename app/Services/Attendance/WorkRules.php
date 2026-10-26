<?php

namespace App\Services\Attendance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How a day's punches are judged (Attendance Settings -> Working Hours & OT).
 *
 *   day        work hours + break = the day. The shift sets it (a 09:30-18:30
 *              shift is 9h = 8h work + 1h break); without usable shift times,
 *              the setting (8h + lunch 45 + snacks 15).
 *   presence   first punch to last punch - anywhere in the day, or only inside
 *              the shift's own times (count mode).
 *   breaks     any number, each with a start and end time (attendance_breaks);
 *              a shift picks its own, or takes the default ones.
 *   worked     presence less the part of each break that fell inside it: in
 *              from 09:30 to 18:30 pays for lunch 13:00-13:45 and snacks
 *              16:00-16:15; gone at 13:00, no lunch is taken off. The device
 *              cannot tell a lunch break from a walk to the gate, so a break
 *              is its set window, not measured.
 *   credit     worked >= work - grace: a full day; >= half day - grace: half.
 *   late       first punch after the shift start + its late minutes (or one
 *              global time); after the shift's half-day mark the day is half
 *              at most, whatever the hours.
 *   overtime   weekday: time beyond work + break (or, counting inside the
 *              shift only, the time after the shift ends); week-off or
 *              holiday: everything worked. A minimum to count, a daily cap.
 *
 * Applies from wh_effective_from: days before it keep the old reckoning, so a
 * payslip already issued can never change.
 *
 * A flexible shift (shift_type = flexible) has no times that matter: presence
 * counts anywhere in the day, breaks included; a full day is its hours (9),
 * a half day its half-day hours; there is no late mark and no half-day mark;
 * weekday overtime is the time beyond its hours.
 */
class WorkRules
{

    protected static array $cache = [];

    /** @var \Illuminate\Support\Collection the company's breaks */
    public $breaks;

    public function __construct(public readonly object $s, protected string $tz, $breaks = null)
    {
        $this->breaks = collect($breaks ?? []);
    }

    public static function forCompany(?int $companyId, ?string $tz = null): ?self
    {
        $key = (int) $companyId;

        if (!array_key_exists($key, self::$cache)) {
            $row = Schema::hasColumn('attendance_settings', 'wh_count_mode')
                ? DB::table('attendance_settings')->when($companyId, fn ($q) => $q->where('company_id', $companyId))->first()
                : null;
            $zone = $tz ?: (DB::table('companies')->when($companyId, fn ($q) => $q->where('id', $companyId))->value('timezone') ?: 'Asia/Kolkata');
            $breaks = Schema::hasTable('attendance_breaks')
                ? DB::table('attendance_breaks')->when($companyId, fn ($q) => $q->where('company_id', $companyId))->orderBy('sort_order')->orderBy('start_time')->get()
                : collect();
            self::$cache[$key] = $row ? new self($row, $zone, $breaks) : null;
        }

        return self::$cache[$key];
    }

    public static function reset(): void
    {
        self::$cache = [];
    }

    public function appliesOn(string $date): bool
    {
        return $this->s->wh_effective_from !== null && $date >= substr((string) $this->s->wh_effective_from, 0, 10);
    }

    public function withinShiftOnly(): bool
    {
        return $this->s->wh_count_mode === 'within_shift';
    }

    /** Flexible hours: a full day after so many hours, any time of day. */
    public static function isFlexible(?object $shift): bool
    {
        return $shift && ($shift->shift_type ?? 'strict') === 'flexible' && (float) ($shift->flexible_total_hours ?? 0) > 0;
    }

    /** The breaks of a shift: the ones it picked, or the default ones. */
    public function breaksFor(?object $shift)
    {
        $ids = $shift && isset($shift->break_ids) && $shift->break_ids !== null && $shift->break_ids !== ''
            ? (is_array($shift->break_ids) ? $shift->break_ids : json_decode((string) $shift->break_ids, true))
            : null;

        return is_array($ids)
            ? $this->breaks->whereIn('id', array_map('intval', $ids))->values()
            : $this->breaks->where('is_default', 1)->values();
    }

    /** All of a shift's breaks, in seconds. */
    public function breakSeconds(?object $shift): int
    {
        // A flexible shift's hours include any breaks taken.
        if (self::isFlexible($shift)) {
            return 0;
        }

        return (int) $this->breaksFor($shift)->sum(fn ($b) => self::duration($b));
    }

    public static function duration(object $break): int
    {
        $start = strtotime($break->start_time);
        $end = strtotime($break->end_time);

        return $end > $start ? $end - $start : ($end + 86400 - $start);
    }

    /** How much of the shift's breaks fell between two moments (unix seconds). */
    public function breakOverlap(?object $shift, int $from, int $to, ?string $date = null): int
    {
        if ($to <= $from || self::isFlexible($shift)) {
            return 0;
        }

        $day = $this->hasTimes($shift) ? substr((string) $shift->day_start, 0, 10)
            : ($date ?: Carbon::createFromTimestamp($from, $this->tz)->toDateString());
        $shiftStart = $this->hasTimes($shift) ? strtotime($shift->office_start_time) : null;
        $total = 0;

        foreach ($this->breaksFor($shift) as $b) {
            $start = Carbon::parse($day . ' ' . $b->start_time, $this->tz);

            // On a shift past midnight a break before its start time is the next morning's.
            if ($shiftStart !== null && ShiftResolver::isOvernight($shift) && strtotime($b->start_time) < $shiftStart) {
                $start->addDay();
            }

            $end = $start->copy()->addSeconds(self::duration($b));
            $total += max(0, min($to, $end->getTimestamp()) - max($from, $start->getTimestamp()));
        }

        return $total;
    }

    /** Hours of work that make a full day: the shift's length less the break, or the setting. */
    public function workSeconds(?object $shift): int
    {
        if (self::isFlexible($shift)) {
            return (int) round((float) $shift->flexible_total_hours * 3600);
        }

        $length = $this->hasTimes($shift) ? ShiftResolver::lengthSeconds($shift) : 0;
        $work = $length - $this->breakSeconds($shift);

        return $work >= 3600 ? $work : (int) round((float) $this->s->wh_work_hours * 3600);
    }

    /** A half day, in the same proportion to the day as the setting's (4h of 8h). */
    public function halfSeconds(?object $shift): int
    {
        if (self::isFlexible($shift)) {
            $half = (float) ($shift->flexible_half_day_hours ?? 0) ?: (float) $shift->flexible_total_hours / 2;

            return (int) round($half * 3600);
        }

        $ratio = (float) $this->s->wh_work_hours > 0 ? (float) $this->s->wh_half_day_hours / (float) $this->s->wh_work_hours : 0.5;

        return (int) round($this->workSeconds($shift) * $ratio);
    }

    /** First and last punch, kept inside the shift's times if counting is limited to them. */
    public function window(int $first, int $last, ?object $shift): array
    {
        if ($this->withinShiftOnly() && $this->hasTimes($shift) && !self::isFlexible($shift)) {
            $first = max($first, Carbon::parse($shift->day_start, $this->tz)->getTimestamp());
            $last = min($last, Carbon::parse($shift->day_end, $this->tz)->getTimestamp());
        }

        return [$first, max($first, $last)];
    }

    public function presence(int $first, int $last, ?object $shift): int
    {
        [$from, $to] = $this->window($first, $last, $shift);

        return $to - $from;
    }

    /** Time in office less the part of the breaks that fell inside it. */
    public function worked(int $first, int $last, ?object $shift, ?string $date = null): int
    {
        [$from, $to] = $this->window($first, $last, $shift);

        return max(0, ($to - $from) - $this->breakOverlap($shift, $from, $to, $date));
    }

    public function credit(int $worked, ?object $shift): float
    {
        $grace = (int) $this->s->wh_grace_minutes * 60;

        if ($worked >= $this->workSeconds($shift) - $grace) {
            return 1.0;
        }

        return $worked > 0 && $worked >= $this->halfSeconds($shift) - $grace ? 0.5 : 0.0;
    }

    /** First punch after the late mark: the shift start + its late minutes, or one global time. */
    public function isLate(int $first, ?object $shift, string $date): bool
    {
        // Flexible hours: whenever they come in is fine.
        if (self::isFlexible($shift)) {
            return false;
        }

        if ($this->s->wh_late_basis === 'global' && $this->s->wh_late_time) {
            $mark = Carbon::parse($date . ' ' . $this->s->wh_late_time, $this->tz);
        } elseif ($this->hasTimes($shift) && $shift->late_mark_duration !== null) {
            $mark = Carbon::parse($shift->day_start, $this->tz)->addMinutes((int) $shift->late_mark_duration);
        } else {
            return false;
        }

        // To the minute, like the daily review.
        return Carbon::createFromTimestamp($first, $this->tz)->startOfMinute()->gt($mark);
    }

    /** Arrived after the shift's half-day mark: a half day at most. */
    public function afterHalfDayMark(int $first, ?object $shift, string $date): bool
    {
        $mark = $shift->halfday_mark_time ?? null;

        if (!$mark || $mark === '00:00:00' || !$this->hasTimes($shift) || self::isFlexible($shift)) {
            return false;
        }

        $at = Carbon::parse(substr((string) $shift->day_start, 0, 10) . ' ' . $mark, $this->tz);

        // An overnight shift's mark can fall on the next calendar day.
        if ($at->lt(Carbon::parse($shift->day_start, $this->tz))) {
            $at->addDay();
        }

        return Carbon::createFromTimestamp($first, $this->tz)->gt($at);
    }

    /**
     * Overtime seconds for the day, after the minimum and the cap.
     *
     * @param string $type 'weekday' | 'weekend' | 'holiday'
     */
    public function overtime(int $first, int $last, ?object $shift, string $type): int
    {
        if (!$this->s->ot_enabled || $last <= $first) {
            return 0;
        }

        if ($type === 'weekday') {
            if ($this->withinShiftOnly() && $this->hasTimes($shift) && !self::isFlexible($shift)) {
                $extra = $last - Carbon::parse($shift->day_end, $this->tz)->getTimestamp();
            } else {
                $extra = ($last - $first) - ($this->workSeconds($shift) + $this->breakSeconds($shift));
            }
            $cap = (float) $this->s->ot_cap_weekday_hours;
        } else {
            $extra = $this->worked($first, $last, $shift);
            $cap = (float) $this->s->ot_cap_off_hours;
        }

        if ($extra < (int) $this->s->ot_min_minutes * 60) {
            return 0;
        }

        return (int) min($extra, $cap > 0 ? $cap * 3600 : $extra);
    }

    public function rate(string $type): float
    {
        return (float) match ($type) {
            'weekend' => $this->s->ot_rate_weekend,
            'holiday' => $this->s->ot_rate_holiday,
            default => $this->s->ot_rate_weekday,
        };
    }

    /** Lates in a period that cost a deduction: every Nth late takes off 0.5 (or 1) day. */
    public function lateDeduction(int $lates, object $attendanceSetting): float
    {
        $every = max(1, (int) ($attendanceSetting->half_day_late_threshold ?? 3));
        $each = (float) ($attendanceSetting->half_day_deduction_type ?? 0.5) ?: 0.5;

        return intdiv($lates, $every) * $each;
    }

    protected function hasTimes(?object $shift): bool
    {
        return $shift && !ShiftResolver::isDayOff($shift) && $shift->office_start_time && $shift->office_end_time
            && $shift->office_start_time !== $shift->office_end_time && !empty($shift->day_start) && !empty($shift->day_end);
    }

}
