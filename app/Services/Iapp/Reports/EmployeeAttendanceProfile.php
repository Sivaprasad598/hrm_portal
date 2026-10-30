<?php

namespace App\Services\Iapp\Reports;

use App\Models\IappDeviceTransaction;
use App\Models\IappEmployee;
use App\Services\Iapp\Shifts\ShiftCalendar;
use App\Services\Iapp\Shifts\ShiftCompliance;
use App\Services\Iapp\Shifts\ShiftDetector;
use App\Services\Iapp\Timeline\DayTimeline;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Attendance KPIs for one biometric identity over a date range.
 *
 * Each day is reconstructed by DayTimeline — the same code that builds the
 * attendance records — so this screen and Shifts, Exceptions and Device
 * Attendance always agree. Counts are of distinct events, never raw rows: one
 * swipe is reported once per gate on its controller.
 *
 * Shift figures come from the ERP (see ShiftCalendar): the devices expose no
 * shift data. Room-level detail is not available either: deviceTrans exposes
 * controllerCode but no gate, door or zone column.
 */
class EmployeeAttendanceProfile
{

    protected ?ShiftCalendar $calendar = null;

    public function __construct(
        protected IappEmployee $employee,
        protected Carbon $from,
        protected Carbon $to
    ) {
    }

    /**
     * Punches in range, oldest first.
     */
    protected function punches(): Collection
    {
        // company_id leads the (company_id, emp_code, punch_time) index, and
        // IappDeviceTransaction carries no company scope of its own, so without
        // it this is a full table scan.
        return IappDeviceTransaction::query()
            ->when($this->companyId(), fn ($q, $id) => $q->where('company_id', $id))
            ->where('emp_code', $this->employee->emp_code)
            ->whereBetween('punch_time', [$this->from->copy()->startOfDay(), $this->to->copy()->endOfDay()])
            ->orderBy('punch_time')
            ->get(['punch_time', 'punch_direction', 'controller_code']);
    }

