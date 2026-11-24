<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The figures behind the Executive Dashboard (ExecutiveDashboardController): every module counted
 * in one place, read-only, for the CEO view (the company at a glance) and the CFO view (the money).
 *
 * Each part reads its own tables and answers zeros when a module is not installed, so one missing
 * module never takes the page down. Only live rows count: active people, payslips not revoked,
 * invoices not drafts or cancelled.
 */
class ExecutiveOverview
{

    private int $company;
    private Carbon $today;
    private ?array $peopleIds = null;

    public function __construct()
    {
        $this->company = (int) company()->id;
        $this->today = now(company()->timezone ?? config('app.timezone'))->startOfDay();
    }

    /** Never counted, for anyone: the Super Admin login and the test sign-ins. @return array<int, int> */
    private function notCounted(): array
    {
        return array_values(array_unique(array_merge(\App\Services\Access\SystemAccounts::ids(), \App\Services\Access\SystemAccounts::testIds()))) ?: [0];
    }

    /** Active employees: the user is active and has an employee record (no exit date passed). */
    private function people(): array
    {
        return $this->peopleIds ??= DB::table('employee_details as e')->join('users as u', 'u.id', '=', 'e.user_id')
            ->where('e.company_id', $this->company)->where('u.status', 'active')
            ->where(fn ($q) => $q->whereNull('e.last_date')->orWhere('e.last_date', '>=', $this->today->toDateString()))
            ->whereNotIn('e.user_id', $this->notCounted())
            ->pluck('e.user_id')->map(fn ($id) => (int) $id)->all();
    }

    // ---------------------------------------------------------------- people

    public function headcount(): array
    {
        $ids = $this->people();
        $monthStart = $this->today->copy()->startOfMonth()->toDateString();

        $joined = DB::table('employee_details')->whereIn('user_id', $ids)->where('joining_date', '>=', $monthStart)->count();
        $leaving = Schema::hasTable('resignations')
            ? DB::table('resignations')->where('company_id', $this->company)->whereIn('status', ['pending', 'approved', 'accepted'])
                ->where(fn ($q) => $q->whereNull('last_working_date')->orWhere('last_working_date', '>=', $this->today->toDateString()))->count()
            : 0;
        $exitedThisMonth = DB::table('employee_details')->where('company_id', $this->company)->whereNotIn('user_id', $this->notCounted())->whereBetween('last_date', [$monthStart, $this->today->toDateString()])->count();
        $probation = DB::table('employee_details')->whereIn('user_id', $ids)->where('employment_type', 'probation')->count();

        $divisions = DB::table('employee_details as e')->leftJoin('teams as t', 't.id', '=', 'e.department_id')
            ->whereIn('e.user_id', $ids)->selectRaw("COALESCE(t.team_name, 'No Division') as name, count(*) as c")
            ->groupBy('name')->orderByDesc('c')->get();

        // twelve month-ends back: people on the books then, joined and left in the month
        $trend = collect(range(11, 0))->map(function ($back) {
            $start = $this->today->copy()->startOfMonth()->subMonths($back);
            $end = $start->copy()->endOfMonth();
            $base = DB::table('employee_details')->where('company_id', $this->company)->whereNotIn('user_id', $this->notCounted());

            return [
                'label' => $start->format('M y'),
                'count' => (clone $base)->where(fn ($q) => $q->whereNull('joining_date')->orWhere('joining_date', '<=', $end->toDateString()))
                    ->where(fn ($q) => $q->whereNull('last_date')->orWhere('last_date', '>', $end->toDateString()))->count(),
                'joined' => (clone $base)->whereBetween('joining_date', [$start->toDateString(), $end->toDateString()])->count(),
                'left' => (clone $base)->whereBetween('last_date', [$start->toDateString(), $end->toDateString()])->count(),
            ];
        })->all();

        return ['active' => count($ids), 'joined' => $joined, 'leaving' => $leaving, 'exited' => $exitedThisMonth,
            'probation' => $probation, 'divisions' => $divisions, 'trend' => $trend];
    }

