<?php

namespace App\Services\Tracker;

use App\Models\Project;
use App\Models\ProjectTrackerSetting;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * What a project earns and costs, month by month.
 *
 *   staff cost   hours logged on it x each person's cost per hour that month. The cost of a month is the
 *                actual payslip (gross + employer PF + ESI) up to the last month paid (the cut-off); after
 *                it, the monthly salary on file - those months are marked "estimated". Per hour = that cost
 *                / the hours they had that month (Capacity).
 *   direct       expenses entered against the project (categories of the "direct" kind)
 *   overheads    the month's shared expenses (rent, power, internet ...) spread over the projects by their
 *                share of the hours logged that month (or headcount; or not at all - Settings)
 *   revenue      earned = budget x % complete reached that month (default), billed = invoices,
 *                received = payments
 *   profit       revenue - staff cost - direct - overheads; cumulative over the timeline
 *
 * Who may see it: FinanceAccess (Super Admin, Admin, CEO, CFO).
 */
class ProjectFinance
{

    private array $monthCost = [];        // [user][Y-m] => [cost, actual]
    private array $monthCapacity = [];    // [user][Y-m] => hours

    public static function canSee(): bool
    {
        return user() && (bool) array_intersect(user_roles(), ['admin', 'manager_admin', 'ceo', 'vice_president', 'cfo']);
    }

    /** The last month with payslips: actuals up to it, estimates after. */
    public static function cutoff(): ?Carbon
    {
        $last = DB::table('payslips')->whereNull('deleted_at')->max('period_end');

        return $last ? Carbon::parse($last)->startOfMonth() : null;
    }

    /**
     * @return array{months: array<int, array>, totals: array, forecast: array, cutoff: ?Carbon, method: string}
     */
    public function forProject(Project $project, ?string $method = null): array
    {
        $setting = ProjectTrackerSetting::forCompany($project->company_id);
        $method = $method ?: ($setting->revenue_method ?: 'earned');
        $start = Carbon::parse($project->start_date ?? $project->created_at)->startOfMonth();
        $end = now()->startOfMonth();
        $cutoff = self::cutoff();

        if ($start->gt($end)) {
            $start = $end->copy();
        }

        $months = collect(CarbonPeriod::create($start, '1 month', $end))->map(fn ($m) => $m->copy());
        $budget = (float) $project->project_budget;

        // hours on the project, per person per month
        $hours = DB::table('project_time_logs')->where('project_id', $project->id)
            ->where('start_time', '>=', $start)->selectRaw("user_id, DATE_FORMAT(start_time, '%Y-%m') as ym, sum(total_minutes) / 60 as h")
            ->groupBy('user_id', 'ym')->get();

        // % complete at the end of each month, from the progress history (Work items' units)
        $tracker = app(ProjectTracker::class)->forProject($project);
        $planned = (float) $tracker['totals']['planned_units'];
        $doneByMonth = DB::table('tracker_progress_logs as l')->join('tasks as t', 't.id', '=', 'l.task_id')
            ->where('l.project_id', $project->id)->where('t.work_type', 'item')
            ->selectRaw("DATE_FORMAT(l.logged_at, '%Y-%m') as ym, sum(l.units_delta) as u")->groupBy('ym')->pluck('u', 'ym');
        $nowPct = ProjectTracker::percent($tracker);

        $direct = DB::table('expenses as e')->leftJoin('expenses_category as c', 'c.id', '=', 'e.category_id')
            ->where('e.project_id', $project->id)->where('e.status', 'approved')->where(fn ($q) => $q->whereNull('c.cost_type')->orWhere('c.cost_type', 'direct'))
            ->selectRaw("DATE_FORMAT(e.purchase_date, '%Y-%m') as ym, sum(e.price) as amount")->groupBy('ym')->pluck('amount', 'ym');
        $billed = DB::table('invoices')->where('project_id', $project->id)->whereNotIn('status', ['canceled', 'draft'])
            ->selectRaw("DATE_FORMAT(issue_date, '%Y-%m') as ym, sum(total) as amount")->groupBy('ym')->pluck('amount', 'ym');
        $received = DB::table('payments')->where('project_id', $project->id)->where('status', 'complete')
            ->selectRaw("DATE_FORMAT(paid_on, '%Y-%m') as ym, sum(amount) as amount")->groupBy('ym')->pluck('amount', 'ym');

        $rows = [];
        $cumulativeUnits = 0;
        $prevEarned = 0;
        $running = 0;
        foreach ($months as $i => $m) {
            $ym = $m->format('Y-m');
            $staff = 0;
            $estimated = false;
            foreach ($hours->where('ym', $ym) as $h) {
                [$perHour, $actual] = $this->hourlyCost((int) $h->user_id, $m, $cutoff);
                $staff += $h->h * $perHour;
                $estimated = $estimated || !$actual;
            }

            $cumulativeUnits += (float) ($doneByMonth[$ym] ?? 0);
            $pct = $i === $months->count() - 1 ? $nowPct : ($planned > 0 ? min(100, $cumulativeUnits / $planned * 100) : 0);
            $earned = $budget * $pct / 100;
            $revenue = match ($method) {
                'billed' => (float) ($billed[$ym] ?? 0),
                'received' => (float) ($received[$ym] ?? 0),
                default => max(0, $earned - $prevEarned),
            };
            $prevEarned = max($prevEarned, $earned);

            $overhead = $this->overheadShare($project, $m, $setting->overhead_method ?: 'hours');
            $directCost = (float) ($direct[$ym] ?? 0);
            $profit = $revenue - $staff - $directCost - $overhead;
            $running += $profit;

            $rows[] = [
                'month' => $m->format('M Y'), 'ym' => $ym, 'hours' => round((float) $hours->where('ym', $ym)->sum('h'), 1),
                'revenue' => round($revenue), 'staff' => round($staff), 'direct' => round($directCost), 'overhead' => round($overhead),
                'cost' => round($staff + $directCost + $overhead), 'profit' => round($profit), 'cumulative' => round($running),
                'pct' => (int) round($pct), 'estimated' => $estimated || ($cutoff && $m->gt($cutoff)) || !$cutoff,
            ];
        }

        $totals = collect(['revenue', 'staff', 'direct', 'overhead', 'cost', 'profit', 'hours'])->mapWithKeys(fn ($k) => [$k => collect($rows)->sum($k)])->all();
        $totals['margin'] = $totals['revenue'] > 0 ? round($totals['profit'] / $totals['revenue'] * 100, 1) : null;

        return ['months' => $rows, 'totals' => $totals, 'forecast' => $this->forecast($project, $tracker, $totals, $budget),
            'cutoff' => $cutoff, 'method' => $method, 'budget' => $budget];
    }

