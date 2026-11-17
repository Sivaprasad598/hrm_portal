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

    /**
     * Worked out once per request each: the same shift start, shift end and break
     * times are asked about for every employee and every day, and each answer
     * built several date objects (tens of thousands for one month's page).
     */
    protected array $atMemo = [];
    protected array $breaksMemo = [];
    protected array $breakWindowMemo = [];
    protected array $lateMarkMemo = [];
    protected array $halfMarkMemo = [];
    protected ?bool $wholeMinuteZone = null;

    /** A local 'Y-m-d H:i:s' in this company's time zone, as unix seconds. */
    protected function at(string $local): int
    {
        return $this->atMemo[$local] ??= Carbon::parse($local, $this->tz)->getTimestamp();
    }

    public static function forCompany(?int $companyId, ?string $tz = null): ?self
    {
        $key = (int) $companyId;

        if (!array_key_exists($key, self::$cache)) {
            $row = \App\Support\SchemaCache::hasColumn('attendance_settings', 'wh_count_mode')
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

        // the same list for the same choice of breaks: filtered once
        return $this->breaksMemo[is_array($ids) ? 'ids:' . json_encode($ids) : 'default'] ??= is_array($ids)
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
            // On a shift past midnight a break before its start time is the next morning's.
            $nextDay = $shiftStart !== null && ShiftResolver::isOvernight($shift) && strtotime($b->start_time) < $shiftStart;

            // The break's start and end on that day (unix seconds): the same for
            // everyone on that day, so worked out once.
            [$breakStart, $breakEnd] = $this->breakWindowMemo[$day . '|' . $b->start_time . '|' . $b->end_time . '|' . ($nextDay ? 1 : 0)]
                ??= (function () use ($day, $b, $nextDay) {
                    $start = Carbon::parse($day . ' ' . $b->start_time, $this->tz);

                    if ($nextDay) {
                        $start->addDay();
                    }

                    $end = $start->copy()->addSeconds(self::duration($b));

                    return [$start->getTimestamp(), $end->getTimestamp()];
                })();

            $total += max(0, min($to, $breakEnd) - max($from, $breakStart));
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
            $first = max($first, $this->at($shift->day_start));
            $last = min($last, $this->at($shift->day_end));
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

    /**
     * The day's credit from the punches themselves (what payroll uses).
     *
     * On top of the hours (credit()), two cases the hours alone got wrong:
     * - In by the late mark and there until the shift ends is a full day. The
     *   late allowance (20 min) is longer than the hours' grace (10 min), so
     *   someone in at 09:45 who stayed to 18:30 had 7h45 and was paid a half.
     * - A half day needs the work time of the shorter half of the shift (to
     *   or from its half-day mark), not more: 09:30-14:00 less a 45-minute
     *   lunch is 3h45, and a 4h half day could never be earned in the morning.
     */
    public function dayCredit(int $first, int $last, int $worked, ?object $shift, string $date): float
    {
        $credit = $this->credit($worked, $shift);

        if ($credit >= 1.0 || self::isFlexible($shift) || !$this->hasTimes($shift)) {
            return $credit;
        }

        $grace = (int) $this->s->wh_grace_minutes * 60;
        $end = $this->at($shift->day_end);

        if ($last >= $end - $grace && !$this->isLate($first, $shift, $date)) {
            return 1.0;
        }

        if ($credit < 0.5 && $worked > 0 && $worked >= $this->shorterHalfSeconds($shift) - $grace) {
            return 0.5;
        }

        return $credit;
    }

    /** Work time of the shorter half of a timed shift: start to half-day mark, or mark to end. */
    public function shorterHalfSeconds(?object $shift): int
    {
        $start = Carbon::parse($shift->day_start, $this->tz);
        $end = Carbon::parse($shift->day_end, $this->tz);
        $mark = $shift->halfday_mark_time ?? null;

        if ($mark && $mark !== '00:00:00') {
            $split = Carbon::parse($start->toDateString() . ' ' . $mark, $this->tz);

            if ($split->lt($start)) {
                $split->addDay();
            }
        } else {
            $split = $start->copy()->addSeconds(intdiv($end->getTimestamp() - $start->getTimestamp(), 2));
        }

        if ($split->lte($start) || $split->gte($end)) {
            return $this->halfSeconds($shift);
        }

        $date = $start->toDateString();
        $morning = ($split->getTimestamp() - $start->getTimestamp()) - $this->breakOverlap($shift, $start->getTimestamp(), $split->getTimestamp(), $date);
        $afternoon = ($end->getTimestamp() - $split->getTimestamp()) - $this->breakOverlap($shift, $split->getTimestamp(), $end->getTimestamp(), $date);

        return (int) min($this->halfSeconds($shift), max(0, min($morning, $afternoon)));
    }

    /** First punch after the late mark: the shift start + its late minutes, or one global time. */
    public function isLate(int $first, ?object $shift, string $date): bool
    {
        // Flexible hours: whenever they come in is fine.
        if (self::isFlexible($shift)) {
            return false;
        }

        // The late mark as unix seconds: the same for everyone on that shift and day.
        if ($this->s->wh_late_basis === 'global' && $this->s->wh_late_time) {
            $mark = $this->lateMarkMemo['g|' . $date . ' ' . $this->s->wh_late_time]
                ??= Carbon::parse($date . ' ' . $this->s->wh_late_time, $this->tz)->getTimestamp();
        } elseif ($this->hasTimes($shift) && $shift->late_mark_duration !== null) {
            $mark = $this->lateMarkMemo['s|' . $shift->day_start . '|' . (int) $shift->late_mark_duration]
                ??= Carbon::parse($shift->day_start, $this->tz)->addMinutes((int) $shift->late_mark_duration)->getTimestamp();
        } else {
            return false;
        }

        // To the minute, like the daily review. Where the zone's offset is a whole
        // number of minutes (every zone in use today) the start of the punch's
        // minute is plain arithmetic; otherwise it is asked of the calendar as before.
        $this->wholeMinuteZone ??= ((new \DateTimeZone($this->tz))->getOffset(new \DateTime('@' . $first)) % 60 === 0);

        if ($this->wholeMinuteZone) {
            return ($first - ($first % 60)) > $mark;
        }

        return Carbon::createFromTimestamp($first, $this->tz)->startOfMinute()->getTimestamp() > $mark;
    }

    /** Arrived after the shift's half-day mark: a half day at most. */
    public function afterHalfDayMark(int $first, ?object $shift, string $date): bool
    {
        $mark = $shift->halfday_mark_time ?? null;

        if (!$mark || $mark === '00:00:00' || !$this->hasTimes($shift) || self::isFlexible($shift)) {
            return false;
        }

        // The mark as unix seconds: the same for everyone on that shift and day.
        $at = $this->halfMarkMemo[$shift->day_start . '|' . $mark] ??= (function () use ($shift, $mark) {
            $at = Carbon::parse(substr((string) $shift->day_start, 0, 10) . ' ' . $mark, $this->tz);

            // An overnight shift's mark can fall on the next calendar day.
            if ($at->lt(Carbon::parse($shift->day_start, $this->tz))) {
                $at->addDay();
            }

            return $at->getTimestamp();
        })();

        return $first > $at;
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
                $extra = $last - $this->at($shift->day_end);
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
