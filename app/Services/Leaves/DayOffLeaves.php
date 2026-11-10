<?php

namespace App\Services\Leaves;

use App\Models\EmployeeDetails;
use App\Models\Holiday;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A day that becomes a holiday or a "Day Off" is not a working day, so leave
 * applied for it earlier must not be counted: the CL stays in the balance,
 * the calendar shows the day off, and payroll pays the day (it already
 * treats holidays and Day Off that way).
 *
 * Such leave is withdrawn when the day is declared - set to rejected with the
 * reason, quietly (no "leave rejected" mail: nobody turned it down) - and the
 * employee's leave balance is worked out again. Leave cannot be applied for a
 * Day Off either (holidays were already refused).
 */
class DayOffLeaves
{
    /** Shift named this is a day off (see ShiftResolver::isDayOff). */
    public const DAY_OFF = 'day off';

    /**
     * Withdraw the approved or pending leave these employees have on this
     * date. Returns how many leave days were withdrawn.
     */
    public static function release(array $userIds, string $date, string $why): int
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));

        if (!$userIds) {
            return 0;
        }

        $leaves = DB::table('leaves')
            ->whereIn('user_id', $userIds)
            ->whereDate('leave_date', $date)
            ->whereIn('status', ['approved', 'pending'])
            ->get(['id', 'company_id', 'user_id', 'leave_type_id']);

        if ($leaves->isEmpty()) {
            return 0;
        }

        DB::table('leaves')->whereIn('id', $leaves->pluck('id'))->update([
            'status' => 'rejected',
            'reject_reason' => 'Not counted: ' . $why . ' on this date, so no leave is needed.',
            'updated_at' => now(),
        ]);

        foreach ($leaves->unique(fn ($l) => $l->company_id . '-' . $l->user_id . '-' . $l->leave_type_id) as $leave) {
            try {
                Artisan::call('app:recalculate-leaves-quotas', [
                    'company' => $leave->company_id, 'user' => $leave->user_id, 'leaveType' => $leave->leave_type_id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('DayOffLeaves: balance not recalculated for user ' . $leave->user_id . ': ' . $e->getMessage());
            }
        }

        \App\Services\Payroll\AttendanceDays::forgetShared();

        Log::info('DayOffLeaves: ' . $leaves->count() . ' leave day(s) withdrawn on ' . $date . ' (' . $why . ')', ['leave_ids' => $leaves->pluck('id')->all()]);

        return $leaves->count();
    }

    /** A holiday was declared (or moved): leave on it for those it applies to. */
    public static function forHoliday(Holiday $holiday): int
    {
        if (!$holiday->date) {
            return 0;
        }

        $date = $holiday->date instanceof \DateTimeInterface ? $holiday->date->format('Y-m-d') : substr((string) $holiday->date, 0, 10);

        $userIds = DB::table('leaves')
            ->where('company_id', $holiday->company_id)
            ->whereDate('leave_date', $date)
            ->whereIn('status', ['approved', 'pending'])
            ->pluck('user_id')->unique();

        // Only those the holiday covers (its departments, designations, employment types)
        $covered = EmployeeDetails::withoutGlobalScopes()->whereIn('user_id', $userIds)->get()
            ->filter(fn ($detail) => Holiday::withoutGlobalScopes()->whereKey($holiday->id)->applicableTo($detail)->exists())
            ->pluck('user_id')->all();

        return self::release($covered, $date, 'the holiday "' . ($holiday->occassion ?: 'Holiday') . '"');
    }

    /** A shift was put on an employee's day: if it is "Day Off", leave on it goes. */
    public static function forShift(int $userId, string $date, ?int $shiftId): int
    {
        if (!$shiftId || !self::isDayOffShift($shiftId)) {
            return 0;
        }

        return self::release([$userId], substr($date, 0, 10), 'a Day Off');
    }

    /** Of these dates (Y-m-d), the ones this employee has a Day Off on. */
    public static function dayOffDates(int $userId, iterable $dates): array
    {
        $dates = collect($dates)->map(fn ($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : substr((string) $d, 0, 10))->all();

        if (!$dates) {
            return [];
        }

        return DB::table('employee_shift_schedules as s')
            ->join('employee_shifts as e', 'e.id', '=', 's.employee_shift_id')
            ->where('s.user_id', $userId)
            ->whereIn(DB::raw('DATE(s.date)'), $dates)
            ->whereRaw('LOWER(TRIM(e.shift_name)) = ?', [self::DAY_OFF])
            ->pluck('s.date')
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->unique()->values()->all();
    }

    protected static function isDayOffShift(int $shiftId): bool
    {
        return strtolower(trim((string) DB::table('employee_shifts')->where('id', $shiftId)->value('shift_name'))) === self::DAY_OFF;
    }
}
