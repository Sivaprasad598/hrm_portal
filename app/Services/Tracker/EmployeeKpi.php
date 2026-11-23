<?php

namespace App\Services\Tracker;

use App\Models\ProjectType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Employee KPIs for a month, from what the tracker records:
 *
 *   utilisation   hours logged / hours they had (Capacity: roster x working hours, less leave and holidays)
 *   productivity  units completed / units their designation's daily rate expects for the hours logged
 *   on_time       their items finished by the due date / their items finished
 *   estimate      how close the hours spent came to the estimate, on their finished items
 *   quality       items not sent back to an earlier step / items they moved forward
 *   blocked       days their open items were held up by a blocker (shown, not scored)
 *   score         the weighted average (0-100) of the scored ones they have data for, the weights being
 *                 those of the project type they spent the most hours on (Settings -> Project Tracker)
 */
class EmployeeKpi
{

    public const METRICS = [
        'utilisation' => ['Utilisation', 'Hours logged against the hours they had'],
        'productivity' => ['Productivity', 'Work completed against what their daily rate expects'],
        'on_time' => ['On time', 'Items finished by their due date'],
        'estimate' => ['Estimate accuracy', 'Hours spent against the estimate'],
        'quality' => ['Quality', 'Work that was not sent back for rework'],
    ];

    /** Everyone's KPIs: the people who run the work (an employee sees their own). */
    public static function canSeeAll(): bool
    {
        return user() && (TrackerAccess::manage() || array_intersect(['admin', 'manager_admin', 'ceo', 'cfo', 'hr', 'manager'], user_roles())
            || in_array(user()->permission('view_projects'), ['all'], true));
    }

    public static function canSee(int $userId): bool
    {
        return user() && ((int) user()->id === $userId || self::canSeeAll());
    }

    /**
     * One person's month: their KPIs, their Division's average for each, and the last six months.
     *
     * @return array{k: ?array, team: array, teamScore: ?int, teamSize: int, trend: array, weights: array}
     */
    public function profile(int $userId, Carbon $month): array
    {
        $k = $this->month([$userId], $month)[$userId] ?? null;

        // the Division: the others in it who logged hours that month
        $division = DB::table('employee_details')->where('user_id', $userId)->value('department_id');
        $peers = $division ? DB::table('employee_details as ed')->join('users as u', 'u.id', '=', 'ed.user_id')->where('u.status', 'active')
            ->where('ed.department_id', $division)
            ->whereIn('ed.user_id', DB::table('project_time_logs')->whereBetween('start_time', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->select('user_id'))
            ->pluck('ed.user_id')->all() : [];
        $rows = collect($this->month($peers, $month));
        $team = [];
        foreach (array_keys(self::METRICS) as $key) {
            $values = $rows->pluck('metrics.' . $key)->filter(fn ($v) => $v !== null);
            $team[$key] = $values->count() ? (int) round($values->avg()) : null;
        }
        $scores = $rows->pluck('score')->filter(fn ($v) => $v !== null);

        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $month->copy()->startOfMonth()->subMonths($i);
            $r = $this->month([$userId], $m)[$userId] ?? null;
            $trend[] = ['month' => $m->format('M Y'), 'score' => $r['score'] ?? null, 'utilisation' => $r['metrics']['utilisation'] ?? null, 'hours' => $r['hours'] ?? 0];
        }

        return ['k' => $k, 'team' => $team, 'teamScore' => $scores->count() ? (int) round($scores->avg()) : null, 'teamSize' => $rows->count(),
            'trend' => $trend, 'weights' => self::weightsFor($k['type'] ?? null)];
    }

    /** A score's band: [label, colour]. */
    public static function band(?int $score): array
    {
        return $score === null ? ['No data', '#98a2b3'] : ($score >= 85 ? ['Excellent', '#12b76a'] : ($score >= 70 ? ['Good', '#3d78e3']
            : ($score >= 50 ? ['Needs attention', '#f79009'] : ['Low', '#f04438'])));
    }

