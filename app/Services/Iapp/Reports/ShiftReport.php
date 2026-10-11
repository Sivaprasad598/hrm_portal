<?php

namespace App\Services\Iapp\Reports;

use App\Services\Iapp\Shifts\ShiftCalendar;
use App\Services\Iapp\Shifts\ShiftCompliance;
use App\Services\Iapp\Shifts\ShiftDetector;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Company-wide shift adherence over a date range.
 *
 * For each biometric identity: the shift the ERP expected, the shift their
 * punches suggest, and how the attended days measured up. Built from the
 * derived attendance days in one query, with every shift, schedule, holiday
 * and leave preloaded by ShiftCalendar — no per-employee queries.
 *
 * Biometric codes not yet linked to an ERP employee are evaluated against the
 * company default shift and flagged as assumed.
 */
class ShiftReport
{

    public function __construct(
        protected Carbon $from,
        protected Carbon $to,
        protected ?int $companyId
    ) {
    }

    public function build(?string $controllerCode = null): array
    {
        $query = DB::table('iapp_attendance_records')
            ->whereBetween('attendance_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->whereNotNull('first_in')
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when($controllerCode, function ($q) use ($controllerCode) {
                $q->where(function ($sub) use ($controllerCode) {
                    $sub->where('controller_code', $controllerCode)
                        ->orWhereJsonContains('controller_codes', $controllerCode);
                });
            });

        // Which people and which ERP users appear at all, asked of the database
        // rather than worked out by loading every row first.
        $userIds = (clone $query)->whereNotNull('user_id')->distinct()->pluck('user_id')
            ->map(fn ($id) => (int) $id)->all();
        $codes = (clone $query)->distinct()->pluck('emp_id')->map(fn ($c) => (string) $c)->all();

        $calendar = (new ShiftCalendar($this->companyId, $this->from, $this->to))->load($userIds);

        $deviceNames = DB::table('iapp_employees')
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->whereIn('emp_code', $codes)
            ->get(['emp_code', 'employee_name'])
            // Keyed as strings: codes like "02301694" and "2301694" are
            // different people and must not collide as integer keys.
            ->mapWithKeys(fn ($row) => ['c:' . $row->emp_code => $row->employee_name])
            ->all();

        $erpNames = $userIds
            ? DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id')->all()
            : [];

        $rows = [];

        // One employee at a time, ordered so their days arrive together. Loading
        // the whole range cost about 1.6KB per day-row: fine for a week, but a
        // year of full history is ~63,000 rows — roughly 100MB on top of a 56MB
        // baseline, which the 128MB limit will not survive. Held this way only
        // one person's days are in memory at once, so a year costs no more than
        // a week.
        $current = null;
        $days = [];

        foreach ($query->orderBy('emp_id')->orderBy('attendance_date')->cursor() as $record) {
            $code = (string) $record->emp_id;

            if ($current !== null && $code !== $current) {
                $rows[] = $this->buildRow($current, collect($days), $calendar, $deviceNames, $erpNames);
                $days = [];
            }

            $current = $code;
            $days[] = $record;
        }

        if ($current !== null) {
            $rows[] = $this->buildRow($current, collect($days), $calendar, $deviceNames, $erpNames);
            unset($days);
        }

        // Most late days first: those are the rows worth acting on.
        usort($rows, fn ($a, $b) => [$b['late_days'], $b['days_attended']] <=> [$a['late_days'], $a['days_attended']]);

        return [
            'rows' => $rows,
            'summary' => $this->summarise($rows),
            'conflict' => $calendar->settingsConflict(),
            'default_shift' => $calendar->defaultShift(),
            'shifts' => $calendar->shifts(),
        ];
    }

    protected function buildRow(string $code, $days, ShiftCalendar $calendar, array $deviceNames, array $erpNames): array
    {
        $userId = $days->pluck('user_id')->filter()->first();
        $userId = $userId ? (int) $userId : null;

        $totals = [
            'days_attended' => 0, 'working_days' => 0, 'on_time' => 0, 'late_days' => 0,
            'late_minutes' => 0, 'half_days' => 0, 'early_exits' => 0, 'early_minutes' => 0,
            'overtime_minutes' => 0, 'short_days' => 0, 'off_day_days' => 0,
        ];

        $detectionInput = [];
        $assignedCounts = [];

        foreach ($days as $day) {
            $date = substr((string) $day->attendance_date, 0, 10);
            $firstIn = $day->first_in ? substr((string) $day->first_in, 11, 8) : null;
            $lastOut = $day->last_out ? substr((string) $day->last_out, 11, 8) : null;

            // Single-punch days have no real window, so they don't vote.
            if ((int) $day->punch_count > 1) {
                $detectionInput[] = ['first_in' => $firstIn, 'last_out' => $lastOut];
            }

            $resolution = $calendar->resolve($userId, $date);

            // Hours are judged on presence — arrival to departure — rather than
            // paired door sessions. People re-enter behind someone else without
            // swiping in, so paired time undercounts, while the shift minimum
            // already allows for a break (8 hours on a 9-hour shift).
            $presence = ($day->first_in && $day->last_out)
                ? intdiv(abs(strtotime((string) $day->last_out) - strtotime((string) $day->first_in)), 60)
                : 0;

            $check = ShiftCompliance::evaluate($date, $firstIn, $lastOut, $presence, $resolution);

            $totals['days_attended']++;

            if ($resolution['working'] && $resolution['shift']) {
                $totals['working_days']++;
                $assignedCounts[$resolution['shift']->id] = ($assignedCounts[$resolution['shift']->id] ?? 0) + 1;
            }

            match ($check['status']) {
                ShiftCompliance::STATUS_ON_TIME => $totals['on_time']++,
                ShiftCompliance::STATUS_LATE => $totals['late_days']++,
                ShiftCompliance::STATUS_HALF_DAY => $totals['half_days']++,
                ShiftCompliance::STATUS_OFF_DAY => $totals['off_day_days']++,
                default => null,
            };

            $totals['late_minutes'] += $check['late_minutes'];
            $totals['early_exits'] += $check['early_exit'] ? 1 : 0;
            $totals['early_minutes'] += $check['early_minutes'];
            $totals['overtime_minutes'] += $check['overtime_minutes'];
            $totals['short_days'] += $check['short_hours'] ? 1 : 0;
        }

        // A half day is also a late arrival; count it in late days so the
        // headline reflects everyone who arrived after grace.
        $totals['late_days'] += $totals['half_days'];

        arsort($assignedCounts);
        $assigned = $assignedCounts
            ? $calendar->shifts()->get(array_key_first($assignedCounts))
            : $calendar->defaultShift();

        $detection = ShiftDetector::detect($detectionInput, $calendar->shifts());
        $judged = $totals['on_time'] + $totals['late_days'];

        return $totals + [
            'emp_code' => $code,
            'device_name' => $deviceNames['c:' . $code] ?? null,
            'user_id' => $userId,
            'erp_name' => $userId ? ($erpNames[$userId] ?? null) : null,
            'assumed' => $userId === null,
            'assigned' => $assigned,
            'detection' => $detection,
            'match' => ShiftDetector::compare($detection, $assigned),
            'on_time_pct' => $judged ? (int) round($totals['on_time'] / $judged * 100) : null,
        ];
    }

    protected function summarise(array $rows): array
    {
        $sum = fn (string $key) => array_sum(array_column($rows, $key));

        $onTime = $sum('on_time');
        $late = $sum('late_days');

        return [
            'employees' => count($rows),
            'assumed' => count(array_filter($rows, fn ($r) => $r['assumed'])),
            'days_attended' => $sum('days_attended'),
            'on_time' => $onTime,
            'late_days' => $late,
            'late_minutes' => $sum('late_minutes'),
            'early_exits' => $sum('early_exits'),
            'overtime_minutes' => $sum('overtime_minutes'),
            'short_days' => $sum('short_days'),
            'off_day_days' => $sum('off_day_days'),
            'on_time_pct' => ($onTime + $late) ? (int) round($onTime / ($onTime + $late) * 100) : null,
            'unlisted' => count(array_filter($rows, fn ($r) => $r['match'] === ShiftDetector::MATCH_UNLISTED)),
            'differs' => count(array_filter($rows, fn ($r) => $r['match'] === ShiftDetector::MATCH_DIFFERS)),
        ];
    }

}