    public function attendance(): array
    {
        $ids = $this->people();
        $day = $this->today->toDateString();

        $present = DB::table('attendances')->whereIn('user_id', $ids)->whereDate('clock_in_time', $day)
            ->where(fn ($q) => $q->whereNull('leave_code')->orWhere('leave_code', ''))->distinct()->count('user_id');
        $late = DB::table('attendances')->whereIn('user_id', $ids)->whereDate('clock_in_time', $day)->where('late', 'yes')->distinct()->count('user_id');
        $onLeave = DB::table('leaves')->whereIn('user_id', $ids)->where('leave_date', $day)->where('status', 'approved')->distinct()->count('user_id');
        $pendingLeaves = DB::table('leaves')->whereIn('user_id', $ids)->where('status', 'pending')->count();
        $holiday = DB::table('holidays')->where('company_id', $this->company)->whereDate('date', $day)->value('occassion');

        // the last fourteen days: share of the active people who clocked in
        $since = $this->today->copy()->subDays(13);
        $byDay = DB::table('attendances')->whereIn('user_id', $ids)->where('clock_in_time', '>=', $since)
            ->selectRaw('DATE(clock_in_time) as d, count(distinct user_id) as c')->groupBy('d')->pluck('c', 'd');
        $trend = collect(range(13, 0))->map(function ($back) use ($byDay, $ids) {
            $d = $this->today->copy()->subDays($back);

            return ['label' => $d->format('d M'), 'pct' => count($ids) ? round(($byDay[$d->toDateString()] ?? 0) / count($ids) * 100, 1) : 0];
        })->all();

        return ['present' => $present, 'late' => $late, 'onLeave' => $onLeave, 'pendingLeaves' => $pendingLeaves, 'holiday' => $holiday,
            'absent' => max(0, count($ids) - $present - $onLeave), 'pct' => count($ids) ? (int) round($present / count($ids) * 100) : 0, 'trend' => $trend];
    }

