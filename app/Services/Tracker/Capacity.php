<?php

namespace App\Services\Tracker;

use App\Models\ProjectTrackerSetting;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * How many hours each employee has, and has booked, day by day.
 *
 * Capacity of a day = the working hours a day (Settings -> Project Tracker: 8, or 7) when it is a
 * working day for them, less a holiday that applies to them and approved leave (half a day for
 * a half-day leave). A working day: their shift roster says so; past the end of their roster,
 * the company's default shift's working days.
 *
 * Booked = the hours still left on their open tasks (estimate - logged, shared between the people
 * on the task), spread evenly over the task's working days from today to its due date. A task
 * already past its due date lands on today - that is work they owe now.
 *
 * Everything is read in a handful of queries for all the people at once, then worked out in memory.
 */
class Capacity
{

    private float $hoursPerDay;

    public function __construct()
    {
        $this->hoursPerDay = (float) ProjectTrackerSetting::forCompany()->workday_hours ?: 8.0;
    }

    public function hoursPerDay(): float
    {
        return $this->hoursPerDay;
    }

    /**
     * @param  int[]  $userIds
     * @return array<int, array{capacity: array<string, float>, booked: array<string, float>, byProject: array<string, array<int, float>>}>
     *         per user, per date (Y-m-d): hours available, hours booked, and booked per project
     */
    public function days(array $userIds, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $dates = collect(CarbonPeriod::create($from, $to))->map(fn ($d) => $d->toDateString())->all();

        if (!$userIds) {
            return [];
        }

        $working = $this->workingDays($userIds, $from, $to, $dates);
        $off = $this->timeOff($userIds, $from, $to);

        $result = [];
        foreach ($userIds as $uid) {
            $cap = [];
            foreach ($dates as $date) {
                $hours = !empty($working[$uid][$date]) ? $this->hoursPerDay : 0.0;
                $hours *= 1 - min(1, $off[$uid][$date] ?? 0);   // 1 = whole day off, 0.5 = half day
                $cap[$date] = round($hours, 2);
            }
            $result[$uid] = ['capacity' => $cap, 'booked' => array_fill_keys($dates, 0.0), 'byProject' => []];
        }

        $this->book($result, $userIds, $from, $to, $dates);

        return $result;
    }

    /**
     * Totals per period ('day', 'week' from Monday, or 'month').
     *
     * @return array<int, array<string, array{label: string, start: string, capacity: float, booked: float, free: float, load: int, byProject: array<int, float>}>>
     */
    public function periods(array $userIds, Carbon $from, Carbon $to, string $unit = 'week'): array
    {
        $days = $this->days($userIds, $from, $to);
        $out = [];

        foreach ($days as $uid => $d) {
            foreach ($d['capacity'] as $date => $cap) {
                $c = Carbon::parse($date);
                $start = $unit === 'month' ? $c->copy()->startOfMonth() : ($unit === 'day' ? $c->copy() : $c->copy()->startOfWeek(Carbon::MONDAY));
                $key = $start->toDateString();

                if (!isset($out[$uid][$key])) {
                    $out[$uid][$key] = ['label' => $unit === 'month' ? $start->format('M Y') : $start->format('d M'), 'start' => $key,
                        'capacity' => 0.0, 'booked' => 0.0, 'free' => 0.0, 'load' => 0, 'byProject' => []];
                }

                $out[$uid][$key]['capacity'] += $cap;
                $out[$uid][$key]['booked'] += $d['booked'][$date];

                foreach ($d['byProject'][$date] ?? [] as $pid => $h) {
                    $out[$uid][$key]['byProject'][$pid] = ($out[$uid][$key]['byProject'][$pid] ?? 0) + $h;
                }
            }

            foreach ($out[$uid] ?? [] as $key => $p) {
                $out[$uid][$key]['capacity'] = round($p['capacity'], 1);
                $out[$uid][$key]['booked'] = round($p['booked'], 1);
                $out[$uid][$key]['free'] = round($p['capacity'] - $p['booked'], 1);
                $out[$uid][$key]['load'] = $p['capacity'] > 0 ? (int) round($p['booked'] / $p['capacity'] * 100) : ($p['booked'] > 0 ? 999 : 0);
            }
        }

        return $out;
    }

