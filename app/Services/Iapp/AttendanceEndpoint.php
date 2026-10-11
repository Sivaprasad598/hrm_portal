<?php

namespace App\Services\Iapp;

use Carbon\Carbon;

/**
 * attd/getDetail — day-wise attendance summary.
 *
 * Availability: this endpoint returns HTTP 404 on the server at 10.10.12.50,
 * which means the Attendance module is not installed or licensed there. The
 * client is written to the published contract so it works if the module is
 * enabled later; until then isAvailable() reports false and the punch feed
 * (DeviceTransEndpoint) is the attendance source.
 *
 * Unlike deviceTrans, this endpoint is documented as using ge/le, so it is
 * built with the docs dialect. If it is ever enabled and rejects those with
 * "Invalid Parameter Operator!", switch to IappFilter::strictDialect().
 */
class AttendanceEndpoint
{

    public const PATH = 'api/attd/getDetail';

    /** Every column documented as selectable. */
    public const FIELDS = [
        'companyCode', 'locationCode', 'attendanceDate', 'empId', 'employeeName',
        'firstIn', 'lastOut', 'workHour', 'extraHours', 'overTimeHours',
        'shiftCode', 'shiftStartTime', 'shiftEndTime', 'shiftDescription',
        'leaveCode', 'leaveQty', 'weekOff', 'holiday', 'odHours', 'tour',
        'absent', 'present', 'lateHours', 'earlyHours', 'lessWorkingHours',
        'attendanceRemark',
    ];

    /**
     * Three fields are named differently in a filter than in select. Using the
     * select name in a filter returns error 04.
     */
    public const FILTER_ALIASES = [
        'tour' => 'tourDays',
        'absent' => 'absentDays',
        'present' => 'presentDays',
    ];

    public function __construct(
        protected IappClient $client
    ) {
    }

    /**
     * One page of attendance for a date range.
     */
    public function forRange(
        Carbon $from,
        Carbon $to,
        ?string $locationCode = null,
        int $page = 1,
        int $pageSize = 1000,
        array $fields = self::FIELDS
    ): IappResponse {
        $filter = IappFilter::docsDialect();

        if ($locationCode) {
            $filter->equals('locationCode', $locationCode);
        }

        // Inclusive range: gt/lt would silently drop the first and last day.
        $filter->dateBetween('attendanceDate', $from, $to);

        return $this->client->get(self::PATH, [
            'select' => implode(',', $fields),
            'filter' => $filter->build(),
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
    }

    /**
     * Every page for a date range.
     *
     * This endpoint does report totalPages, unlike deviceTrans, so ordinary
     * page numbers are used. totalPages is taken from the response rather
     * than derived — the documented example's own arithmetic disagrees with
     * its record count.
     *
     * @return \Generator<int, array<int, array>>
     */
    public function stream(Carbon $from, Carbon $to, ?string $locationCode = null, int $maxPages = 200): \Generator
    {
        $page = 1;
        $totalPages = 1;

        do {
            $response = $this->forRange($from, $to, $locationCode, $page);

            if (!$response->successful()) {
                // 101 means the query was valid and matched nothing.
                if ($response->isEmptyResult()) {
                    return;
                }

                throw new \RuntimeException(
                    'iApp attd/getDetail failed: ' . ($response->message() ?: 'unknown error')
                );
            }

            $rows = $response->rows();

            if ($rows === []) {
                return;
            }

            yield $rows;

            $totalPages = (int) ($response->raw()['totalPages'] ?? 1);
            $page++;
        } while ($page <= min($totalPages, $maxPages));
    }

    /**
     * Whether the Attendance module answers on this server.
     *
     * Cheap probe: a one-row request for today. A 404 means the module is not
     * present, which is the current state at this site.
     */
    public function isAvailable(): bool
    {
        $response = $this->forRange(now(), now(), null, 1, 1, ['empId', 'attendanceDate']);

        if ($response->status() === 404) {
            return false;
        }

        return $response->successful() || $response->isEmptyResult();
    }

    /**
     * Normalise a getDetail row into iapp_attendance_records columns.
     */
    public static function normalise(array $row): ?array
    {
        $date = IappHelper::parseDateTime(IappResponse::scalar($row, 'attendanceDate'));

        if (!$date) {
            return null;
        }

        $workHour = IappResponse::scalar($row, 'workHour');
        $overTime = IappResponse::scalar($row, 'overTimeHours');
        $lateHours = IappResponse::scalar($row, 'lateHours');

        return [
            'emp_id' => IappResponse::scalar($row, 'empId') ?? '',
            'employee_name' => IappResponse::scalar($row, 'employeeName'),
            'location_code' => IappResponse::scalar($row, 'locationCode'),
            'attendance_date' => $date->toDateString(),
            'first_in' => IappHelper::parseDateTime(IappResponse::scalar($row, 'firstIn')),
            'last_out' => IappHelper::parseDateTime(IappResponse::scalar($row, 'lastOut')),
            'work_hour' => $workHour,
            'work_minutes' => IappHelper::durationToMinutes($workHour),
            'extra_hours' => IappResponse::scalar($row, 'extraHours'),
            'over_time_hours' => $overTime,
            'over_time_minutes' => IappHelper::durationToMinutes($overTime),
            'late_hours' => $lateHours,
            'late_minutes' => IappHelper::durationToMinutes($lateHours),
            'early_hours' => IappResponse::scalar($row, 'earlyHours'),
            'less_working_hours' => IappResponse::scalar($row, 'lessWorkingHours'),
            'od_hours' => IappResponse::scalar($row, 'odHours'),
            'shift_code' => IappResponse::scalar($row, 'shiftCode'),
            'leave_code' => IappResponse::scalar($row, 'leaveCode'),
            'leave_qty' => self::decimal($row, 'leaveQty'),
            'week_off' => IappResponse::scalar($row, 'weekOff'),
            'holiday' => IappResponse::scalar($row, 'holiday'),
            'absent' => self::decimal($row, 'absent'),
            'present' => self::decimal($row, 'present'),
            'tour' => self::decimal($row, 'tour'),
            'attendance_remark' => IappResponse::scalar($row, 'attendanceRemark'),
            'day_type' => self::classifyDay($row),
            'raw_payload' => $row,
        ];
    }

    protected static function decimal(array $row, string $key): ?float
    {
        $value = IappResponse::scalar($row, $key);

        return ($value === null || !is_numeric($value)) ? null : (float) $value;
    }

    /**
     * Classify the day once, at sync time, so listing screens and the promoter
     * never re-derive it per row.
     *
     * Order matters: a day can carry both a leave code and a weekoff marker,
     * and the more specific reason is the useful one.
     */
    public static function classifyDay(array $row): string
    {
        $model = \App\Models\IappAttendanceRecord::class;

        if (IappResponse::scalar($row, 'firstIn') !== null) {
            return $model::DAY_PRESENT;
        }

        if (IappResponse::scalar($row, 'leaveCode') !== null) {
            return $model::DAY_LEAVE;
        }

        if (IappResponse::scalar($row, 'holiday') !== null) {
            return $model::DAY_HOLIDAY;
        }

        if (IappResponse::scalar($row, 'weekOff') !== null) {
            return $model::DAY_WEEK_OFF;
        }

        $absent = self::decimal($row, 'absent');

        if ($absent !== null && $absent > 0) {
            return $model::DAY_ABSENT;
        }

        $present = self::decimal($row, 'present');

        if ($present !== null && $present > 0) {
            return $model::DAY_PRESENT;
        }

        return $model::DAY_UNKNOWN;
    }

}