    /**
     * Team today, as on the private dashboard: what waits for this person's decision, their own team
     * (anyone reporting to them) in / on leave / not in, and the company's day - on leave, working from
     * home, birthdays this week, work anniversaries, joinings in the next 30 days.
     */
    public function teamToday(): array
    {
        $tz = company()->timezone ?: 'Asia/Kolkata';
        $today = now($tz)->startOfDay();
        $ids = $this->people();

        $leaves = \App\Services\Leaves\LeaveApprovals::waitingFor()->whereNotIn('user_id', $this->notCounted())
            ->unique(fn ($l) => $l->unique_id ?: 'id' . $l->id)->values();
        $corrections = \App\Services\Attendance\CorrectionApprovals::waitingFor()->whereNotIn('user_id', $this->notCounted())->values();

        // their own team (people reporting to them), counted as payroll counts the day
        $teamIds = \App\Services\Attendance\CorrectionApprovals::teamUserIds();
        $team = null;
        if ($teamIds) {
            $teamIds = array_values(array_diff($teamIds, $this->notCounted()));
            $team = ['size' => count($teamIds), 'in' => [], 'leave' => [], 'out' => []];
            $counter = new \App\Services\Payroll\AttendanceDays($tz);
            foreach (\App\Models\User::whereIn('id', $teamIds)->orderBy('name')->limit(60)->get(['id', 'name']) as $m) {
                try {
                    $day = $counter->forRange((int) $m->id, $today, $today)['days'][0] ?? null;
                } catch (\Throwable $e) {
                    report($e);
                    $day = null;
                }
                $status = $day['status'] ?? 'upcoming';
                $bucket = match (true) {
                    in_array($status, ['paid_leave', 'half_leave', 'lop_leave', 'half_day_leave'], true) => 'leave',
                    in_array($status, ['week_off', 'holiday', 'not_employed'], true) => null,
                    !empty($day['first_in']) || $status === 'present' => 'in',
                    default => 'out',
                };
                if ($bucket) {
                    $team[$bucket][] = $m->name;
                }
            }
        }

        $onLeave = DB::table('leaves as l')->join('users as u', 'u.id', '=', 'l.user_id')->leftJoin('leave_types as t', 't.id', '=', 'l.leave_type_id')
            ->whereIn('l.user_id', $ids)->where('l.status', 'approved')->where('l.leave_date', $today->toDateString())
            ->orderBy('u.name')->get(['u.id', 'u.name', 'l.duration', 'l.half_day_type', 't.type_name', 't.color']);
        $wfh = DB::table('attendances as a')->join('users as u', 'u.id', '=', 'a.user_id')->whereIn('a.user_id', $ids)
            ->where('a.work_from_type', 'home')->whereBetween('a.clock_in_time', [$today->copy()->utc(), $today->copy()->endOfDay()->utc()])
            ->distinct()->orderBy('u.name')->pluck('u.name');

        // birthdays in the next seven days (month-day, across a year end)
        $week = collect(range(0, 6))->map(fn ($i) => $today->copy()->addDays($i));
        $people = DB::table('employee_details as e')->join('users as u', 'u.id', '=', 'e.user_id')->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')
            ->whereIn('e.user_id', $ids);
        $birthdays = (clone $people)->whereNotNull('e.date_of_birth')
            ->whereIn(DB::raw("DATE_FORMAT(e.date_of_birth, '%m-%d')"), $week->map(fn ($d) => $d->format('m-d'))->all())
            ->get(['u.id', 'u.name', 'e.date_of_birth', 'd.name as designation'])
            ->map(function ($p) use ($today) {
                $dob = Carbon::parse($p->date_of_birth);
                $next = $dob->copy()->year($today->year);
                if ($next->lt($today)) {
                    $next->addYear();
                }
                $p->on = $next;
                $p->days = (int) $today->diffInDays($next);

                return $p;
            })->sortBy('days')->values();

        $anniversaries = (clone $people)->whereNotNull('e.joining_date')->where('e.joining_date', '<', $today->toDateString())
            ->whereIn(DB::raw("DATE_FORMAT(e.joining_date, '%m-%d')"), $week->map(fn ($d) => $d->format('m-d'))->all())
            ->get(['u.id', 'u.name', 'e.joining_date', 'd.name as designation'])
            ->map(function ($p) use ($today) {
                $joined = Carbon::parse($p->joining_date);
                $next = $joined->copy()->year($today->year);
                if ($next->lt($today)) {
                    $next->addYear();
                }
                $p->on = $next;
                $p->days = (int) $today->diffInDays($next);
                $p->years = $joined->diffInYears($next);

                return $p;
            })->filter(fn ($p) => $p->years >= 1)->sortBy('days')->values();

        $joinings = DB::table('employee_details as e')->join('users as u', 'u.id', '=', 'e.user_id')->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')
            ->where('e.company_id', $this->company)->whereNotIn('e.user_id', $this->notCounted())
            ->whereBetween('e.joining_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])
            ->orderBy('e.joining_date')->limit(8)->get(['u.id', 'u.name', 'e.joining_date', 'd.name as designation']);

        return ['leaves' => $leaves, 'corrections' => $corrections, 'team' => $team, 'onLeave' => $onLeave, 'wfh' => $wfh,
            'birthdays' => $birthdays, 'anniversaries' => $anniversaries, 'joinings' => $joinings, 'today' => $today];
    }

    public function recruitment(): array
    {
        if (!Schema::hasTable('job_positions')) {
            return ['positions' => 0, 'openings' => 0, 'pipeline' => 0, 'interviews' => 0, 'offers' => 0, 'joined' => 0];
        }

        $open = DB::table('job_positions')->where('company_id', $this->company)->where('status', 'open');
        $apps = DB::table('job_applications')->where('company_id', $this->company);

        return [
            'positions' => (clone $open)->count(),
            'openings' => (int) (clone $open)->sum('openings'),
            'pipeline' => (clone $apps)->whereIn('stage', \App\Models\JobApplication::ACTIVE)->count(),
            'interviews' => DB::table('recruit_interviews')->where('company_id', $this->company)->where('status', 'scheduled')
                ->whereBetween('starts_at', [$this->today->copy()->utc(), $this->today->copy()->addDays(7)->utc()])->count(),
            'offers' => (clone $apps)->where('stage', 'offered')->count(),
            'joined' => (clone $apps)->where('stage', 'joined')->where('stage_changed_at', '>=', $this->today->copy()->startOfMonth())->count(),
        ];
    }

    // ---------------------------------------------------------------- work

    public function projects(): array
    {
        $live = DB::table('projects')->where('company_id', $this->company)->whereNull('deleted_at');
        $active = (clone $live)->whereNotIn('status', ['finished', 'canceled']);
        $day = $this->today->toDateString();

        $overdue = (clone $active)->whereNotNull('deadline')->where('deadline', '<', $day)->count();
        $dueSoon = (clone $active)->whereBetween('deadline', [$day, $this->today->copy()->addDays(14)->toDateString()])->count();
        $hours = round(DB::table('project_time_logs')->where('company_id', $this->company)
            ->where('start_time', '>=', $this->today->copy()->startOfMonth())->sum('total_minutes') / 60);

        $tasks = DB::table('tasks')->where('company_id', $this->company)->whereNull('deleted_at');
        $done = DB::table('taskboard_columns')->where('company_id', $this->company)->where('slug', 'completed')->value('id');
        $openTasks = (clone $tasks)->when($done, fn ($q) => $q->where('board_column_id', '!=', $done))->count();
        $lateTasks = (clone $tasks)->when($done, fn ($q) => $q->where('board_column_id', '!=', $done))->whereNotNull('due_date')->where('due_date', '<', $day)->count();

        $late = (clone $active)->whereNotNull('deadline')->where('deadline', '<', $day)->orderBy('deadline')->limit(5)
            ->get(['id', 'project_name', 'deadline', 'completion_percent']);

        return ['active' => (clone $active)->count(), 'finished' => (clone $live)->where('status', 'finished')->count(),
            'overdue' => $overdue, 'dueSoon' => $dueSoon, 'hours' => $hours, 'openTasks' => $openTasks, 'lateTasks' => $lateTasks,
            'clients' => DB::table('users')->join('role_user', 'role_user.user_id', '=', 'users.id')->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->where('roles.name', 'client')->where('users.company_id', $this->company)->where('users.status', 'active')->count(),
            'late' => $late];
    }

    /** Revenue, cost and profit of the open projects (ProjectFinance, as the Project Dashboard shows). */
    public function projectMoney(): array
    {
        $finance = app(\App\Services\Tracker\ProjectFinance::class);
        $rows = \App\Models\Project::whereNotIn('status', ['finished', 'canceled'])->orderBy('project_name')->get()
            ->map(function ($p) use ($finance) {
                $t = $finance->forProject($p)['totals'];

                return (object) ['id' => $p->id, 'name' => $p->project_name, 'revenue' => $t['revenue'], 'cost' => $t['cost'], 'profit' => $t['profit'], 'margin' => $t['margin']];
            })->sortByDesc('revenue')->values();

        $revenue = $rows->sum('revenue');

        return ['rows' => $rows, 'revenue' => $revenue, 'cost' => $rows->sum('cost'), 'profit' => $rows->sum('profit'),
            'margin' => $revenue > 0 ? round($rows->sum('profit') / $revenue * 100, 1) : null];
    }

    public function assets(): array
    {
        if (!Schema::hasTable('assets')) {
            return ['total' => 0, 'assigned' => 0, 'free' => 0, 'repair' => 0, 'value' => 0, 'serviceDue' => 0, 'warrantyEnding' => 0];
        }

        $all = DB::table('assets')->where('company_id', $this->company);
        $assigned = (clone $all)->where('status', 'assigned')->count();
        $total = (clone $all)->whereNotIn('status', ['disposed', 'lost'])->count();
        $soon = $this->today->copy()->addDays(30)->toDateString();

        return [
            'total' => $total,
            'assigned' => $assigned,
            'free' => (clone $all)->where('status', 'available')->count(),
            'repair' => (clone $all)->where('status', 'repair')->count(),
            'value' => (float) (clone $all)->whereNotIn('status', ['disposed', 'lost'])->sum('purchase_cost'),
            'serviceDue' => (clone $all)->whereNotNull('next_service_on')->where('next_service_on', '<=', $soon)->count(),
            'warrantyEnding' => (clone $all)->whereBetween('warranty_until', [$this->today->toDateString(), $soon])->count(),
        ];
    }

    // ---------------------------------------------------------------- money

    /** Payroll by pay period (payslips not revoked): the last six, newest last. */
    public function payroll(): array
    {
        $rows = DB::table('payslips')->whereNull('deleted_at')->where('status', '!=', 'revoked')
            ->where('company_id', $this->company)->whereNotNull('period_start')->whereNotIn('user_id', $this->notCounted())
            ->selectRaw('period_start, max(period) as label, count(*) as slips, sum(gross) as gross, sum(net_salary) as net,
                sum(coalesce(employer_pf, 0) + coalesce(employer_esi, 0)) as employer, sum(total_deductions) as deductions,
                sum(coalesce(pf, 0)) as pf, sum(coalesce(esi, 0)) as esi, sum(coalesce(pt, 0)) as pt, sum(coalesce(tds, 0)) as tds,
                sum(case when paid_at is null then net_salary else 0 end) as unpaid')
            ->groupBy('period_start')->orderByDesc('period_start')->limit(6)->get()->reverse()->values();

        $last = $rows->last();

        return [
            'periods' => $rows->map(fn ($r) => (object) ['label' => Carbon::parse($r->period_start)->addDays(10)->format('M y'), 'name' => $r->label,
                'slips' => (int) $r->slips, 'gross' => (float) $r->gross, 'net' => (float) $r->net, 'employer' => (float) $r->employer,
                'ctc' => (float) $r->gross + (float) $r->employer, 'deductions' => (float) $r->deductions])->all(),
            'last' => $last ? (object) ['name' => $last->label, 'slips' => (int) $last->slips, 'gross' => (float) $last->gross, 'net' => (float) $last->net,
                'ctc' => (float) $last->gross + (float) $last->employer, 'unpaid' => (float) $last->unpaid,
                'statutory' => (float) $last->pf + (float) $last->esi + (float) $last->pt + (float) $last->tds + (float) $last->employer,
                'pf' => (float) $last->pf, 'esi' => (float) $last->esi, 'pt' => (float) $last->pt, 'tds' => (float) $last->tds, 'employerShare' => (float) $last->employer] : null,
            'settlements' => Schema::hasTable('settlements') ? DB::table('settlements')->where('company_id', $this->company)->whereNotIn('status', ['paid', 'cancelled'])->count() : 0,
        ];
    }

    /** GST invoices this financial year (April to March): issued, outstanding, overdue, ageing, by month and client. */
    public function invoices(): array
    {
        $fyStart = Carbon::create($this->today->month >= 4 ? $this->today->year : $this->today->year - 1, 4, 1);
        $issued = DB::table('invoices')->where('invoices.company_id', $this->company)->whereNotIn('invoices.status', ['draft', 'canceled'])
            ->where('invoices.issue_date', '>=', $fyStart->toDateString());
        $open = DB::table('invoices')->where('company_id', $this->company)->whereIn('status', ['unpaid', 'partial']);
        $owed = 'coalesce(nullif(due_amount, 0), total)';

        $ageing = ['Not due' => 0, '1–30 days' => 0, '31–60 days' => 0, '61–90 days' => 0, '90+ days' => 0];
        foreach ((clone $open)->get(['due_date', 'total', 'due_amount']) as $inv) {
            $late = $inv->due_date ? (int) Carbon::parse($inv->due_date)->diffInDays($this->today, false) : 0;
            $key = $late <= 0 ? 'Not due' : ($late <= 30 ? '1–30 days' : ($late <= 60 ? '31–60 days' : ($late <= 90 ? '61–90 days' : '90+ days')));
            $ageing[$key] += (float) ($inv->due_amount > 0 ? $inv->due_amount : $inv->total);
        }

        $months = collect(range(0, 11))->map(fn ($i) => $fyStart->copy()->addMonths($i))->filter(fn ($m) => $m->lte($this->today));
        $byMonth = (clone $issued)->selectRaw("DATE_FORMAT(issue_date, '%Y-%m') as ym, sum(total) as t")->groupBy('ym')->pluck('t', 'ym');

        $clients = (clone $issued)->leftJoin('client_details as c', 'c.user_id', '=', 'invoices.client_id')->leftJoin('users as u', 'u.id', '=', 'invoices.client_id')
            ->selectRaw("COALESCE(NULLIF(c.company_name, ''), u.name, 'Unknown') as client_name, sum(invoices.total) as total, count(*) as n")
            ->groupBy('client_name')->orderByDesc('total')->limit(5)->get();

        return [
            'fy' => $fyStart->format('Y') . '–' . $fyStart->copy()->addYear()->format('y'),
            'count' => (clone $issued)->count(),
            'invoiced' => (float) (clone $issued)->sum('total'),
            'gst' => (float) (clone $issued)->sum(DB::raw('coalesce(cgst, 0) + coalesce(sgst, 0) + coalesce(igst, 0)')),
            'collected' => (float) (clone $issued)->where('status', 'paid')->sum('total'),
            'outstanding' => (float) (clone $open)->sum(DB::raw($owed)),
            'overdue' => (float) (clone $open)->where('due_date', '<', $this->today->toDateString())->sum(DB::raw($owed)),
            'overdueCount' => (clone $open)->where('due_date', '<', $this->today->toDateString())->count(),
            'drafts' => DB::table('invoices')->where('company_id', $this->company)->where('status', 'draft')->count(),
            'ageing' => $ageing,
            'months' => ['labels' => $months->map(fn ($m) => $m->format('M'))->values()->all(), 'values' => $months->map(fn ($m) => (float) ($byMonth[$m->format('Y-m')] ?? 0))->values()->all()],
            'clients' => $clients,
        ];
    }

}
