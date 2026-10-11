<?php

namespace App\Services\Iapp\Sync;

use App\Models\IappAttendanceRecord;
use App\Models\IappDeviceTransaction;
use App\Services\Iapp\IappConfig;
use App\Services\Iapp\Timeline\DayTimeline;
use Carbon\Carbon;

/**
 * Turns raw punches into one attendance day per employee.
 *
 * attd/getDetail is not available on this deployment, so the day-wise view is
 * built here instead of being fetched. Rows land in iapp_attendance_records
 * exactly as the vendor feed would have filled them, marked source =
 * 'punch_derived', so Device Attendance, Exceptions and Shifts all work from
 * the same records. Pushing them into the ERP's own attendance is a separate,
 * deliberate step — see AttendanceSync.
 *
 * How a day is reconstructed — gate duplicates, direction-less face scans,
 * arrival and departure — lives in DayTimeline, which Employee 360 uses too.
 */
class PunchAggregator
{

    public function __construct(
        protected IappConfig $config
    ) {
    }

    /**
     * Rebuild attendance days for a date range from stored punches.
     *
     * Always rebuilds from every punch in the range: building from a subset
     * (one controller, say) would overwrite whole days with partial data.
     *
     * @return array{days:int, employees:int, created:int, updated:int}
     */
    public function build(Carbon $from, Carbon $to): array
    {
        $companyId = $this->config->companyId();
        $timeline = new DayTimeline(DayTimeline::directionlessControllers($companyId));

        $before = IappAttendanceRecord::withoutGlobalScopes()->count();

        $days = 0;
        $written = 0;
        $employees = [];

        // One calendar day at a time. Loading a whole range at once cost about
        // 5KB per punch — 19,777 punches peaked at 94MB, so a single month
        // (~60,000) would have needed roughly 285MB against a 128MB limit and
        // died mid-run. A day is self-contained here anyway, since punches are
        // grouped by employee and date, so this changes cost and nothing else.
        $day = $from->copy()->startOfDay();
        $last = $to->copy()->startOfDay();

        while ($day->lte($last)) {
            $punches = IappDeviceTransaction::query()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->whereBetween('punch_time', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
                ->orderBy('punch_time')
                ->get(['user_id', 'emp_code', 'controller_code', 'punch_time', 'punch_direction']);

            if ($punches->isEmpty()) {
                $day->addDay();

                continue;
            }

            $date = $day->toDateString();
            $rows = [];

            // Grouped by employee code rather than user_id so unmatched
            // employees still get a row and stay visible instead of vanishing.
            foreach ($punches->groupBy(fn ($p) => (string) ($p->emp_code ?? '')) as $empCode => $dayPunches) {
                // Unidentified punches have no one to attribute a day to; they
                // are reported separately under Exceptions.
                if ($empCode === '') {
                    continue;
                }

                $built = $timeline->build($date, $dayPunches);

                if ($built['total_events'] === 0) {
                    continue;
                }

                $rows[] = [
                    'company_id' => $companyId,
                    'user_id' => $dayPunches->firstWhere('user_id', '!=', null)?->user_id,
                    'emp_id' => $empCode,
                    'attendance_date' => $date,
                    'first_in' => $built['first_in_at']?->format('Y-m-d H:i:s'),
                    'last_out' => $built['last_out_at']?->format('Y-m-d H:i:s'),
                    'work_hour' => $built['inside_minutes'] > 0 ? $this->minutesToDuration($built['inside_minutes']) : null,
                    'work_minutes' => $built['inside_minutes'] > 0 ? $built['inside_minutes'] : null,
                    'day_type' => IappAttendanceRecord::DAY_PRESENT,
                    'source' => IappAttendanceRecord::SOURCE_PUNCH,
                    'controller_code' => $built['events'][0]['controller'] ?? null,
                    'controller_codes' => json_encode($built['controllers']),
                    // What the figures are built from, after gate repeats
                    // are collapsed...
                    'punch_count' => $built['total_events'],
                    // ...and what the device actually sent, which is what the
                    // Device Attendance screen shows under PUNCHES.
                    'raw_punch_count' => $built['recorded_events'] ?? $built['total_events'],
                    'sessions' => json_encode($built['sessions']),
                ];

                // Strict comparison: "2301694" and "02301694" are different
                // enrolments but equal under PHP's loose numeric-string rules.
                $employees[$empCode] = true;
            }

            // Persisted per day so neither the punches nor the rows built from
            // them accumulate across the range.
            $this->persist($rows);

            $days += count($rows);
            $written += count($rows);

            unset($punches, $rows);

            $day->addDay();
        }

        $created = max(0, IappAttendanceRecord::withoutGlobalScopes()->count() - $before);

        return [
            'days' => $days,
            'employees' => count($employees),
            'created' => $created,
            'updated' => max(0, $written - $created),
        ];
    }

    protected function minutesToDuration(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * Upsert on the same natural key the vendor feed uses, so both sources
     * share one row per employee per day.
     *
     * promote_status is set on insert only: a rebuild must never reset a row
     * already promoted into attendances.
     */
    protected function persist(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $now = now();

        foreach (array_chunk($rows, 500) as $chunk) {
            foreach ($chunk as &$row) {
                $row['promote_status'] = $row['user_id']
                    ? IappAttendanceRecord::PROMOTE_PENDING
                    : IappAttendanceRecord::PROMOTE_UNMATCHED;
                $row['synced_at'] = $now;
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
            }

            unset($row);

            IappAttendanceRecord::upsert(
                $chunk,
                ['company_id', 'emp_id', 'attendance_date'],
                [
                    'user_id', 'first_in', 'last_out', 'work_hour', 'work_minutes',
                    'day_type', 'source', 'controller_code', 'controller_codes',
                    'punch_count', 'raw_punch_count', 'sessions', 'synced_at', 'updated_at',
                ]
            );
        }
    }

}
