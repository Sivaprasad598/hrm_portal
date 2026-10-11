<?php

namespace App\Observers;

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Leave;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\EmployeeShiftSchedule;

class AttendanceObserver
{

    public function saving(Attendance $attendance)
    {
        if (user()) {
            $attendance->last_updated_by = user()->id;
        }
    }

    public function updating(Attendance $attendance)
    {
        $request = request();

        // If late/halfday are being explicitly set in the request (manual edit),
        // ensure they are not overridden by any auto-calculation logic
        // The controller already sets these values, but this ensures they're protected
        if ($request && ($request->has('late') || $request->has('halfday'))) {
            // Values are being manually set, so they will be preserved
            // No need to do anything here as the controller handles it
            // This method to prevent any future auto-calculation logic from overriding manual values
        }
    }

    public function creating(Attendance $attendance)
    {
        if (user()) {
            $attendance->added_by = user()->id;
        }

        if ($attendance->work_from_type != 'other') {
            $attendance->working_from = $attendance->work_from_type;
        }


        $attendance->company_id = $attendance->user->company_id;

        // Get company, user, and request from context
        $company = $attendance->user->company;
        $user = $attendance->user;
        $request = request();


        $now = now($company->timezone);

        $showClockIn = AttendanceSetting::where('company_id', $company->id)->first();

        // Get attendance settings for the user's shift
        $attendanceSettings = $this->attendanceShift($showClockIn, $company, $user);

        $startTimestamp = now($company->timezone)->format('Y-m-d') . ' ' . $attendanceSettings->office_start_time;
        $endTimestamp = now($company->timezone)->format('Y-m-d') . ' ' . $attendanceSettings->office_end_time;
        $officeStartTime = Carbon::createFromFormat('Y-m-d H:i:s', $startTimestamp, $company->timezone);
        $officeEndTime = Carbon::createFromFormat('Y-m-d H:i:s', $endTimestamp, $company->timezone);

        if ($showClockIn->show_clock_in_button == 'yes') {
            $officeEndTime = now($company->timezone);
        }

        // check if user has clocked in on time or not
        $lateCheckData = Attendance::whereBetween('clock_in_time', [
            $officeStartTime->copy()->timezone(config('app.timezone')),
            $officeEndTime->copy()->timezone(config('app.timezone'))
        ])
            ->where('user_id', $user->id)
            ->orderBy('clock_in_time', 'asc')
            ->first();

        $isLate = 'yes';

        if ($lateCheckData && $lateCheckData->late === 'no' || $attendanceSettings->shift_type == 'flexible') {
            // user has reached office on time ,so late check will be disabled now
            $isLate = 'no';
        }

        if ($officeStartTime->gt($officeEndTime)) {
            $officeEndTime->addDay();
        }

        $cannotLogin = false;

        if ($attendanceSettings->shift_type == 'strict') {
            $clockInCount = Attendance::getTotalUserClockInWithTime($officeStartTime, $officeEndTime, $user->id);
        } else {
            $Utc = now($company->timezone)->format('p');
            $clockInCount = Attendance::where('user_id', $user->id)
                ->whereDate(DB::raw("CONVERT_TZ(clock_in_time, '+00:00', '{$Utc}')"), now($Utc)->format('Y-m-d'))
                ->count();
        }


        if ($attendanceSettings->halfday_mark_time) {
            $halfDayTimes = Carbon::createFromFormat('Y-m-d H:i:s', $now->format('Y-m-d') . ' ' . $attendanceSettings->halfday_mark_time, $company->timezone);
        }

        $officeStartTime = Carbon::createFromFormat('Y-m-d H:i:s', $now->format('Y-m-d') . ' ' . $attendanceSettings->office_start_time, $company->timezone);

        $lateTime = $officeStartTime->copy()->addMinutes((int)$attendanceSettings->late_mark_duration ?? 0);

        $checkTodayAttendance = Attendance::where('user_id', $user->id)
            ->where(DB::raw('DATE(attendances.clock_in_time)'), '=', $now->format('Y-m-d'))->first();

        // Don't create new attendance, modify the existing one being created
        if (!isset($attendance->clock_in_time)) {
            $attendance->clock_in_time = $now->copy()->timezone(config('app.timezone'));
        }

        // Check if late/halfday are already explicitly set (manual entry)
        // If manually set, respect the user's choice and don't override
        // Check both the model attributes (set via create()) and the request (set via form)
        $lateAlreadySet = array_key_exists('late', $attendance->getAttributes()) || ($request && $request->has('late'));
        $halfDayAlreadySet = array_key_exists('half_day', $attendance->getAttributes()) || ($request && $request->has('halfday'));

        // Only auto-calculate late status if not manually set
        if (!$lateAlreadySet && $now->gt($lateTime) && $isLate === 'yes' && $attendanceSettings->shift_type == 'strict') {
            $attendance->late = 'yes';
        }

        $leave = Leave::where('leave_date', $attendance->clock_in_time->format('Y-m-d'))
            ->where('status', 'approved')
            ->where('user_id', $user->id)->first();

        // Only auto-calculate half_day if not manually set
        if (!$halfDayAlreadySet) {
            if (isset($leave) && !is_null($leave->half_day_type) && $attendanceSettings->shift_type == 'strict') {
                $attendance->half_day = 'yes';
            } else {
                // Only set to 'no' if it wasn't already set
                if (!array_key_exists('half_day', $attendance->getAttributes())) {
                    $attendance->half_day = 'no';
                }
            }
        }

        $startTimestamp = now($company->timezone)->format('Y-m-d') . ' ' . $attendanceSettings->office_start_time;
        $endTimestamp = now($company->timezone)->format('Y-m-d') . ' ' . $attendanceSettings->office_end_time;

        $officeStartTime = Carbon::createFromFormat('Y-m-d H:i:s', $startTimestamp, $company->timezone);
        $officeEndTime = Carbon::createFromFormat('Y-m-d H:i:s', $endTimestamp, $company->timezone);

        // shift crosses midnight
        if ($officeStartTime->gt($officeEndTime)) { // check if shift end time is less then current time then shift not ended yet
            if (now($company->timezone)->lessThan($officeEndTime)) {
                $officeStartTime->subDay();
            } else {
                $officeEndTime->addDay();
            }
        }

        $Utc = now($company->timezone)->format('p');

        // Fetch current clock-in record
        if ($attendanceSettings && ($attendanceSettings->show_clock_in_button == 'no' || $attendanceSettings->show_clock_in_button == null)) {
            $currentClockIn = Attendance::select('id', 'half_day', 'clock_in_time', 'clock_out_time', 'employee_shift_id')
                ->where('user_id', $user->id)
                ->where(function ($query) use ($officeStartTime, $officeEndTime, $Utc) {
                    $query->whereBetween(DB::raw("CONVERT_TZ(clock_in_time, '+00:00', '{$Utc}')"), [$officeStartTime, $officeEndTime]);
                })
                ->first();
        } else {

            $currentClockIn = Attendance::select('id', 'half_day', 'clock_in_time', 'clock_out_time', 'employee_shift_id')
                ->where('user_id', $user->id)
                ->whereDate(DB::raw("CONVERT_TZ(clock_in_time, '+00:00', '{$Utc}')"), now($Utc)->format('Y-m-d'))
                ->first();
        }

        $timeFlag = false;

        if (isset($halfDayTimes)) {
            $startTimePeriod = $officeStartTime->format('A'); // AM or 'PM'
            $halfdayPeriod = $halfDayTimes->format('A'); // Assume $halfday is a Carbon instance or similarly formatted

            // First clock in happened and on time
            if ($currentClockIn && $currentClockIn->half_day == 'no') {
                $timeFlag = false;
            } else {
                $isSameDay = $officeStartTime->isSameDay(now($company->timezone));
                $isNowAfterHalfDayTimes = now($company->timezone)->gt($halfDayTimes);

                if ($startTimePeriod === 'PM' && $halfdayPeriod === 'AM') {
                    // Half day exists in the first half of the next day
                    $timeFlag = ($officeEndTime->isSameDay(now($company->timezone)) && $isNowAfterHalfDayTimes);
                } else if ($startTimePeriod === 'AM' && $halfdayPeriod === 'PM') {
                    // Half day exists in the second half of the same day
                    $timeFlag = ($isSameDay && $isNowAfterHalfDayTimes);
                } else if (($startTimePeriod === 'PM' && $halfdayPeriod === 'PM') || ($startTimePeriod === 'AM' && $halfdayPeriod === 'AM')) {
                    // Same day or next day depending on the start time
                    if ($officeStartTime->gt($halfDayTimes)) {
                        // Next day scenario
                        $timeFlag = ($officeEndTime->isSameDay(now($company->timezone)) && $isNowAfterHalfDayTimes);
                    } else {
                        // Same day scenario
                        $timeFlag = ($isSameDay && $isNowAfterHalfDayTimes);
                    }
                }
            }
        }


        // Check day's first record and half day time
        // Only auto-set half_day if not manually set
        if (
            !$halfDayAlreadySet &&
            isset($halfDayTimes) &&
            now($company->timezone)->gt($halfDayTimes) &&
            !is_null($attendanceSettings->halfday_mark_time)
            && is_null($checkTodayAttendance)
            && $timeFlag
            // && ($now->gt($halfDayTimes))
            && ($showClockIn->show_clock_in_button == 'no') // DO NOT allow half day when allowed outside hours clock-in
            && $attendanceSettings->shift_type == 'strict'
        ) {
            $attendance->half_day = 'yes';
        }

        $attendance->employee_shift_id = $attendanceSettings->id;

        $attendance->shift_start_time = $attendance->clock_in_time->format('Y-m-d') . ' ' . $attendanceSettings->office_start_time;

        if (Carbon::parse($attendanceSettings->office_start_time, $company->timezone)->gt(Carbon::parse($attendanceSettings->office_end_time, $company->timezone))) {
            $attendance->shift_end_time = $attendance->clock_in_time->copy()->addDay()->format('Y-m-d') . ' ' . $attendanceSettings->office_end_time;
        } else {
            $attendance->shift_end_time = $attendance->clock_in_time->format('Y-m-d') . ' ' . $attendanceSettings->office_end_time;
        }
    }