    /** Free hours between two dates (for a booking check). @return array<int, array{capacity: float, booked: float, free: float}> */
    public function window(array $userIds, Carbon $from, Carbon $to): array
    {
        $out = [];
        foreach ($this->days($userIds, $from, $to) as $uid => $d) {
            $cap = array_sum($d['capacity']);
            $booked = array_sum($d['booked']);
            $out[$uid] = ['capacity' => round($cap, 1), 'booked' => round($booked, 1), 'free' => round($cap - $booked, 1)];
        }

        return $out;
    }

    /** @return array<int, array<string, bool>> */
    private function workingDays(array $userIds, Carbon $from, Carbon $to, array $dates): array
    {
        // the roster: a day with a working shift; a day-off shift (no hours) is not one
        $rows = DB::table('employee_shift_schedules as s')
            ->join('employee_shifts as sh', 'sh.id', '=', 's.employee_shift_id')
            ->whereIn('s.user_id', $userIds)->whereBetween('s.date', [$from->toDateString(), $to->toDateString()])
            ->get(['s.user_id', 's.date', 'sh.office_start_time', 'sh.office_end_time', 'sh.shift_type', 'sh.office_open_days']);
        $rosterEnd = DB::table('employee_shift_schedules')->whereIn('user_id', $userIds)
            ->groupBy('user_id')->selectRaw('user_id, max(date) as last')->pluck('last', 'user_id');

        // the roster has every day on it; the shift says which days of the week it works (1 = Monday)
        $working = [];
        $openDaysOf = [];
        foreach ($rows as $r) {
            $open = $openDaysOf[$r->office_open_days] ??= array_map('intval', json_decode((string) $r->office_open_days, true) ?: [1, 2, 3, 4, 5, 6]);
            $date = substr($r->date, 0, 10);
            $working[$r->user_id][$date] = ($r->office_start_time !== $r->office_end_time || $r->shift_type === 'flexible')
                && in_array(Carbon::parse($date)->dayOfWeekIso, $open, true);
        }

        // past the roster: the default shift's working days (1 = Monday ... 7 = Sunday)
        $defaultShift = DB::table('employee_shifts')->where('id', DB::table('attendance_settings')->value('default_employee_shift'))->value('office_open_days');
        $openDays = array_map('intval', json_decode((string) $defaultShift, true) ?: [1, 2, 3, 4, 5, 6]);

        foreach ($userIds as $uid) {
            $last = $rosterEnd[$uid] ?? null;
            foreach ($dates as $date) {
                if (isset($working[$uid][$date])) {
                    continue;
                }
                if ($last && $date <= substr($last, 0, 10)) {
                    $working[$uid][$date] = false;   // within the roster and not on it: off
                    continue;
                }
                $working[$uid][$date] = in_array(Carbon::parse($date)->dayOfWeekIso, $openDays, true);
            }
        }

        return $working;
    }

    /** Holidays that apply to them and approved leave: 1 = the whole day, 0.5 = half. @return array<int, array<string, float>> */
    private function timeOff(array $userIds, Carbon $from, Carbon $to): array
    {
        $off = [];
        $people = DB::table('employee_details')->whereIn('user_id', $userIds)->get(['user_id', 'department_id', 'designation_id'])->keyBy('user_id');
        $holidays = DB::table('holidays')->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['date', 'department_id_json', 'designation_id_json']);

        foreach ($holidays as $h) {
            $depts = array_map('intval', json_decode((string) $h->department_id_json, true) ?: []);
            $desigs = array_map('intval', json_decode((string) $h->designation_id_json, true) ?: []);

            foreach ($userIds as $uid) {
                $p = $people[$uid] ?? null;
                $applies = (!$depts || ($p && in_array((int) $p->department_id, $depts, true)))
                    && (!$desigs || ($p && in_array((int) $p->designation_id, $desigs, true)));

                if ($applies) {
                    $off[$uid][substr($h->date, 0, 10)] = 1;
                }
            }
        }