    /** Staff cost per person to date: hours on the project and what they cost. @return \Illuminate\Support\Collection */
    public function people(Project $project)
    {
        $cutoff = self::cutoff();
        $rows = DB::table('project_time_logs')->where('project_id', $project->id)
            ->selectRaw("user_id, DATE_FORMAT(start_time, '%Y-%m') as ym, sum(total_minutes) / 60 as h")->groupBy('user_id', 'ym')->get();
        $names = DB::table('users')->leftJoin('employee_details as ed', 'ed.user_id', '=', 'users.id')->leftJoin('designations as d', 'd.id', '=', 'ed.designation_id')
            ->whereIn('users.id', $rows->pluck('user_id')->unique())->get(['users.id', 'users.name', 'd.name as designation'])->keyBy('id');

        return $rows->groupBy('user_id')->map(function ($months, $uid) use ($cutoff, $names) {
            $cost = 0;
            $estimated = false;
            foreach ($months as $m) {
                [$perHour, $actual] = $this->hourlyCost((int) $uid, Carbon::createFromFormat('Y-m', $m->ym)->startOfMonth(), $cutoff);
                $cost += $m->h * $perHour;
                $estimated = $estimated || !$actual;
            }
            $hours = (float) $months->sum('h');

            return (object) ['id' => (int) $uid, 'name' => $names[$uid]->name ?? '?', 'designation' => $names[$uid]->designation ?? null,
                'hours' => round($hours, 1), 'cost' => round($cost), 'per_hour' => $hours > 0 ? round($cost / $hours) : 0, 'estimated' => $estimated];
        })->sortByDesc('cost')->values();
    }

    /** Cost per hour of a person in a month, and whether it is from an actual payslip. @return array{0: float, 1: bool} */
    public function hourlyCost(int $userId, Carbon $month, ?Carbon $cutoff = null): array
    {
        $ym = $month->format('Y-m');

        if (!isset($this->monthCost[$userId][$ym])) {
            $slip = DB::table('payslips')->where('user_id', $userId)->whereNull('deleted_at')
                ->whereRaw("DATE_FORMAT(period_end, '%Y-%m') = ?", [$ym])->orderByDesc('id')->first(['gross', 'employer_pf', 'employer_esi']);

            if ($slip) {
                $this->monthCost[$userId][$ym] = [(float) $slip->gross + (float) $slip->employer_pf + (float) $slip->employer_esi, true];
            } else {
                $salary = (float) DB::table('users')->where('id', $userId)->value('monthly_salary');
                $this->monthCost[$userId][$ym] = [$salary, false];
            }

            $this->monthCapacity[$userId][$ym] = app(Capacity::class)->window([$userId], $month->copy()->startOfMonth(), $month->copy()->endOfMonth()->startOfDay())[$userId]['capacity'] ?? 0;
        }

        [$cost, $actual] = $this->monthCost[$userId][$ym];
        $capacity = $this->monthCapacity[$userId][$ym] ?: 26 * app(Capacity::class)->hoursPerDay();

        return [$capacity > 0 ? $cost / $capacity : 0, $actual];
    }