    /**
     * Get attendance shift for user
     */
    public function attendanceShift($defaultAttendanceSettings, $company, $user)
    {
        $checkPreviousDayShift = EmployeeShiftSchedule::with('shift')->where('user_id', $user->id)
            ->where('date', now($company->timezone)->subDay()->toDateString())
            ->first();

        $checkTodayShift = EmployeeShiftSchedule::with('shift')->where('user_id', $user->id)
            ->where('date', now($company->timezone)->toDateString())
            ->first();

        $backDayFromDefault = Carbon::parse(now($company->timezone)->subDay()->format('Y-m-d') . ' ' . $defaultAttendanceSettings->office_start_time, $company->timezone);

        $backDayToDefault = Carbon::parse(now($company->timezone)->subDay()->format('Y-m-d') . ' ' . $defaultAttendanceSettings->office_end_time, $company->timezone);

        if ($backDayFromDefault->gt($backDayToDefault)) {
            $backDayToDefault->addDay();
        }

        $nowTime = Carbon::createFromFormat('Y-m-d H:i:s', now($company->timezone)->toDateTimeString(), 'UTC');

        if ($checkPreviousDayShift && $nowTime->betweenIncluded($checkPreviousDayShift->shift_start_time, $checkPreviousDayShift->shift_end_time)) {
            $attendanceSettings = $checkPreviousDayShift;
        } else if ($nowTime->betweenIncluded($backDayFromDefault, $backDayToDefault)) {
            $attendanceSettings = $defaultAttendanceSettings;
        } else if (
            $checkTodayShift &&
            ($nowTime->betweenIncluded($checkTodayShift->shift_start_time, $checkTodayShift->shift_end_time)
                || $nowTime->gt($checkTodayShift->shift_end_time)
                || (!$nowTime->betweenIncluded($checkTodayShift->shift_start_time, $checkTodayShift->shift_end_time) && $defaultAttendanceSettings->show_clock_in_button == 'no'))
        ) {
            $attendanceSettings = $checkTodayShift;
        } else if ($checkTodayShift && !is_null($checkTodayShift->shift->early_clock_in)) {
            $attendanceSettings = $checkTodayShift;
        } else if ($checkTodayShift && $checkTodayShift->shift->shift_type == 'flexible') {
            $attendanceSettings = $checkTodayShift;
        } else {
            $attendanceSettings = $defaultAttendanceSettings;
        }


        if (isset($attendanceSettings->shift)) {
            return $attendanceSettings->shift;
        }

        return $attendanceSettings;
    }
}