    /** Default weights: counted work leans on output and quality, hours-based work on delivery and estimates. */
    public static function defaultWeights(?ProjectType $type): array
    {
        return $type && $type->countsUnits()
            ? ['utilisation' => 20, 'productivity' => 35, 'on_time' => 20, 'estimate' => 0, 'quality' => 25]
            : ['utilisation' => 25, 'productivity' => 0, 'on_time' => 35, 'estimate' => 25, 'quality' => 15];
    }

    public static function weightsFor(?ProjectType $type): array
    {
        $saved = $type ? json_decode((string) $type->kpi_weights, true) : null;

        return is_array($saved) && array_sum($saved) > 0 ? array_merge(array_fill_keys(array_keys(self::METRICS), 0), $saved) : self::defaultWeights($type);
    }

    /**
     * @param int[] $userIds
     * @return array<int, array> per user: hours, units, each metric (value % or null), score, type
     */
    public function month(array $userIds, Carbon $month): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth()->startOfDay();
        $until = min($to->copy()->endOfDay(), now());   // this month: up to today
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        if (!$userIds) {
            return [];
        }

        $capacity = app(Capacity::class)->window($userIds, $from, Carbon::parse($until)->startOfDay());
        $hoursPerDay = app(Capacity::class)->hoursPerDay();

        // hours logged, by person and project type
        $logged = DB::table('project_time_logs as l')->leftJoin('projects as p', 'p.id', '=', 'l.project_id')
            ->whereIn('l.user_id', $userIds)->whereBetween('l.start_time', [$from, $until])
            ->groupBy('l.user_id', 'p.project_type_id')->selectRaw('l.user_id, p.project_type_id, sum(l.total_minutes) / 60 as h')->get();

        $types = ProjectType::whereIn('id', $logged->pluck('project_type_id')->filter()->unique())->get()->keyBy('id');
        $designation = DB::table('employee_details')->whereIn('user_id', $userIds)->pluck('designation_id', 'user_id');
        $rates = DB::table('project_type_rates')->whereNull('stage_id')->get()->groupBy(fn ($r) => $r->project_type_id . '|' . $r->designation_id);

        // the history of the month: units credited (shared between the people on the item), rework, finished items
        $logs = DB::table('tracker_progress_logs')->whereBetween('logged_at', [$from, $until])->get();
        $units = [];
        $forward = [];
        $rework = [];
        foreach ($logs as $log) {
            $people = array_map('intval', json_decode((string) $log->assignee_ids, true) ?: []);
            foreach (array_intersect($people, $userIds) as $uid) {
                if ($log->units_delta > 0) {
                    $units[$uid] = ($units[$uid] ?? 0) + $log->units_delta / max(1, count($people));
                }
                if ($log->stage_from && $log->stage_to && $log->stage_from !== $log->stage_to) {
                    $log->rework ? $rework[$uid] = ($rework[$uid] ?? 0) + 1 : $forward[$uid] = ($forward[$uid] ?? 0) + 1;
                }
            }
        }

        // items finished this month that they were on
        $completed = DB::table('taskboard_columns')->where('slug', 'completed')->value('id');
        $finished = DB::table('tasks as t')->join('task_users as tu', 'tu.task_id', '=', 't.id')
            ->whereIn('tu.user_id', $userIds)->whereNull('t.deleted_at')->where('t.work_type', '!=', 'epic')
            ->where('t.board_column_id', $completed)->whereBetween('t.completed_on', [$from, $until])
            ->get(['tu.user_id', 't.id', 't.due_date', 't.completed_on', 't.estimate_hours', 't.estimate_minutes']);
        $spent = DB::table('project_time_logs')->whereIn('task_id', $finished->pluck('id')->unique())->groupBy('task_id')
            ->selectRaw('task_id, sum(total_minutes) / 60 as h')->pluck('h', 'task_id');