    /** This project's share of the month's shared expenses. */
    private function overheadShare(Project $project, Carbon $month, string $method): float
    {
        if ($method === 'none') {
            return 0;
        }

        $ym = $month->format('Y-m');
        $shared = (float) DB::table('expenses as e')->join('expenses_category as c', 'c.id', '=', 'e.category_id')
            ->where('c.cost_type', 'shared')->where('e.status', 'approved')->where('e.company_id', $project->company_id)
            ->whereRaw("DATE_FORMAT(e.purchase_date, '%Y-%m') = ?", [$ym])->sum('e.price');

        if ($shared <= 0) {
            return 0;
        }

        if ($method === 'headcount') {
            $mine = DB::table('project_members')->where('project_id', $project->id)->count();
            $all = DB::table('project_members')->join('projects', 'projects.id', '=', 'project_members.project_id')
                ->whereNull('projects.deleted_at')->whereNotIn('projects.status', ['finished', 'canceled'])->count();

            return $all ? $shared * $mine / $all : 0;
        }

        // the project's hours against the hours of everyone the office houses that month: rent and power
        // serve the whole staff, so a project carries only the part its hours use
        $mine = (float) DB::table('project_time_logs')->where('project_id', $project->id)
            ->whereRaw("DATE_FORMAT(start_time, '%Y-%m') = ?", [$ym])->sum('total_minutes') / 60;
        $all = $this->companyHours($project->company_id, $month);

        return $all > 0 ? $shared * min(1, $mine / $all) : 0;
    }

    /** Everyone's working hours in a month: active employees x working days (default shift, less holidays) x hours a day. */
    private function companyHours(int $companyId, Carbon $month): float
    {
        static $cache = [];
        $key = $companyId . '|' . $month->format('Y-m');

        if (!isset($cache[$key])) {
            $people = DB::table('users')->join('employee_details as ed', 'ed.user_id', '=', 'users.id')
                ->where('users.company_id', $companyId)->where('users.status', 'active')->count();
            $openDays = array_map('intval', json_decode((string) DB::table('employee_shifts')
                ->where('id', DB::table('attendance_settings')->where('company_id', $companyId)->value('default_employee_shift'))->value('office_open_days'), true) ?: [1, 2, 3, 4, 5, 6]);
            $holidays = DB::table('holidays')->where('company_id', $companyId)->whereNull('department_id_json')
                ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])->count();
            $days = collect(CarbonPeriod::create($month->copy()->startOfMonth(), $month->copy()->endOfMonth()))->filter(fn ($d) => in_array($d->dayOfWeekIso, $openDays, true))->count();
            $cache[$key] = $people * max(0, $days - $holidays) * app(Capacity::class)->hoursPerDay();
        }

        return $cache[$key];
    }

    /** To completion: the hours still estimated on open work x today's cost of the people on it. */
    private function forecast(Project $project, array $tracker, array $totals, float $budget): array
    {
        $remainingCost = 0;
        $remainingHours = 0;
        $logged = DB::table('project_time_logs')->where('project_id', $project->id)->groupBy('task_id')->selectRaw('task_id, sum(total_minutes) / 60 as h')->pluck('h', 'task_id');

        foreach ($tracker['tasks']->where('work_type', '!=', 'epic') as $task) {
            if (($tracker['isDone'])($task)) {
                continue;
            }
            $left = max(0, ((int) $task->estimate_hours + $task->estimate_minutes / 60) - (float) ($logged[$task->id] ?? 0));
            if ($left <= 0 || $task->users->isEmpty()) {
                continue;
            }
            $remainingHours += $left;
            foreach ($task->users as $user) {
                $remainingCost += $left / $task->users->count() * $this->hourlyCost((int) $user->id, now()->startOfMonth())[0];
            }
        }

        $finalCost = $totals['cost'] + $remainingCost;

        return [
            'remaining_hours' => round($remainingHours, 1),
            'remaining_cost' => round($remainingCost),
            'final_cost' => round($finalCost),
            'final_profit' => $budget > 0 ? round($budget - $finalCost) : null,
            'final_margin' => $budget > 0 ? round(($budget - $finalCost) / $budget * 100, 1) : null,
        ];
    }

}
