<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use Illuminate\Support\Facades\DB;

/**
 * Whether the signed-in person is currently clocked in, for the topbar.
 *
 * The dashboard works this out as part of a much larger query it runs anyway.
 * The topbar renders on every page in the account area, so it needs the same
 * answer from one cheap lookup instead.
 *
 * Read-only. Nothing here decides whether a punch is *allowed* — the clock-in
 * modal and storeClockIn() still make that call, with the shift, radius, IP and
 * daily-cap rules they already apply. This only decides which icon to draw.
 */
class ClockState
{

    /**
     * @return array{show: bool, clockedIn: bool, attendanceId: ?int}
     */
    public function current(): array
    {
        $blank = ['show' => false, 'clockedIn' => false, 'attendanceId' => null];

        $user = user();

        if (!$user) {
            return $blank;
        }

        // Clients and anyone else without an employee role have no attendance.
        if (!in_array('employee', user_roles()) && !in_array('admin', user_roles())) {
            return $blank;
        }

        $settings = DB::table('attendance_settings')
            ->where('company_id', $user->company_id)
            ->first(['employee_clock_in_out']);

        if (!$settings || $settings->employee_clock_in_out !== 'yes') {
            return $blank;
        }

        // An open session is one with no clock-out. Taking the most recent
        // matches what the dashboard's Clock Out button acts on.
        $open = Attendance::where('user_id', $user->id)
            ->whereNull('clock_out_time')
            ->orderByDesc('clock_in_time')
            ->first(['id']);

        return [
            'show' => true,
            'clockedIn' => $open !== null,
            'attendanceId' => $open?->id,
        ];
    }

}