        // days blocked: open blockers on their items, within the month
        $blocked = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('task_blockers')) {
            $rows = DB::table('task_blockers as b')->join('task_users as tu', 'tu.task_id', '=', 'b.task_id')->whereIn('tu.user_id', $userIds)
                ->where('b.created_at', '<=', $until)->where(fn ($q) => $q->whereNull('b.resolved_at')->orWhere('b.resolved_at', '>=', $from))
                ->get(['tu.user_id', 'b.created_at', 'b.resolved_at']);
            foreach ($rows as $b) {
                $start = Carbon::parse($b->created_at)->max($from);
                $end = Carbon::parse($b->resolved_at ?? $until)->min(Carbon::parse($until));
                $blocked[$b->user_id] = ($blocked[$b->user_id] ?? 0) + max(0, $start->diffInDays($end));
            }
        }

        $out = [];
        foreach ($userIds as $uid) {
            $mine = $logged->where('user_id', $uid);
            $hours = (float) $mine->sum('h');
            $mainType = $mine->filter(fn ($r) => $r->project_type_id)->sortByDesc('h')->first();
            $type = $mainType ? ($types[$mainType->project_type_id] ?? null) : null;

            // what their rate expects for the hours logged on counted work
            $expected = 0.0;
            foreach ($mine as $r) {
                $t = $types[$r->project_type_id] ?? null;
                $rate = $t && $t->countsUnits() ? ($rates[$t->id . '|' . ($designation[$uid] ?? 0)][0]->units_per_day ?? null) : null;
                if ($rate) {
                    $expected += $r->h / max(1, $hoursPerDay) * (float) $rate;
                }
            }

            $done = $finished->where('user_id', $uid);
            $onTime = $done->filter(fn ($t) => $t->due_date && substr($t->completed_on, 0, 10) <= substr($t->due_date, 0, 10))->count();
            $accuracy = $done->map(function ($t) use ($spent) {
                $est = ((int) $t->estimate_hours * 60 + (int) $t->estimate_minutes) / 60;
                return $est > 0 ? max(0, 100 - abs(((float) ($spent[$t->id] ?? 0)) - $est) / $est * 100) : null;
            })->filter(fn ($v) => $v !== null);

            $cap = $capacity[$uid]['capacity'] ?? 0;
            $moves = ($forward[$uid] ?? 0) + ($rework[$uid] ?? 0);
            $m = [
                'utilisation' => $cap > 0 ? round($hours / $cap * 100) : null,
                'productivity' => $expected > 0 ? round(($units[$uid] ?? 0) / $expected * 100) : null,
                'on_time' => $done->count() ? round($onTime / $done->count() * 100) : null,
                'estimate' => $accuracy->count() ? round($accuracy->avg()) : null,
                'quality' => $moves ? round(($forward[$uid] ?? 0) / $moves * 100) : null,
            ];

            $out[$uid] = [
                'hours' => round($hours, 1), 'capacity' => round($cap, 1), 'units' => round($units[$uid] ?? 0, 1), 'expected' => round($expected, 1),
                'finished' => $done->count(), 'on_time_count' => $onTime, 'rework' => $rework[$uid] ?? 0, 'blocked_days' => (int) ($blocked[$uid] ?? 0),
                'metrics' => $m, 'type' => $type, 'score' => $this->score($m, self::weightsFor($type)),
            ];
        }

        return $out;
    }

    /** The scored metrics they have data for, weighted (utilisation is best at 85-100%; over 110% counts against). */
    private function score(array $m, array $weights): ?int
    {
        $sum = 0;
        $weight = 0;
        foreach ($weights as $key => $w) {
            if (!$w || ($m[$key] ?? null) === null) {
                continue;
            }
            $v = (float) $m[$key];
            $v = $key === 'utilisation' ? ($v >= 85 && $v <= 110 ? 100 : ($v > 110 ? max(0, 100 - ($v - 110) * 2) : $v / 85 * 100)) : min(100, $v);
            $sum += $v * $w;
            $weight += $w;
        }

        return $weight ? (int) round($sum / $weight) : null;
    }

    /** The months ending with this one. @return array<int, array<string, ?int>> per user: month (Y-m) => score */
    public function trend(array $userIds, int $months = 6): array
    {
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            foreach ($this->month($userIds, $month) as $uid => $k) {
                $out[$uid][$month->format('M Y')] = $k['score'];
            }
        }

        return $out;
    }

}
