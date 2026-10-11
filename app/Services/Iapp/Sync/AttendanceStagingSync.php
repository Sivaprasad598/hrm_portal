<?php

namespace App\Services\Iapp\Sync;

use App\Models\IappAttendanceRecord;
use App\Models\IappSyncLog;
use App\Services\Iapp\AttendanceEndpoint;
use App\Services\Iapp\IappClient;
use App\Services\Iapp\IappConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * API -> iapp_attendance_records.
 *
 * Day-wise attendance lands in staging exactly as iApp returned it and is
 * only then promoted into `attendances`. Keeping the pull separate matters
 * because iApp restates past days after regularisation and leave approval:
 * a re-sync must be comparable against what was there before, not a silent
 * overwrite.
 *
 * Rows with no first_in — absent, leave, week-off, holiday — are kept here
 * too. They never become attendance rows, but absence reporting and payroll
 * LOP need them.
 *
 * Note: attd/getDetail is not available on the server at this site (404), so
 * this class currently has no data to pull. It is complete and tested against
 * the documented contract for when the Attendance module is enabled.
 */
class AttendanceStagingSync
{

    protected const CHUNK = 500;

    protected AttendanceEndpoint $endpoint;

    protected EmployeeResolver $resolver;

    public function __construct(
        protected IappClient $client,
        ?AttendanceEndpoint $endpoint = null,
        ?EmployeeResolver $resolver = null
    ) {
        $this->endpoint = $endpoint ?? new AttendanceEndpoint($client);
        $this->resolver = $resolver ?? new EmployeeResolver($client->config());
    }

    public function config(): IappConfig
    {
        return $this->client->config();
    }

    public function resolver(): EmployeeResolver
    {
        return $this->resolver;
    }