        $leaves = DB::table('leaves')->whereIn('user_id', $userIds)->where('status', 'approved')
            ->whereBetween('leave_date', [$from->toDateString(), $to->toDateString()])->get(['user_id', 'leave_date', 'duration']);

        foreach ($leaves as $l) {
            $date = substr($l->leave_date, 0, 10);
            $off[$l->user_id][$date] = max($off[$l->user_id][$date] ?? 0, $l->duration === 'half day' ? 0.5 : 1);
        }

        return $off;
    }

    /** Spread the hours left on each open task over its working days. */
    private function book(array &$result, array $userIds, Carbon $from, Carbon $to, array $dates): void
    {
        $completed = DB::table('taskboard_columns')->where('slug', 'completed')->value('id');
        $today = now()->startOfDay();

        $tasks = DB::table('tasks')
            ->join('task_users as tu', 'tu.task_id', '=', 'tasks.id')
            ->whereIn('tu.user_id', $userIds)
            ->whereNull('tasks.deleted_at')
            ->where(fn ($q) => $q->whereNull('tasks.work_type')->orWhere('tasks.work_type', '!=', 'epic'))
            ->when($completed, fn ($q) => $q->where('tasks.board_column_id', '!=', $completed))
            ->where(fn ($q) => $q->where('tasks.estimate_hours', '>', 0)->orWhere('tasks.estimate_minutes', '>', 0))
            ->where(fn ($q) => $q->whereNull('tasks.due_date')->orWhere('tasks.due_date', '>=', $today->copy()->subYear()->toDateString()))
            ->get(['tasks.id', 'tasks.project_id', 'tasks.start_date', 'tasks.due_date', 'tasks.estimate_hours', 'tasks.estimate_minutes', 'tu.user_id']);

        if ($tasks->isEmpty()) {
            return;
        }

        $taskIds = $tasks->pluck('id')->unique()->all();
        // shared between everyone on the task - not only the people asked about here
        $people = DB::table('task_users')->whereIn('task_id', $taskIds)->groupBy('task_id')->selectRaw('task_id, count(*) as n')->pluck('n', 'task_id');
        $logged = DB::table('project_time_logs')->whereIn('task_id', $taskIds)->groupBy('task_id')->selectRaw('task_id, sum(total_minutes) as m')->pluck('m', 'task_id');

        foreach ($tasks as $t) {
            $estimate = ((int) $t->estimate_hours * 60 + (int) $t->estimate_minutes) / 60;
            $left = max(0, $estimate - ((float) ($logged[$t->id] ?? 0)) / 60) / max(1, $people[$t->id]);

            if ($left <= 0 || !isset($result[$t->user_id])) {
                continue;
            }

            $start = Carbon::parse($t->start_date ?: $today)->startOfDay()->max($today);
            $due = $t->due_date ? Carbon::parse($t->due_date)->startOfDay() : $start->copy()->addDays(6);
            $due = $due->max($today);   // overdue: owed today

            // its working days inside the window (and the whole span, for the share that falls here)
            $span = [];
            for ($d = $start->copy(); $d->lte($due); $d->addDay()) {
                $key = $d->toDateString();
                if (($result[$t->user_id]['capacity'][$key] ?? null) === null || $result[$t->user_id]['capacity'][$key] > 0) {
                    $span[] = $key;   // a day outside the window counts as working (unknown); inside, only working days
                }
            }

            if (!$span) {
                $span = [$due->toDateString()];
            }

            $perDay = $left / count($span);
            foreach ($span as $key) {
                if (isset($result[$t->user_id]['booked'][$key])) {
                    $result[$t->user_id]['booked'][$key] += $perDay;
                    $result[$t->user_id]['byProject'][$key][$t->project_id] = ($result[$t->user_id]['byProject'][$key][$t->project_id] ?? 0) + $perDay;
                }
            }
        }

        foreach ($result as $uid => $r) {
            foreach ($r['booked'] as $date => $h) {
                $result[$uid]['booked'][$date] = round($h, 2);
            }
        }
    }

}
