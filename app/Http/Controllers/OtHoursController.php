<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Services\Attendance\WorkRules;
use App\Services\Payroll\OtBank;
use App\Services\Payroll\PayPeriod;
use App\Services\Payroll\SalaryCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Payroll -> OT Hours: every employee's overtime by salary period for a year,
 * what was used to cover LOP or encashed, and the balance they hold - in
 * hours, days and money. A month opens its day-by-day overtime.
 *
 * Figures are bank hours: overtime x its rate (a week-off hour at 1.5x is
 * 1.5 bank hours), from ot_daily and ot_ledger - and, for the months before
 * go-live, from the past records (ot_past, entered or imported).
 */
class OtHoursController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'OT Hours';

        $this->middleware(function ($request, $next) {
            abort_403(!\App\Services\Access\PayrollAccess::can('settings'));

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $current = PayPeriod::containing(now());
        $this->year = (int) ($request->year ?: $current->end->year);
        $this->month = (int) $request->month;
        $this->departmentId = $request->department;
        $this->search = trim((string) $request->search);
        $this->show = $request->show === 'all' ? 'all' : 'with_ot';
        $oldest = (int) (DB::table('ot_past')->min('year') ?: now()->year);
        $this->years = range((int) now()->year, min($oldest, (int) now()->year - 3));
        $this->departments = DB::table('teams')->orderBy('team_name')->pluck('team_name', 'id');
        $this->rules = WorkRules::forCompany(company()->id);
        $this->goLive = \App\Services\Payroll\OtPast::goLive(company()->id);

        // The salary periods of the year: "January" is 21 Dec - 20 Jan, and so on.
        $months = $this->month ? [$this->month] : range(1, 12);
        $this->periods = collect($months)->mapWithKeys(fn ($m) => [$m => PayPeriod::forMonth($m, $this->year)]);
        $from = $this->periods->first()->start->toDateString();
        $to = $this->periods->last()->end->toDateString();

        $employees = DB::table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('teams as t', 't.id', '=', 'ed.department_id')
            ->where('u.status', 'active')
            ->whereNotIn('u.id', \App\Services\Access\SystemAccounts::ids())   // not the Super Admin login
            ->when($this->departmentId, fn ($q, $d) => $q->where('ed.department_id', $d))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('u.name', 'like', '%' . $this->search . '%')
                ->orWhere('ed.employee_id', 'like', '%' . $this->search . '%')))
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.employee_ctc', 'u.basic_for_payroll', 'ed.employee_id', 't.team_name as department']);

        $ids = $employees->pluck('id')->all();

        // Bank minutes per employee per period (a day from the 21st belongs to the next month's period).
        $startDay = PayPeriod::configuredStartDay();
        $byPeriod = DB::table('ot_daily')->whereIn('user_id', $ids ?: [0])->whereBetween('date', [$from, $to])
            ->selectRaw("user_id, IF(DAY(`date`) >= ?, DATE_FORMAT(DATE_ADD(`date`, INTERVAL 1 MONTH), '%c'), DATE_FORMAT(`date`, '%c')) AS m,
                SUM(bank_minutes) AS bank, SUM(ot_minutes) AS ot, COUNT(*) AS days", [$startDay])
            ->groupBy('user_id', 'm')->get()
            ->groupBy('user_id');

        $ledger = DB::table('ot_ledger')->whereIn('user_id', $ids ?: [0])->whereBetween('period_start', [$from, $to])
            ->whereIn('type', [OtBank::COMPENSATED, OtBank::ENCASHED])
            ->selectRaw('user_id, type, SUM(hours) AS hours, SUM(days) AS days, SUM(amount) AS amount')
            ->groupBy('user_id', 'type')->get()->groupBy('user_id');

        // Past records of these months (kept before the system).
        $past = DB::table('ot_past')->whereIn('user_id', $ids ?: [0])->where('year', $this->year)->whereIn('month', $months)
            ->selectRaw('user_id, month, SUM(ot_hours) AS ot, SUM(used_hours) AS used, SUM(used_days) AS used_days,
                SUM(encashed_hours) AS enc, SUM(encashed_amount) AS enc_amount, COUNT(`date`) AS days')
            ->groupBy('user_id', 'month')->get()->groupBy('user_id');

        $bank = new OtBank();
        $dayHours = $bank->dayHours(company()->id);
        $calc = new SalaryCalculator();
        $hoursPerDay = (float) ($this->rules->s->wh_work_hours ?? 8) ?: 8.0;
        $periodDays = $current->days();

        $rows = [];
        $totals = ['earned' => 0, 'used_hours' => 0, 'used_days' => 0, 'encashed_hours' => 0, 'encashed_amount' => 0, 'balance' => 0, 'balance_amount' => 0];

        foreach ($employees as $e) {
            $periods = $byPeriod->get($e->id, collect())->keyBy('m');
            $old = $past->get($e->id, collect())->keyBy('month');
            $cells = [];
            $earned = 0.0;

            foreach ($this->periods as $m => $p) {
                $minutes = (int) ($periods[$m]->bank ?? 0);
                $pastHours = (float) ($old[$m]->ot ?? 0);
                $cells[$m] = ['hours' => $minutes / 60 + $pastHours, 'ot' => (int) ($periods[$m]->ot ?? 0) / 60,
                    'days' => (int) ($periods[$m]->days ?? 0) + (int) ($old[$m]->days ?? 0), 'past' => isset($old[$m])];
                $earned += $minutes / 60 + $pastHours;
            }

            $l = $ledger->get($e->id, collect())->keyBy('type');
            $usedHours = -(float) ($l[OtBank::COMPENSATED]->hours ?? 0) + (float) $old->sum('used');
            $usedDays = (float) ($l[OtBank::COMPENSATED]->days ?? 0) + (float) $old->sum('used_days');
            $encHours = -(float) ($l[OtBank::ENCASHED]->hours ?? 0) + (float) $old->sum('enc');
            $encAmount = (float) ($l[OtBank::ENCASHED]->amount ?? 0) + (float) $old->sum('enc_amount');
            $balance = $bank->balance((int) $e->id);

            if ($this->show === 'with_ot' && $earned == 0.0 && $balance == 0.0 && $usedHours == 0.0 && $encHours == 0.0) {
                continue;
            }

            // What an hour is worth to this employee today.
            $hourly = 0.0;
            if ((float) $e->employee_ctc > 0) {
                $pay = $calc->forEmployee((float) $e->employee_ctc, $e->basic_for_payroll !== null ? (float) $e->basic_for_payroll : null, 30, 30.0);
                $base = ($this->rules->s->ot_basis ?? 'gross') === 'basic' ? (float) $pay['basic'] : (float) $pay['gross'];
                $hourly = $base / max(1, $periodDays * $hoursPerDay);
            }

            $rows[] = [
                'id' => $e->id, 'name' => $e->name, 'code' => $e->employee_id, 'department' => $e->department,
                'cells' => $cells, 'earned' => $earned, 'used_hours' => $usedHours, 'used_days' => $usedDays,
                'encashed_hours' => $encHours, 'encashed_amount' => $encAmount, 'balance' => $balance,
                'balance_days' => $dayHours > 0 ? $balance / $dayHours : 0, 'balance_amount' => $balance * $hourly, 'hourly' => $hourly,
            ];

            foreach (['earned', 'used_hours', 'used_days', 'encashed_hours', 'encashed_amount', 'balance', 'balance_amount'] as $k) {
                $totals[$k] += end($rows)[$k];
            }
        }

        $this->rows = $rows;
        $this->totals = $totals;
        $this->dayHours = $dayHours;

        return view('ot-hours.index', $this->data);
    }

    /** One employee's overtime day by day for one salary period, and the bank movements of that period. */
    public function days(Request $request)
    {
        $request->validate(['user_id' => 'required|integer', 'month' => 'required|integer|min:1|max:12', 'year' => 'required|integer']);

        $this->period = PayPeriod::forMonth((int) $request->month, (int) $request->year);
        $this->employee = DB::table('users as u')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->where('u.id', $request->user_id)->first(['u.id', 'u.name', 'ed.employee_id']);
        abort_if(!$this->employee, 404);

        $this->otDays = DB::table('ot_daily')->where('user_id', $request->user_id)
            ->whereBetween('date', [$this->period->start->toDateString(), $this->period->end->toDateString()])
            ->orderBy('date')->get();
        $this->movements = DB::table('ot_ledger as l')->leftJoin('payslips as p', 'p.id', '=', 'l.payslip_id')
            ->where('l.user_id', $request->user_id)->whereDate('l.period_start', $this->period->start->toDateString())
            ->orderBy('l.id')->get(['l.*', 'p.payslip_number']);
        $this->pastRows = DB::table('ot_past')->where('user_id', $request->user_id)->where('year', $request->year)
            ->where('month', $request->month)->orderBy('date')->get();

        return Reply::dataOnly(['status' => 'success', 'html' => view('ot-hours.days', $this->data)->render()]);
    }

}