    public function run(Carbon $from, Carbon $to, string $trigger = 'manual', ?int $userId = null): IappSyncLog
    {
        $timer = microtime(true);

        $log = new IappSyncLog([
            'sync_type' => IappSyncLog::TYPE_ATTENDANCE,
            'trigger' => $trigger,
            'triggered_by' => $userId,
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'status' => IappSyncLog::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $log->company_id = $this->config()->companyId();
        $log->save();

        $fetched = 0;
        $rows = [];

        try {
            if (!$this->config()->isConfigured()) {
                throw new \RuntimeException('iApp connection is not configured.');
            }

            foreach ($this->endpoint->stream($from, $to, $this->config()->locationCode()) as $batch) {
                $fetched += count($batch);

                foreach ($batch as $row) {
                    $normalised = AttendanceEndpoint::normalise($row);

                    if ($normalised === null) {
                        continue;
                    }

                    // One row per employee per day; a later page restating the
                    // same day wins.
                    $rows[$normalised['emp_id'] . '|' . $normalised['attendance_date']] = $normalised;
                }
            }

            $this->resolver->load(array_column($rows, 'emp_id'));

            $result = $this->persist($rows, $log->company_id);

            $log->fill([
                'records_fetched' => $fetched,
                'records_created' => $result['created'],
                'records_updated' => $result['updated'],
                'records_skipped' => $fetched - count($rows),
                'records_unmatched' => $this->resolver->unmatchedCount(),
                'unmatched_codes' => array_slice($this->resolver->unmatchedCodes(), 0, 200),
                'status' => $this->resolver->unmatchedCount() > 0
                    ? IappSyncLog::STATUS_PARTIAL
                    : IappSyncLog::STATUS_SUCCESS,
                'message' => sprintf(
                    '%d rows (%d unique days), %d employees matched, %d unmatched.',
                    $fetched,
                    count($rows),
                    $this->resolver->matchedCount(),
                    $this->resolver->unmatchedCount()
                ),
            ]);
        } catch (\Throwable $e) {
            Log::error('iApp attendance staging sync failed', ['message' => $e->getMessage()]);

            $log->fill([
                'records_fetched' => $fetched,
                'status' => IappSyncLog::STATUS_FAILED,
                'message' => $e->getMessage(),
            ]);
        }

        $log->finished_at = now();
        $log->duration_ms = (int) round((microtime(true) - $timer) * 1000);
        $log->save();

        return $log;
    }

    /**
     * Chunked bulk upsert on (company_id, emp_id, attendance_date).
     *
     * @return array{created:int, updated:int}
     */
    protected function persist(array $rows, ?int $companyId): array
    {
        if ($rows === []) {
            return ['created' => 0, 'updated' => 0];
        }

        $before = IappAttendanceRecord::withoutGlobalScopes()->count();
        $now = now();
        $written = 0;

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            $payload = [];

            foreach ($chunk as $row) {
                $userId = $this->resolver->resolve($row['emp_id']);

                $payload[] = [
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'emp_id' => $row['emp_id'],
                    'employee_name' => $row['employee_name'],
                    'location_code' => $row['location_code'],
                    'attendance_date' => $row['attendance_date'],
                    'first_in' => $row['first_in']?->format('Y-m-d H:i:s'),
                    'last_out' => $row['last_out']?->format('Y-m-d H:i:s'),
                    'work_hour' => $row['work_hour'],
                    'work_minutes' => $row['work_minutes'],
                    'extra_hours' => $row['extra_hours'],
                    'over_time_hours' => $row['over_time_hours'],
                    'over_time_minutes' => $row['over_time_minutes'],
                    'late_hours' => $row['late_hours'],
                    'late_minutes' => $row['late_minutes'],
                    'early_hours' => $row['early_hours'],
                    'less_working_hours' => $row['less_working_hours'],
                    'od_hours' => $row['od_hours'],
                    'shift_code' => $row['shift_code'],
                    'leave_code' => $row['leave_code'],
                    'leave_qty' => $row['leave_qty'],
                    'week_off' => $row['week_off'],
                    'holiday' => $row['holiday'],
                    'absent' => $row['absent'],
                    'present' => $row['present'],
                    'tour' => $row['tour'],
                    'attendance_remark' => $row['attendance_remark'],
                    'day_type' => $row['day_type'],
                    // Only worked days can become an attendance row; the rest
                    // stay here and are marked so the promoter skips them
                    // without re-deciding per run.
                    'promote_status' => $this->initialPromoteStatus($row, $userId),
                    'raw_payload' => json_encode($row['raw_payload']),
                    'synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            IappAttendanceRecord::upsert(
                $payload,
                ['company_id', 'emp_id', 'attendance_date'],
                [
                    'user_id', 'employee_name', 'location_code', 'first_in', 'last_out',
                    'work_hour', 'work_minutes', 'extra_hours', 'over_time_hours',
                    'over_time_minutes', 'late_hours', 'late_minutes', 'early_hours',
                    'less_working_hours', 'od_hours', 'shift_code', 'leave_code',
                    'leave_qty', 'week_off', 'holiday', 'absent', 'present', 'tour',
                    'attendance_remark', 'day_type', 'raw_payload', 'synced_at', 'updated_at',
                ]
            );

            $written += count($payload);
        }

        $created = max(0, IappAttendanceRecord::withoutGlobalScopes()->count() - $before);

        return ['created' => $created, 'updated' => max(0, $written - $created)];
    }

    /**
     * Note this deliberately does not reset an already-promoted row: the
     * upsert above leaves promote_status untouched on update, so a restated
     * day keeps its promotion history.
     */
    protected function initialPromoteStatus(array $row, ?int $userId): string
    {
        if ($userId === null) {
            return IappAttendanceRecord::PROMOTE_UNMATCHED;
        }

        if ($row['first_in'] === null) {
            return IappAttendanceRecord::PROMOTE_NOT_APPLICABLE;
        }

        return IappAttendanceRecord::PROMOTE_PENDING;
    }

}
