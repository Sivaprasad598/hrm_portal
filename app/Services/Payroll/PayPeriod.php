<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The stretch of days one payslip covers.
 *
 * A calendar month by default. Where the company's cycle starts on another day
 * — the 21st, say — the period for September runs 21 August to 20 September,
 * which is the convention when payroll is cut before month end.
 *
 * Everything that needs to know a period's dates asks here, so the estimate,
 * the payslip and the attendance count cannot disagree about where a month
 * begins.
 */
class PayPeriod
{

    public function __construct(
        public Carbon $start,
        public Carbon $end,
        public string $label,
        public int $month,
        public int $year,
    ) {
    }

    /**
     * The period a given payroll month refers to.
     */
    public static function forMonth(int $month, int $year, ?int $startDay = null): self
    {
        $startDay = $startDay ?? static::configuredStartDay();
        $anchor = Carbon::create($year, $month, 1)->startOfDay();

        if ($startDay <= 1) {
            return new self($anchor->copy(), $anchor->copy()->endOfMonth(), $anchor->format('F - Y'), $month, $year);
        }

        // The cycle ends the day before that date in the payroll month, and runs
        // from the same date a month earlier.
        $end = $anchor->copy()->day(min($startDay, $anchor->daysInMonth))->subDay();
        $start = $end->copy()->addDay()->subMonth();

        return new self($start, $end, $anchor->format('F - Y'), $month, $year);
    }

    /** The day of the month a cycle starts on; 1 for calendar months. */
    public static function configuredStartDay(): int
    {
        try {
            $day = (int) DB::table('payroll_calculations')->orderByDesc('id')->value('period_start_day');
        } catch (\Throwable) {
            $day = 1;
        }

        return ($day >= 1 && $day <= 28) ? $day : 1;
    }

    public static function isCalendarMonth(): bool
    {
        return static::configuredStartDay() <= 1;
    }

    /** How the period reads on screen: "August 2026", or the dates when it is not a month. */
    public function describe(): string
    {
        if ($this->start->isSameMonth($this->end) && $this->start->day === 1) {
            return $this->start->format('F Y');
        }

        return $this->start->format('d M') . ' – ' . $this->end->format('d M Y');
    }

    public function days(): int
    {
        return $this->start->diffInDays($this->end) + 1;
    }

}
