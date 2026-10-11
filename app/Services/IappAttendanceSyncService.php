<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\EmployeeDetails;
use Carbon\Carbon;

class IappAttendanceSyncService
{

    protected IappService $iapp;

    protected bool $wasRecentlyCreated = false;

    public function __construct(IappService $iapp)
    {
        $this->iapp = $iapp;
    }

    /**
     * Pull iApp attendance for a date range and upsert it into the local
     * `attendances` table, one row per employee per day.
     *
     * Employees are matched via employee_details.employee_id = iApp's empId.
     * Records with no firstIn (absent/leave/weekoff/holiday — no actual
     * punch) are skipped, since attendances.clock_in_time is required.
     *
     * @param string $fromDate 'DD-MM-YYYY'
     * @param string $toDate 'DD-MM-YYYY'
     */
    public function sync(string $fromDate, string $toDate, ?string $locationCode = null): array
    {
        $records = $this->iapp->getAllAttendance($fromDate, $toDate, $locationCode);

        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'unmatched' => []];

        foreach ($records as $record) {
            $empId = $record['empId'] ?? null;
            $firstIn = $record['firstIn'] ?? null;

            if (!$empId || !$firstIn) {
                $summary['skipped']++;
                continue;
            }

            $employeeDetail = EmployeeDetails::where('employee_id', $empId)->first();

            if (!$employeeDetail) {
                $summary['unmatched'][] = $empId;
                continue;
            }

            $this->upsert($employeeDetail, $record, $firstIn);

            $summary[$this->wasRecentlyCreated ? 'created' : 'updated']++;
        }

        $summary['unmatched'] = array_values(array_unique($summary['unmatched']));

        return $summary;
    }

    protected function upsert(EmployeeDetails $employeeDetail, array $record, string $firstIn): Attendance
    {
        $clockInTime = Carbon::createFromFormat('d-m-Y H:i', $firstIn);
        $clockOutTime = !empty($record['lastOut'])
            ? Carbon::createFromFormat('d-m-Y H:i', $record['lastOut'])
            : null;

        $attendance = Attendance::where('user_id', $employeeDetail->user_id)
            ->whereDate('clock_in_time', $clockInTime->toDateString())
            ->first();

        $isHalfDay = (float) ($record['leaveQty'] ?? 0) === 0.5;
        $isLate = !empty($record['lateHours']) && $record['lateHours'] !== '00:00';

        $attributes = [
            'company_id' => $employeeDetail->company_id,
            'user_id' => $employeeDetail->user_id,
            'clock_in_time' => $clockInTime,
            'clock_out_time' => $clockOutTime,
            // iApp reports a daily summary, not a per-device IP — there is
            // none to record, and attendances.clock_in_ip is required.
            'clock_in_ip' => $attendance->clock_in_ip ?? '0.0.0.0',
            'working_from' => 'office',
            'work_from_type' => 'office',
            'late' => $isLate ? 'yes' : 'no',
            'half_day' => $isHalfDay ? 'yes' : 'no',
        ];

        if ($attendance) {
            $attendance->fill($attributes)->save();
            $this->wasRecentlyCreated = false;

            return $attendance;
        }

        $attendance = Attendance::create($attributes);
        $this->wasRecentlyCreated = true;

        return $attendance;
    }

}