    /**
     * Full profile: headline KPIs, shift KPIs, per-day rows and place totals.
     */
    public function build(): array
    {
        $calendar = $this->calendar();
        $timeline = new DayTimeline(DayTimeline::directionlessControllers($this->companyId()));

        $days = [];
        $events = [];

        foreach ($this->punches()->groupBy(fn ($punch) => $punch->punch_time->toDateString()) as $date => $dayPunches) {
            $day = $timeline->build($date, $dayPunches);

            if ($day['total_events'] === 0) {
                continue;
            }

            foreach ($day['events'] as $event) {
                $events[] = $event;
            }

            $resolution = $calendar->resolve($this->userId(), $date);

            // Hours are judged on presence (arrival to departure), not paired
            // door sessions — see DayTimeline on loose swipe discipline.
            $day['shift'] = [
                'label' => ShiftCalendar::label($resolution['shift']),
                'shift_id' => $resolution['shift']->id ?? null,
                'source' => $resolution['source'],
                'working' => $resolution['working'],
                'reason' => $resolution['reason'],
                'holiday' => $resolution['holiday'],
            ] + ShiftCompliance::evaluate(
                $date,
                $day['first_in'],
                $day['last_out'],
                (int) ($day['presence_minutes'] ?? 0),
                $resolution
            );

            unset($day['first_in_at'], $day['last_out_at']);

            $days[] = $day;
        }

        // Newest first for display; the maths is order-independent.
        usort($days, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return [
            'kpis' => $this->buildKpis($days) + $this->buildShiftKpis($days, $calendar),
            'days' => $days,
            'places' => $this->buildPlaces($events),
            'hourly' => $this->buildHourly($events),
            'range' => [
                'from' => $this->from->toDateString(),
                'to' => $this->to->toDateString(),
                'calendar_days' => (int) $this->from->diffInDays($this->to) + 1,
            ],
        ];
    }

    protected function userId(): ?int
    {
        return $this->employee->user_id ? (int) $this->employee->user_id : null;
    }

    protected function companyId(): ?int
    {
        return $this->employee->company_id ? (int) $this->employee->company_id : null;
    }

    protected function calendar(): ShiftCalendar
    {
        return $this->calendar ??= (new ShiftCalendar($this->companyId(), $this->from, $this->to))
            ->load(array_filter([$this->userId()]));
    }

    /**
     * Headline numbers across the range.
     */
    protected function buildKpis(array $days): array
    {
        $count = count($days);
        $insideTotal = array_sum(array_column($days, 'inside_minutes'));
        $outsideTotal = array_sum(array_column($days, 'outside_minutes'));
        $checkIns = array_sum(array_column($days, 'check_ins'));
        $checkOuts = array_sum(array_column($days, 'check_outs'));

        // Averaged over days actually attended, not calendar days — a weekend
        // with no punches would otherwise drag every average down.
        $firstIns = array_filter(array_column($days, 'first_in'));
        $lastOuts = array_filter(array_column($days, 'last_out'));

        return [
            'days_present' => $count,
            'calendar_days' => (int) $this->from->diffInDays($this->to) + 1,
            'total_inside_minutes' => $insideTotal,
            'total_outside_minutes' => $outsideTotal,
            'avg_inside_minutes' => $count ? (int) round($insideTotal / $count) : 0,
            'avg_outside_minutes' => $count ? (int) round($outsideTotal / $count) : 0,
            'total_check_ins' => $checkIns,
            'total_check_outs' => $checkOuts,
            'total_scans' => array_sum(array_column($days, 'scans')),
            'avg_check_ins' => $count ? round($checkIns / $count, 1) : 0,
            'avg_exits' => $count ? round($checkOuts / $count, 1) : 0,
            'avg_first_in' => $this->averageTime($firstIns),
            'avg_last_out' => $this->averageTime($lastOuts),
            'earliest_in' => $firstIns ? min($firstIns) : null,
            'latest_out' => $lastOuts ? max($lastOuts) : null,
        ];
    }

    /**
     * Shift adherence, detected shift and absence across the range.
     */
    protected function buildShiftKpis(array $days, ShiftCalendar $calendar): array
    {
        $totals = [
            'on_time_days' => 0, 'late_days' => 0, 'late_minutes' => 0, 'half_days' => 0,
            'early_exits' => 0, 'early_minutes' => 0, 'overtime_minutes' => 0,
            'short_days' => 0, 'off_day_days' => 0,
        ];

        $assignedCounts = [];
        $sourceCounts = [];
        $detectionInput = [];
        $attendedWorking = [];

        foreach ($days as $day) {
            $shift = $day['shift'];

            // A single event has no real window, so it doesn't vote.
            if ($day['total_events'] > 1) {
                $detectionInput[] = ['first_in' => $day['first_in'], 'last_out' => $day['last_out']];
            }

            $sourceCounts[$shift['source']] = ($sourceCounts[$shift['source']] ?? 0) + 1;

            if ($shift['working'] && $shift['shift_id']) {
                $assignedCounts[$shift['shift_id']] = ($assignedCounts[$shift['shift_id']] ?? 0) + 1;
                $attendedWorking[$day['date']] = true;
            }

            match ($shift['status']) {
                ShiftCompliance::STATUS_ON_TIME => $totals['on_time_days']++,
                ShiftCompliance::STATUS_LATE => $totals['late_days']++,
                ShiftCompliance::STATUS_HALF_DAY => $totals['half_days']++,
                ShiftCompliance::STATUS_OFF_DAY => $totals['off_day_days']++,
                default => null,
            };

            $totals['late_minutes'] += $shift['late_minutes'];
            $totals['early_exits'] += $shift['early_exit'] ? 1 : 0;
            $totals['early_minutes'] += $shift['early_minutes'];
            $totals['overtime_minutes'] += $shift['overtime_minutes'];
            $totals['short_days'] += $shift['short_hours'] ? 1 : 0;
        }

        // A half day is also an arrival after grace, so it counts as late too.
        $totals['late_days'] += $totals['half_days'];

        arsort($assignedCounts);
        arsort($sourceCounts);

        $assigned = $assignedCounts
            ? $calendar->shifts()->get(array_key_first($assignedCounts))
            : $calendar->defaultShift();

        $detection = ShiftDetector::detect($detectionInput, $calendar->shifts());

        // Absence can only be claimed for days the device data actually
        // covers: a day before the first sync or after the latest one has no
        // punches for anyone, and would read as absence that never happened.
        [$coverFrom, $coverTo] = $this->coverage();
        $expected = 0;
        $absentDates = [];
        // How the covered days split, for "Days Present": the days left once
        // the shift's week-offs, holidays and full-day leave are taken out.
        $split = ['covered_days' => 0, 'week_off_days' => 0, 'holiday_days' => 0, 'leave_days' => 0];

        if ($coverFrom && $coverTo && $coverFrom->lte($coverTo)) {
            for ($date = $coverFrom->copy(); $date->lte($coverTo); $date->addDay()) {
                $key = $date->toDateString();
                $split['covered_days']++;
                $resolved = $calendar->resolve($this->userId(), $key);

                if (!$resolved['working']) {
                    match ($resolved['reason'] ?? null) {
                        ShiftCalendar::REASON_HOLIDAY => $split['holiday_days']++,
                        ShiftCalendar::REASON_LEAVE => $split['leave_days']++,
                        default => $split['week_off_days']++,
                    };

                    continue;
                }

                $expected++;

                if (!isset($attendedWorking[$key])) {
                    $absentDates[] = $key;
                }
            }
        }

        $judged = $totals['on_time_days'] + $totals['late_days'];

        return $totals + $split + [
            // Days with punches that were not working days (week-off, holiday, leave).
            'off_day_present' => max(0, count($days) - count($attendedWorking)),
            'shift_assigned' => ShiftCalendar::label($assigned),
            'shift_source' => $sourceCounts
                ? array_key_first($sourceCounts)
                : ($this->userId() ? ShiftCalendar::SOURCE_DEFAULT : ShiftCalendar::SOURCE_ASSUMED),
            'shift_detected' => $detection['label'],
            'shift_window' => $detection['window'],
            'shift_confidence' => $detection['confidence'],
            'shift_match' => ShiftDetector::compare($detection, $assigned),
            'on_time_pct' => $judged ? (int) round($totals['on_time_days'] / $judged * 100) : null,
            'expected_working_days' => $expected,
            'attended_working_days' => count($attendedWorking),
            'absent_days' => count($absentDates),
            'absent_dates' => $absentDates,
            'coverage_from' => $coverFrom?->toDateString(),
            'coverage_to' => $coverTo?->toDateString(),
            'shift_conflict' => $calendar->settingsConflict(),
        ];
    }

    /**
     * The span of dates the stored punch data covers, clamped to the range.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    protected function coverage(): array
    {
        $bounds = DB::table('iapp_device_transactions')
            ->when($this->companyId(), fn ($q, $id) => $q->where('company_id', $id))
            ->selectRaw('MIN(punch_time) AS first_punch, MAX(punch_time) AS last_punch')
            ->first();

        if (!$bounds || !$bounds->first_punch) {
            return [null, null];
        }

        return [
            Carbon::parse($bounds->first_punch)->startOfDay()->max($this->from->copy()->startOfDay()),
            Carbon::parse($bounds->last_punch)->startOfDay()->min($this->to->copy()->startOfDay()),
        ];
    }

    /**
     * Visit and event counts per controller across the range, using the same
     * visit rule as DayTimeline and restarting it each day.
     *
     * @param  array<int, array>  $events  chronological, from DayTimeline
     */
    protected function buildPlaces(array $events): array
    {
        $places = [];
        $previousDoor = null;
        $previousDate = null;

        foreach ($events as $event) {
            $controller = $event['controller'];

            if ($controller === null) {
                continue;
            }

            $date = $event['at']->toDateString();

            if ($date !== $previousDate) {
                $previousDoor = null;
                $previousDate = $date;
            }

            $places[$controller] ??= ['controller_code' => $controller, 'visits' => 0, 'events' => 0, 'last_seen' => null];
            $places[$controller]['events']++;
            $places[$controller]['last_seen'] = $event['at']->format('Y-m-d H:i:s');

            if ($event['kind'] === DayTimeline::KIND_SCAN) {
                $places[$controller]['visits']++;

                continue;
            }

            if ($controller !== $previousDoor) {
                $places[$controller]['visits']++;
            }

            $previousDoor = $controller;
        }

        uasort($places, fn ($a, $b) => $b['visits'] <=> $a['visits']);

        return array_values($places);
    }

    /**
     * Events per hour of day. Face scans are counted on their own: their
     * direction is not meaningful, so they are neither an in nor an out.
     */
    protected function buildHourly(array $events): array
    {
        $hours = array_fill(0, 24, ['in' => 0, 'out' => 0, 'scan' => 0]);

        foreach ($events as $event) {
            $hour = (int) $event['at']->format('G');

            if ($event['kind'] === DayTimeline::KIND_SCAN) {
                $hours[$hour]['scan']++;
            } elseif ($event['direction'] === 'in') {
                $hours[$hour]['in']++;
            } elseif ($event['direction'] === 'out') {
                $hours[$hour]['out']++;
            }
        }

        return $hours;
    }

    /**
     * Mean of a set of clock times, as HH:MM.
     */
    protected function averageTime(array $times): ?string
    {
        if ($times === []) {
            return null;
        }

        $seconds = 0;

        foreach ($times as $time) {
            [$h, $m, $s] = array_pad(explode(':', $time), 3, 0);
            $seconds += ((int) $h * 3600) + ((int) $m * 60) + (int) $s;
        }

        $average = (int) round($seconds / count($times));

        return sprintf('%02d:%02d', intdiv($average, 3600), intdiv($average % 3600, 60));
    }

    /** Minutes as "7h 45m". */
    public static function humanMinutes(?int $minutes): string
    {
        if (!$minutes) {
            return '--';
        }

        return intdiv($minutes, 60) . 'h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) . 'm';
    }

}
