<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\EmployeeShift;
use App\Models\EmployeeShiftSchedule;
use Carbon\Carbon;

/**
 * A month's punches and day-offs for the attendance reports (Total Monthly,
 * Monthly sheet, Annual), read as plain rows.
 *
 * The reports each loaded the month as Attendance models: every punch became a
 * model with its company loaded, two date objects and a computed attribute -
 * tens of thousands of them a month, twelve months for the annual report -
 * which was most of those pages' time. The queries here are the same ones
 * (same conditions, same scopes, same order), and the figures are worked out
 * the same way; only the objects around the rows are gone.
 */
class MonthPunches
{

    /**
     * Each keyed [userId][day of month]:
     *  0: seconds worked - every session of the day that has a clock-out, added up
     *  1: the shift on the day's last-read session (an EmployeeShift, or null)
     *  2: the day's first clock-in, unix seconds (first in the order read)
     *  3: whether that first session was worked from home
     *
     * @param  iterable $employeeIds
     * @param  bool $ordered  read in clock-in order (the annual report does)
     */
    public static function load($employeeIds, Carbon $monthStart, Carbon $monthEnd, string $timezone, int $year, int $month, bool $ordered = false): array
    {
        $query = Attendance::whereIn('user_id', $employeeIds)
            ->whereBetween('clock_in_time', [$monthStart->copy()->timezone('UTC'), $monthEnd->copy()->timezone('UTC')]);

        if ($ordered) {
            $query->orderBy('clock_in_time');
        }

        $rows = $query->toBase()->get();

        // the shifts those punches name, as the "shift" relation loaded them
        $shiftIds = $rows->pluck('employee_shift_id')->filter()->unique()->values();
        $shifts = $shiftIds->isEmpty() ? collect() : EmployeeShift::whereIn('id', $shiftIds)->get()->keyBy('id');

        // stored times are read in the application's time zone, as the model's date cast does
        $stored = new \DateTimeZone(date_default_timezone_get());
        $local = new \DateTimeZone($timezone);

        $worked = $shiftOn = $firstIn = $firstFromHome = [];

        foreach ($rows as $row) {
            $in = new \DateTime((string) $row->clock_in_time, $stored);
            $at = (clone $in)->setTimezone($local);

            if ((int) $at->format('Y') !== $year || (int) $at->format('n') !== $month) {
                continue;
            }

            $day = (int) $at->format('j');
            $shiftOn[$row->user_id][$day] = $row->employee_shift_id ? ($shifts[$row->employee_shift_id] ?? null) : null;

            if (!isset($firstIn[$row->user_id][$day])) {
                $firstIn[$row->user_id][$day] = $in->getTimestamp();
                $firstFromHome[$row->user_id][$day] = $row->work_from_type === 'home';
            }

            if ($row->clock_out_time) {
                $seconds = abs((new \DateTime((string) $row->clock_out_time, $stored))->getTimestamp() - $in->getTimestamp());
                $worked[$row->user_id][$day] = ($worked[$row->user_id][$day] ?? 0) + $seconds;
            }
        }

        return [$worked, $shiftOn, $firstIn, $firstFromHome];
    }

    /** [userId][day of month] => true where the shift roster says "Day Off". */
    public static function dayOffs($employeeIds, Carbon $monthStart, Carbon $monthEnd): array
    {
        $rows = EmployeeShiftSchedule::whereIn('user_id', $employeeIds)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->toBase()->get();

        $shiftIds = $rows->pluck('employee_shift_id')->filter()->unique()->values();
        $dayOffShifts = $shiftIds->isEmpty() ? [] : EmployeeShift::whereIn('id', $shiftIds)->get()
            ->filter(fn ($shift) => $shift->shift_name === 'Day Off')->keyBy('id')->all();

        $dayOff = [];

        foreach ($rows as $row) {
            if ($row->employee_shift_id && isset($dayOffShifts[$row->employee_shift_id])) {
                // the roster's date is a calendar date: its day of the month
                $dayOff[$row->user_id][(int) substr((string) $row->date, 8, 2)] = true;
            }
        }

        return $dayOff;
    }

}
