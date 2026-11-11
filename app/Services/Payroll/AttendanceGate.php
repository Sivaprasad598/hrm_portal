<?php

namespace App\Services\Payroll;

use App\Services\Iapp\Pipeline\PipelineStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether an employee's payslip for a pay cycle may be issued yet, as far as
 * attendance goes. Payroll Run (PayslipWriter) and Generate One Payslip
 * (payslips.php) both ask here, so the two cannot apply different rules.
 *
 *  - The cycle must be over. Always.
 *  - "Use biometric attendance for payroll" on: for someone on a pulled device,
 *    the device feed must reach the cycle's last day (HR may "Issue anyway").
 *    Off: the device is not waited on; the attendance already in the system is
 *    what is paid.
 *  - No attendance in the system at all, no approved leave, and not marked
 *    "Attendance exempt": not paid - a whole month of loss of pay is almost
 *    always missing data, not a month away. With the setting on, someone on a
 *    device is let through here, since their device days are pushed in first.
 */
class AttendanceGate
{

    /** Payroll Settings -> "Use biometric attendance for payroll" (on unless switched off). */
    public static function useBiometric(?int $companyId = null): bool
    {
        if (!Schema::hasTable('payroll_options')) {
            return true;
        }

        $value = DB::table('payroll_options')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('id')
            ->value('use_biometric_attendance');

        return $value === null ? true : (bool) $value;
    }

    public static function setUseBiometric(?int $companyId, bool $on): void
    {
        $existing = DB::table('payroll_options')->where('company_id', $companyId)->first(['id']);

        $existing
            ? DB::table('payroll_options')->where('id', $existing->id)->update(['use_biometric_attendance' => $on, 'updated_at' => now()])
            : DB::table('payroll_options')->insert(['company_id' => $companyId, 'use_biometric_attendance' => $on,
                'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Why this payslip cannot be issued yet, or null.
     *
     * @return array{0: string, 1: bool}|null  [reason, whether HR's "Issue anyway" may pass it]
     */
    public function blocker(int $userId, PayPeriod $cycle, ?int $companyId = null, ?string $name = null): ?array
    {
        $pipeline = new PipelineStatus();
        $useDevice = self::useBiometric($companyId);
        $who = $name !== null && $name !== '' ? $name . ': ' : '';

        if ($reason = $pipeline->payslipBlocker($userId, $cycle)) {
            $deviceLag = str_starts_with($reason, 'device attendance');

            // The device feed is only waited on when payroll uses it.
            if (!$deviceLag || $useDevice) {
                return [$who . $reason, $deviceLag];
            }
        }

        $exempt = (bool) DB::table('employee_details')->where('user_id', $userId)->value('attendance_exempt');

        if ($exempt || ($useDevice && $pipeline->deviceCodeFor($userId))) {
            return null;
        }

        $hasAttendance = DB::table('attendances')->where('user_id', $userId)
            ->whereBetween('clock_in_time', [$cycle->start->copy()->startOfDay(), $cycle->end->copy()->endOfDay()])->exists();
        $hasLeave = DB::table('leaves')->where('user_id', $userId)->where('status', 'approved')
            ->whereBetween('leave_date', [$cycle->start->toDateString(), $cycle->end->toDateString()])->exists();

        if ($hasAttendance || $hasLeave) {
            return null;
        }

        return [$who . 'no attendance recorded for ' . $cycle->describe()
            . ($useDevice ? ' (not on a biometric device that is pulled, and none entered)' : ' in the system')
            . '. Mark their attendance, or if they do not record attendance at all, tick'
            . ' "Attendance exempt" on their employee form to pay them in full.', false];
    }

}
