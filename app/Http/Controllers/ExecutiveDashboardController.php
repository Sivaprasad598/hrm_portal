<?php

namespace App\Http\Controllers;

use App\Services\Access\ComingSoon;
use App\Services\Dashboard\ExecutiveOverview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Dashboard -> Executive Dashboard (asked for 2026-11-23): the company on one page for the people
 * who run it, in two views -
 *   Company (CEO): people, attendance today, delivery, hiring, with every module's counts;
 *   Finance (CFO): invoices and receivables, payroll cost and statutory dues, project profit.
 * Each opens on the view that suits the person (a CFO on Finance, everyone else on Company) and
 * either can be switched to. Read-only; the figures come from ExecutiveOverview.
 */
class ExecutiveDashboardController extends AccountBaseController
{

    public const ROLES = ['admin', 'manager_admin', 'ceo', 'vice_president', 'cfo'];   // Vice President added 2026-11-24 (as the CEO)

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Executive Dashboard';

        $this->middleware(function ($request, $next) {
            abort_403(!array_intersect(self::ROLES, user_roles()));

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $roles = user_roles();
        $default = in_array('cfo', $roles) && !array_intersect(['ceo', 'vice_president'], $roles) ? 'cfo' : 'ceo';
        $this->mode = in_array($request->query('view'), ['ceo', 'cfo'], true) ? $request->query('view') : $default;

        $o = new ExecutiveOverview();
        $this->people = $o->headcount();
        $this->attendance = $o->attendance();
        $this->officeHours = $o->officeHours();
        $this->hiring = $o->recruitment();
        $this->work = $o->projects();
        $this->assets = $o->assets();
        $this->payroll = $o->payroll();
        $this->invoices = $o->invoices();
        // project profit reads every project's months: only on the Finance view
        $this->projectMoney = $this->mode === 'cfo' ? $o->projectMoney() : null;
        $this->teamDay = $o->teamToday();
        $this->modules = $this->modules();
        $this->attention = $this->attention();
        // the CEO / CFO land here from Dashboard; their own dashboard is a button
        $this->ownDashboard = !array_intersect(['admin', 'manager_admin'], $roles) ? route('dashboard', ['mine' => 1]) : null;
        $this->asOf = now(company()->timezone ?? config('app.timezone'));

        return view('executive-dashboard.index', $this->data);
    }

    /** What is waiting on someone, most pressing first: [count, label, tone, route]; only those above zero. */
    private function attention(): array
    {
        $items = [
            [$this->work['overdue'], 'projects past deadline', 'bad', 'project-tracker.dashboard'],
            [$this->invoices['overdueCount'], 'invoices overdue', 'bad', 'invoices.index'],
            [$this->work['lateTasks'], 'tasks past due date', 'warn', 'tasks.index'],
            [$this->attendance['pendingLeaves'], 'leave requests waiting', 'warn', 'leaves.index'],
            [$this->payroll['last']?->unpaid > 0 ? $this->payroll['last']->slips : 0, 'payslips not marked paid', 'warn', 'payroll-run.index'],
            [$this->hiring['offers'], 'offers waiting for an answer', 'info', 'job-applications.index'],
            [$this->hiring['interviews'], 'interviews in the next 7 days', 'info', 'recruit-interviews.index'],
            [$this->people['leaving'], 'people serving notice', 'info', 'resignation-management.index'],
        ];

        return array_values(array_map(fn ($i) => (object) ['count' => $i[0], 'label' => $i[1], 'tone' => $i[2],
            'url' => Route::has($i[3]) && !ComingSoon::moduleFor($i[3]) ? route($i[3]) : null],
            array_filter($items, fn ($i) => (int) $i[0] > 0)));
    }

    /** One card per module: its figures and where it opens (a module marked Coming soon says so). */
    private function modules(): array
    {
        $p = $this->people;
        $a = $this->attendance;
        $h = $this->hiring;
        $w = $this->work;
        $s = $this->assets;
        $pay = $this->payroll['last'];
        $inv = $this->invoices;

        $cards = [
            ['People', 'employees.index', 'fa-users', '#3d78e3', [
                ['Active employees', $p['active']], ['Joined this month', $p['joined'], 'good'], ['Serving notice', $p['leaving'], $p['leaving'] ? 'warn' : null], ['Left this month', $p['exited']]]],
            ['Attendance today', 'attendances.index', 'fa-user-check', '#12b76a', [
                ['Present', $a['present'] . ' (' . $a['pct'] . '%)'], ['On leave', $a['onLeave']], ['Not clocked in', $a['absent'], $a['absent'] ? 'warn' : null], ['Late', $a['late']]]],
            ['Leave', 'leaves.index', 'fa-calendar-check', '#0ba5ec', [
                ['Requests waiting', $a['pendingLeaves'], $a['pendingLeaves'] ? 'warn' : null], ['On leave today', $a['onLeave']], ['Holiday today', $a['holiday'] ?: 'No']]],
            ['Payroll', 'payroll-dashboard.index', 'fa-wallet', '#7a5af8', $pay ? [
                ['Cost to company · ' . $pay->name, ['money', $pay->ctc]], ['Payslips', $pay->slips], ['Net pay', ['money', $pay->net]], ['Not marked paid', ['money', $pay->unpaid], $pay->unpaid ? 'warn' : null]]
                : [['Last run', 'None yet']]],
            ['Recruitment', 'recruitment.dashboard', 'fa-user-plus', '#ee46bc', [
                ['Open positions', $h['positions']], ['Openings', $h['openings']], ['Candidates in play', $h['pipeline']], ['Interviews next 7 days', $h['interviews']]]],
            ['Projects', 'project-tracker.dashboard', 'fa-project-diagram', '#cb6b4f', [
                ['Active', $w['active']], ['Past deadline', $w['overdue'], $w['overdue'] ? 'bad' : null], ['Due in 14 days', $w['dueSoon']], ['Hours this month', number_format($w['hours'])]]],
            ['Tasks', 'tasks.index', 'fa-tasks', '#f79009', [
                ['Open', $w['openTasks']], ['Past due date', $w['lateTasks'], $w['lateTasks'] ? 'bad' : null]]],
            ['Assets', 'assets.index', 'fa-laptop', '#344054', [
                ['In use or ready', $s['total']], ['Assigned', $s['assigned']], ['Available', $s['free']], ['Under repair', $s['repair'], $s['repair'] ? 'warn' : null]]],
            ['Finance', 'invoices.index', 'fa-file-invoice', '#067647', [
                ['Invoiced FY ' . $inv['fy'], ['money', $inv['invoiced']]], ['Outstanding', ['money', $inv['outstanding']]], ['Overdue', ['money', $inv['overdue']], $inv['overdue'] ? 'bad' : null], ['Drafts', $inv['drafts']]]],
            ['Clients', 'clients.index', 'fa-handshake', '#475467', [
                ['Active clients', $w['clients']], ['Invoices this FY', $inv['count']]]],
        ];

        return array_map(function ($c) {
            [$title, $route, $icon, $colour, $stats] = $c;

            return (object) ['title' => $title, 'icon' => $icon, 'colour' => $colour, 'stats' => $stats,
                'url' => Route::has($route) ? route($route) : null,
                'soon' => ComingSoon::moduleFor($route) !== null];
        }, $cards);
    }

}
