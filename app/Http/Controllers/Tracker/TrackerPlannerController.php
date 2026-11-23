<?php

namespace App\Http\Controllers\Tracker;

use App\Models\Designation;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskboardColumn;
use App\Models\Team;
use App\Models\User;
use App\Services\Tracker\Capacity;
use App\Services\Tracker\ProjectTracker;
use App\Services\Tracker\TrackerAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Work -> Project Dashboard (how the projects stand), Work -> Resource Planner (who is free when,
 * the next three months), an employee's availability, and the booking check used when work is
 * assigned. Hours from App\Services\Tracker\Capacity.
 */
class TrackerPlannerController extends TrackerBaseController
{

    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            abort_403(!(TrackerAccess::manage() || in_array(user()->permission('view_projects'), ['all', 'added', 'owned', 'both'], true)
                // finance sees the Project Dashboard (its money card) without the Projects module
                || ($request->routeIs('project-tracker.dashboard') && \App\Services\Tracker\ProjectFinance::canSee())));

            return $next($request);
        });
    }

    public function dashboard(Capacity $capacity)
    {
        $this->pageTitle = 'Project Dashboard';
        $today = now()->startOfDay();
        $tracker = app(ProjectTracker::class);

        $projects = Project::with('projectType.stages', 'projectType.division', 'members')
            ->whereNotIn('status', ['finished', 'canceled'])->orderBy('deadline')->get();

        $this->projects = $projects->map(function (Project $project) use ($tracker, $today) {
            $t = $tracker->forProject($project);
            $grace = $project->grace_days ?? $project->projectType?->grace_days ?? 0;
            $final = $project->deadline?->copy()->addDays($grace);
            $pct = ProjectTracker::percent($t);
            $state = !$project->deadline ? 'none' : ($pct >= 100 ? 'done' : ($today->gt($final) ? 'overdue' : ($today->gt($project->deadline) ? 'grace' : 'ok')));
            // at risk: on schedule but the time gone is well ahead of the work done
            if ($state === 'ok' && $project->start_date && $project->deadline && $project->deadline->gt($project->start_date)) {
                $timeGone = max(0, min(100, $project->start_date->diffInDays($today, false) / max(1, $project->start_date->diffInDays($project->deadline)) * 100));
                if ($timeGone - $pct > 25) {
                    $state = 'risk';
                }
            }

            return (object) ['project' => $project, 'tracker' => $t, 'pct' => $pct, 'state' => $state, 'grace' => $grace,
                'daysLeft' => $project->deadline ? (int) $today->diffInDays($project->deadline, false) : null];
        })->sortBy(fn ($r) => array_search($r->state, ['overdue', 'grace', 'risk', 'ok', 'none', 'done']) * 1000 + ($r->daysLeft ?? 999))->values();

        $this->stateCounts = $this->projects->countBy('state');
        $completed = TaskboardColumn::completeColumn()?->id;
        $projectIds = $projects->pluck('id');
        $this->doneThisWeek = Task::whereIn('project_id', $projectIds)->where('board_column_id', $completed)
            ->where('completed_on', '>=', now()->startOfWeek(Carbon::MONDAY))->where('work_type', '!=', 'epic')->count();
        $this->hoursThisWeek = round(DB::table('project_time_logs')->whereIn('project_id', $projectIds)
            ->where('start_time', '>=', now()->startOfWeek(Carbon::MONDAY))->sum('total_minutes') / 60, 1);
        $this->openBlockers = \Illuminate\Support\Facades\Schema::hasTable('task_blockers')
            ? DB::table('task_blockers')->join('tasks', 'tasks.id', '=', 'task_blockers.task_id')->whereIn('tasks.project_id', $projectIds)->where('task_blockers.status', 'open')->count() : 0;

        // by Division (the project type's)
        $this->byDivision = $this->projects->groupBy(fn ($r) => $r->project->projectType?->division?->team_name ?? 'No type yet')
            ->map(fn ($rows, $name) => (object) ['name' => $name, 'count' => $rows->count(), 'pct' => (int) round($rows->avg('pct')),
                'color' => $rows->first()->project->projectType?->color ?? '#98a2b3', 'late' => $rows->whereIn('state', ['overdue', 'grace'])->count()])
            ->sortByDesc('count')->values();

        // the team: everyone on an open project, this week and next
        $people = $projects->flatMap(fn ($p) => $p->members->pluck('user_id'))->unique()->values()->all();
        $weeks = $capacity->periods($people, now()->startOfWeek(Carbon::MONDAY), now()->startOfWeek(Carbon::MONDAY)->addWeeks(2)->subDay(), 'week');
        $names = User::withoutGlobalScopes()->whereIn('id', $people)->get(['id', 'name', 'image'])->keyBy('id');
        $this->load = collect($weeks)->map(fn ($w, $uid) => (object) ['user' => $names[$uid] ?? null, 'weeks' => array_values($w),
            'max' => collect($w)->max('load')])->filter(fn ($r) => $r->user)->sortByDesc('max')->values();

        // graphs: hours logged a week by Division (12 weeks back), the team's hours ahead (13 weeks), money
        $since = now()->startOfWeek(Carbon::MONDAY)->subWeeks(11);
        $logged = DB::table('project_time_logs as l')->join('projects as p', 'p.id', '=', 'l.project_id')
            ->leftJoin('project_types as pt', 'pt.id', '=', 'p.project_type_id')->leftJoin('teams as t', 't.id', '=', 'pt.team_id')
            ->where('l.start_time', '>=', $since)->whereNull('p.deleted_at')
            ->selectRaw("YEARWEEK(l.start_time, 3) as wk, COALESCE(t.team_name, 'Other') as division, sum(l.total_minutes) / 60 as h")
            ->groupBy('wk', 'division')->get();
        $weekKeys = collect(range(0, 11))->map(fn ($i) => $since->copy()->addWeeks($i));
        $this->hoursChart = [
            'labels' => $weekKeys->map(fn ($w) => $w->format('d M'))->all(),
            'datasets' => $logged->pluck('division')->unique()->values()->map(fn ($division) => [
                str_replace(' Department', '', $division),
                $weekKeys->map(fn ($w) => (float) optional($logged->first(fn ($r) => $r->division === $division && (int) $r->wk === (int) $w->format('oW')))->h)->all(),
            ])->all(),
            'colors' => $logged->pluck('division')->unique()->values()->map(fn ($d) => DB::table('project_types')->join('teams', 'teams.id', '=', 'project_types.team_id')
                ->where('teams.team_name', $d)->value('project_types.color') ?? '#98a2b3')->all(),
        ];

        $ahead = $capacity->periods($people, now()->startOfWeek(Carbon::MONDAY), now()->startOfWeek(Carbon::MONDAY)->addWeeks(13)->subDay(), 'week');
        $aheadWeeks = collect($ahead)->map(fn ($w) => array_values($w))->flatten(1)->groupBy('start')->sortKeys();   // every person's weeks (not merged by key)
        $this->capacityChart = [
            'labels' => $aheadWeeks->map(fn ($w) => $w->first()['label'])->values()->all(),
            'datasets' => [['Hours available', $aheadWeeks->map(fn ($w) => $w->sum('capacity'))->values()->all(), 'line'],
                ['Hours booked', $aheadWeeks->map(fn ($w) => $w->sum('booked'))->values()->all(), 'bar']],
        ];

        $this->canSeeMoney = \App\Services\Tracker\ProjectFinance::canSee();
        if ($this->canSeeMoney) {
            $finance = app(\App\Services\Tracker\ProjectFinance::class);
            $money = $projects->filter(fn ($p) => $p->project_budget > 0 || $p->project_type_id)->map(fn ($p) => ['project' => $p, 'f' => $finance->forProject($p)]);
            $this->money = $money->values();
            $monthly = $money->flatMap(fn ($m) => $m['f']['months'])->groupBy('ym')->sortKeys()->take(-6);
            $this->moneyChart = [
                'labels' => $monthly->map(fn ($rows) => $rows->first()['month'])->values()->all(),
                'datasets' => [['Revenue', $monthly->map(fn ($r) => $r->sum('revenue'))->values()->all(), 'bar'],
                    ['Cost', $monthly->map(fn ($r) => $r->sum('cost'))->values()->all(), 'bar'],
                    ['Profit', $monthly->map(fn ($r) => $r->sum('profit'))->values()->all(), 'line']],
            ];
            $this->cutoff = \App\Services\Tracker\ProjectFinance::cutoff();
        }

        // work due in the next 7 days, and late
        $open = Task::with('project:id,project_name', 'users')->whereIn('project_id', $projectIds)->where('work_type', '!=', 'epic')
            ->when($completed, fn ($q) => $q->where('board_column_id', '!=', $completed))->whereNotNull('due_date');
        $this->dueSoon = (clone $open)->whereBetween('due_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])->orderBy('due_date')->limit(8)->get();
        $this->late = (clone $open)->where('due_date', '<', $today->toDateString())->orderBy('due_date')->limit(8)->get();

        return view('project-tracker.dashboard', $this->data);
    }

    public function planner(Request $request, Capacity $capacity)
    {
        $this->pageTitle = 'Resource Planner';
        $unit = in_array($request->input('unit'), ['day', 'month'], true) ? $request->input('unit') : 'week';
        $from = $unit === 'month' ? now()->startOfMonth() : now()->startOfWeek(Carbon::MONDAY);
        $to = $unit === 'month' ? $from->copy()->addMonths(3)->endOfMonth()->startOfDay() : $from->copy()->addWeeks(13)->subDay();
        // Days: two weeks at a time (from a Monday within the 13 weeks), worked out over the whole 13 weeks
        // so each day's hours add up to the week's
        $this->dayFrom = null;
        if ($unit === 'day') {
            $pick = rescue(fn () => Carbon::parse((string) $request->input('from'))->startOfWeek(Carbon::MONDAY), $from, false);
            $this->dayFrom = $pick->max($from)->min($to->copy()->subDays(13)->startOfWeek(Carbon::MONDAY));
        }
        $scope = $request->input('scope', 'projects');

        $query = User::withoutGlobalScopes()->join('employee_details as ed', 'ed.user_id', '=', 'users.id')
            ->leftJoin('designations as d', 'd.id', '=', 'ed.designation_id')->leftJoin('teams as t', 't.id', '=', 'ed.department_id')
            ->where('users.company_id', company()->id)->where('users.status', 'active')
            ->when($scope !== 'all', fn ($q) => $q->whereIn('users.id', DB::table('project_members')->join('projects', 'projects.id', '=', 'project_members.project_id')
                ->whereNull('projects.deleted_at')->whereNotIn('projects.status', ['finished', 'canceled'])->select('project_members.user_id')))
            ->when($request->division, fn ($q, $d) => $q->whereIn('ed.department_id', Team::withDescendants($d)))
            ->when($request->designation, fn ($q, $d) => $q->where('ed.designation_id', $d))
            ->when($request->project, fn ($q, $p) => $q->whereIn('users.id', DB::table('project_members')->where('project_id', $p)->select('user_id')))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('users.name', 'like', "%$s%")->orWhere('ed.employee_id', 'like', "%$s%")))
            ->orderBy('users.name')
            ->select('users.id', 'users.name', 'users.image', 'users.email', 'ed.employee_id', 'd.name as designation', 't.team_name as division');

        $this->people = $query->paginate(in_array((int) $request->per_page, [25, 50, 100], true) ? (int) $request->per_page : 25)->withQueryString();
        $grid = $capacity->periods($this->people->pluck('id')->all(), $from, $to, $unit);
        if ($unit === 'day') {
            $keep = collect(\Carbon\CarbonPeriod::create($this->dayFrom, $this->dayFrom->copy()->addDays(13)))->map(fn ($d) => $d->toDateString())->flip();
            $grid = array_map(fn ($row) => array_intersect_key($row, $keep->all()), $grid);
        }
        $this->grid = $grid;
        $this->unit = $unit;
        $first = $grid ? reset($grid) : [];
        $this->columns = collect($first)->map(fn ($p) => ['label' => $p['label'], 'start' => $p['start']])->values();

        if ($this->columns->isEmpty()) {
            $this->columns = collect(\Carbon\CarbonPeriod::create($unit === 'day' ? $this->dayFrom : $from, $unit === 'month' ? '1 month' : ($unit === 'day' ? '1 day' : '1 week'),
                $unit === 'day' ? $this->dayFrom->copy()->addDays(13) : $to))
                ->map(fn ($d) => ['label' => $unit === 'month' ? $d->format('M Y') : $d->format('d M'), 'start' => $d->toDateString()])->values();
        }
        if ($unit === 'day') {
            $last = $to->copy()->subDays(13)->startOfWeek(Carbon::MONDAY);
            $this->dayRange = ['from' => $this->dayFrom,
                'prev' => $this->dayFrom->gt($from) ? $this->dayFrom->copy()->subWeeks(2)->max($from)->toDateString() : null,
                'next' => $this->dayFrom->lt($last) ? $this->dayFrom->copy()->addWeeks(2)->min($last)->toDateString() : null];
        } else {
            $this->dayRange = null;
        }

        $this->projectNames = Project::whereIn('id', collect($this->grid)->flatMap(fn ($w) => collect($w)->flatMap(fn ($p) => array_keys($p['byProject'])))->unique())
            ->pluck('project_name', 'id');
        $this->hoursPerDay = $capacity->hoursPerDay();
        $this->divisions = Team::where('company_id', company()->id)->get();
        $this->designations = Designation::where('company_id', company()->id)->orderBy('name')->get(['id', 'name']);
        $this->projectsList = Project::whereNotIn('status', ['finished', 'canceled'])->orderBy('project_name')->get(['id', 'project_name']);

        return view('project-tracker.planner', $this->data);
    }

    /** One person over the next three months: by week and by month, per project, their open work. */
    public function person($userId, Capacity $capacity)
    {
        $this->person = User::withoutGlobalScopes()->with('employeeDetail.designation', 'employeeDetail.department')->findOrFail($userId);
        $from = now()->startOfWeek(Carbon::MONDAY);
        $this->weeks = array_values($capacity->periods([$this->person->id], $from, $from->copy()->addWeeks(13)->subDay(), 'week')[$this->person->id] ?? []);
        $this->months = array_values($capacity->periods([$this->person->id], now()->startOfMonth(), now()->startOfMonth()->addMonths(3)->endOfMonth()->startOfDay(), 'month')[$this->person->id] ?? []);
        $projectIds = collect($this->weeks)->flatMap(fn ($w) => array_keys($w['byProject']))->unique();
        $this->projectNames = Project::whereIn('id', $projectIds)->with('projectType:id,color')->get(['id', 'project_name', 'project_type_id'])->keyBy('id');
        $completed = TaskboardColumn::completeColumn()?->id;
        $this->tasks = Task::with('project:id,project_name')->whereHas('users', fn ($q) => $q->where('users.id', $this->person->id))
            ->where('work_type', '!=', 'epic')->when($completed, fn ($q) => $q->where('board_column_id', '!=', $completed))
            ->orderByRaw('due_date is null, due_date')->limit(12)->get();
        $this->hoursPerDay = $capacity->hoursPerDay();

        return $this->panel('project-tracker.person', 'Availability — ' . $this->person->name);
    }

    /** The booking check: how free the chosen people are between two dates. */
    public function check(Request $request, Capacity $capacity)
    {
        $format = company()->date_format;

        try {
            $from = Carbon::createFromFormat($format, (string) $request->from)->startOfDay();
            $to = Carbon::createFromFormat($format, (string) $request->to)->startOfDay();
        } catch (\Throwable) {
            return response()->json(['people' => []]);
        }

        if ($to->lt($from) || $from->diffInDays($to) > 366) {
            return response()->json(['people' => []]);
        }

        $ids = array_slice(array_map('intval', (array) $request->users), 0, 30);
        $window = $capacity->window($ids, $from, $to);
        // the item being edited is already in "booked": take its own share out
        $own = $request->task ? $this->ownShare((int) $request->task, $ids, $from, $to) : [];
        $names = User::withoutGlobalScopes()->whereIn('id', $ids)->pluck('name', 'id');
        $need = (float) $request->hours / max(1, count($ids));

        return response()->json(['people' => collect($ids)->map(fn ($id) => [
            'id' => $id, 'name' => $names[$id] ?? '?',
            'capacity' => $window[$id]['capacity'] ?? 0,
            'free' => round(($window[$id]['free'] ?? 0) + ($own[$id] ?? 0), 1),
            'need' => round($need, 1),
        ])->values()]);
    }

    /** Hours of this task already counted as booked for each person in the window. */
    private function ownShare(int $taskId, array $ids, Carbon $from, Carbon $to): array
    {
        $task = Task::with('users')->find($taskId);

        if (!$task) {
            return [];
        }

        $est = ((int) $task->estimate_hours * 60 + (int) $task->estimate_minutes) / 60;
        $logged = DB::table('project_time_logs')->where('task_id', $taskId)->sum('total_minutes') / 60;
        $share = max(0, $est - $logged) / max(1, $task->users->count());

        return $task->users->pluck('id')->intersect($ids)->mapWithKeys(fn ($id) => [$id => $share])->all();
    }

}
