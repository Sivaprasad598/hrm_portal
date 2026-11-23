<?php

namespace App\Http\Controllers\Tracker;

use App\Models\Designation;
use App\Models\Team;
use App\Models\User;
use App\Services\Tracker\EmployeeKpi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Work -> Employee KPIs: everyone who worked on projects in a month, best first, and one person's
 * KPIs (side panel; the same block is the KPI tab on the employee's page and My Space -> My KPI).
 * Read from App\Services\Tracker\EmployeeKpi.
 */
class TrackerKpiController extends TrackerBaseController
{

    public function index(Request $request, EmployeeKpi $kpi)
    {
        abort_403(!EmployeeKpi::canSeeAll());
        $this->pageTitle = 'Employee KPIs';
        $this->month = self::month($request->month);
        $from = $this->month->copy()->startOfMonth();
        $to = $this->month->copy()->endOfMonth();

        // who worked on projects that month: hours logged, or work credited to them
        $credited = DB::table('tracker_progress_logs')->whereBetween('logged_at', [$from, $to])->pluck('assignee_ids')
            ->flatMap(fn ($j) => json_decode((string) $j, true) ?: [])->unique()->all();
        $people = User::withoutGlobalScopes()->join('employee_details as ed', 'ed.user_id', '=', 'users.id')
            ->leftJoin('designations as d', 'd.id', '=', 'ed.designation_id')->leftJoin('teams as t', 't.id', '=', 'ed.department_id')
            ->where('users.company_id', company()->id)->where('users.status', 'active')
            ->where(fn ($q) => $q->whereIn('users.id', DB::table('project_time_logs')->whereBetween('start_time', [$from, $to])->select('user_id'))
                ->orWhereIn('users.id', $credited ?: [0]))
            ->when($request->division, fn ($q, $d) => $q->whereIn('ed.department_id', Team::withDescendants($d)))
            ->when($request->designation, fn ($q, $d) => $q->where('ed.designation_id', $d))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('users.name', 'like', "%$s%")->orWhere('ed.employee_id', 'like', "%$s%")))
            ->get(['users.id', 'users.name', 'users.image', 'users.email', 'ed.employee_id', 'd.name as designation', 't.team_name as division']);

        $scores = $kpi->month($people->pluck('id')->all(), $this->month);
        $rows = $people->map(function ($p) use ($scores) {
            $p->kpi = $scores[$p->id] ?? null;

            return $p;
        })->sortBy([fn ($a, $b) => ($b->kpi['score'] ?? -1) <=> ($a->kpi['score'] ?? -1), fn ($a, $b) => strcmp($a->name, $b->name)])->values();

        $scored = $rows->pluck('kpi.score')->filter(fn ($v) => $v !== null);
        $this->summary = [
            'people' => $rows->count(),
            'scored' => $scored->count(),
            'avg' => $scored->count() ? (int) round($scored->avg()) : null,
            'utilisation' => ($u = $rows->pluck('kpi.metrics.utilisation')->filter(fn ($v) => $v !== null))->count() ? (int) round($u->avg()) : null,
            'hours' => round($rows->sum('kpi.hours'), 1),
            'rework' => $rows->sum('kpi.rework'),
        ];
        $this->bands = ['Under 50' => $scored->filter(fn ($s) => $s < 50)->count(), '50–69' => $scored->filter(fn ($s) => $s >= 50 && $s < 70)->count(),
            '70–84' => $scored->filter(fn ($s) => $s >= 70 && $s < 85)->count(), '85+' => $scored->filter(fn ($s) => $s >= 85)->count()];

        $perPage = in_array((int) $request->per_page, [25, 50, 100], true) ? (int) $request->per_page : 25;
        $page = max(1, (int) $request->page);
        $this->people = (new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]));
        $this->rankFrom = ($page - 1) * $perPage;
        $this->months = collect(range(0, 11))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $this->divisions = Team::where('company_id', company()->id)->get();
        $this->designations = Designation::where('company_id', company()->id)->orderBy('name')->get(['id', 'name']);

        return view('project-tracker.kpi.index', $this->data);
    }

    /** One person's KPIs (side panel, or a page of its own). */
    public function show(Request $request, $userId, EmployeeKpi $kpi)
    {
        abort_403(!EmployeeKpi::canSee((int) $userId));
        $this->person = User::withoutGlobalScopes()->with('employeeDetail.designation', 'employeeDetail.department')->findOrFail($userId);
        $this->month = self::month($request->month);
        $this->kpiProfile = $kpi->profile($this->person->id, $this->month);
        $this->months = collect(range(0, 11))->map(fn ($i) => now()->startOfMonth()->subMonths($i));

        return $this->panel('project-tracker.kpi.person', 'KPIs — ' . $this->person->name);
    }

    public static function month(?string $ym): Carbon
    {
        $month = $ym && preg_match('/^\d{4}-\d{2}$/', $ym) ? Carbon::createFromFormat('Y-m-d', $ym . '-01')->startOfMonth() : now()->startOfMonth();

        return $month->gt(now()) ? now()->startOfMonth() : $month;
    }

}
