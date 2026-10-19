<?php

namespace App\Http\Controllers;

use App\Exports\PayrollRegisterExport;
use App\Services\Payroll\PayoutQuery;
use App\Services\Payroll\PayPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * What was actually paid in a month, taken from the payslips themselves.
 *
 * The payroll screen is an estimate of what a month will cost and is computed
 * from salaries and attendance. This is the record of what went out: every
 * issued payslip, its statutory deductions, and the totals an accountant is
 * asked for — the PF and ESI due from both sides, professional tax, and income
 * tax withheld.
 *
 * Nothing here recalculates anything. If a figure looks wrong, the payslip is
 * wrong and should be revoked and reissued, so the register and the payslips
 * can never disagree.
 */
class PayrollRegisterController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Payroll Register';

        $this->middleware(function ($request, $next) {
            // Whole-company salary figures. This was the administrator role
            // itself, which meant payroll could not be handed to anybody
            // without handing them everything else with it. manage_payroll is
            // the permission for the job; the administrator passes regardless.
            abort_403(user()->permission('manage_payroll') !== 'all' && !in_array('admin', user_roles()));

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        [$month, $year] = $this->period($request);
        $cycle = PayPeriod::forMonth($month, $year);

        $this->month = $month;
        $this->year = $year;
        $this->cycle = $cycle;
        $this->monthName = $cycle->describe();
        $this->periodLabel = $cycle->label;
        $this->includeUnapproved = $request->boolean('include_unapproved');

        $this->rows = $this->rows($cycle, $this->includeUnapproved);
        $this->totals = $this->totals($this->rows);
        $this->departments = $this->byDepartment($this->rows);

        return view('payroll-register.index', $this->data);
    }

    /**
     * The register as a spreadsheet: one sheet per thing an accountant files.
     */
    public function export(Request $request)
    {
        [$month, $year] = $this->period($request);
        $cycle = PayPeriod::forMonth($month, $year);
        $rows = $this->rows($cycle, $request->boolean('include_unapproved'));

        return Excel::download(
            new PayrollRegisterExport($rows, $this->totals($rows), $cycle),
            'payroll-register-' . Carbon::create($year, $month, 1)->format('Y-m') . '.xlsx'
        );
    }

    /**
     * Every payslip issued for the period.
     *
     * Drafts are left out by default: nothing has been paid against them yet,
     * so they are not part of the record.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function rows(PayPeriod $cycle, bool $includeUnapproved = false): array
    {
        // One place builds these, so the register and the dashboard agree.
        return app(PayoutQuery::class)->forCycle($cycle, $includeUnapproved);
    }

    /** @deprecated kept only so the old query is on record. */
    protected function rowsTheOldWay(PayPeriod $cycle, bool $includeUnapproved = false): array
    {
        $payslips = DB::table('payslips as p')
            ->leftJoin('employee_details as ed', 'ed.user_id', '=', 'p.user_id')
            ->leftJoin('teams as t', 't.id', '=', 'ed.department_id')
            ->where('p.period', $cycle->label)
            ->where('p.status', 'active')
            ->whereNull('p.deleted_at')
            ->when(!$includeUnapproved, fn ($q) => $q->whereIn('p.approval_status', ['approved', 'paid']))
            ->orderBy('p.emp_name')
            ->get([
                'p.id', 'p.payslip_number', 'p.emp_name', 'p.emp_id', 'p.emp_pan', 'p.user_id',
                'p.gross', 'p.basic', 'p.hra', 'p.ca', 'p.lta', 'p.cea', 'p.others',
                'p.pf', 'p.employer_pf', 'p.esi', 'p.employer_esi', 'p.pt', 'p.tds',
                'p.total_deductions', 'p.net_salary', 'p.attendance_net', 'p.leave_encashment',
                'p.working_days', 'p.days_present', 'p.lop_days',
                'p.approval_status', 'p.paid_at', 'p.payment_reference',
                'ed.pf_account_number', 'ed.esi_number', 'ed.personal_account_number', 'ed.ifsc_code',
                't.team_name as department',
            ]);

        // Extra pay elements are their own lines on the slip, so they are shown
        // as their own columns here rather than folded into the salary.
        $extras = DB::table('payslip_components')
            ->whereIn('payslip_id', $payslips->pluck('id'))
            ->get(['payslip_id', 'kind', 'label', 'amount']);

        $rows = [];
        $serial = 0;

        foreach ($payslips as $payslip) {
            $lines = $extras->where('payslip_id', $payslip->id);
            $earnings = (float) $lines->where('kind', 'earning')->sum('amount');
            $deductions = (float) $lines->where('kind', 'deduction')->sum('amount');

            $rows[] = array_merge((array) $payslip, [
                'serial' => ++$serial,
                'extra_earnings' => $earnings,
                'extra_deductions' => $deductions,
                'extras' => $lines->values()->all(),
                // What the employee is actually paid: the attendance-adjusted
                // figure, plus anything encashed and any extra line.
                'paid' => (float) $payslip->attendance_net + (float) $payslip->leave_encashment
                    + $earnings - $deductions,
                // What the company parts with, its own contributions included.
                'cost' => (float) $payslip->attendance_net + (float) $payslip->leave_encashment
                    + $earnings - $deductions
                    + (float) $payslip->employer_pf + (float) $payslip->employer_esi,
            ]);
        }

        return $rows;
    }

    /** @return array<string, float|int> */
    protected function totals(array $rows): array
    {
        return app(PayoutQuery::class)->totals($rows);
    }

    /** @deprecated kept only so the old sums are on record. */
    protected function totalsTheOldWay(array $rows): array
    {
        $sum = fn (string $key) => array_sum(array_map(fn ($r) => (float) ($r[$key] ?? 0), $rows));

        $pfEmployee = $sum('pf');
        $pfEmployer = $sum('employer_pf');
        $esiEmployee = $sum('esi');
        $esiEmployer = $sum('employer_esi');

        return [
            'payslips' => count($rows),
            'paid_count' => count(array_filter($rows, fn ($r) => $r['approval_status'] === 'paid')),
            'gross' => $sum('gross'),
            'basic' => $sum('basic'),
            'pf_employee' => $pfEmployee,
            'pf_employer' => $pfEmployer,
            // What goes to the PF authority for the month: both sides together.
            'pf_payable' => $pfEmployee + $pfEmployer,
            'esi_employee' => $esiEmployee,
            'esi_employer' => $esiEmployer,
            'esi_payable' => $esiEmployee + $esiEmployer,
            'pt' => $sum('pt'),
            'tds' => $sum('tds'),
            'extra_earnings' => $sum('extra_earnings'),
            'extra_deductions' => $sum('extra_deductions'),
            'encashment' => $sum('leave_encashment'),
            'lop_days' => $sum('lop_days'),
            'paid' => $sum('paid'),
            'cost' => $sum('cost'),
        ];
    }

    /**
     * The same figures broken down by department, for whoever holds the budget.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function byDepartment(array $rows): array
    {
        return app(PayoutQuery::class)->byDepartment($rows);
    }

    /** @deprecated kept only so the old grouping is on record. */
    protected function byDepartmentTheOldWay(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $name = $row['department'] ?: 'No department';
            $groups[$name] ??= ['department' => $name, 'people' => 0, 'gross' => 0.0, 'paid' => 0.0, 'cost' => 0.0];
            $groups[$name]['people']++;
            $groups[$name]['gross'] += (float) $row['gross'];
            $groups[$name]['paid'] += (float) $row['paid'];
            $groups[$name]['cost'] += (float) $row['cost'];
        }

        usort($groups, fn ($a, $b) => $b['cost'] <=> $a['cost']);

        return $groups;
    }

    /** Month and year from the request, defaulting to last month. */
    protected function period(Request $request): array
    {
        // A register is read after a month is done, so that is where it opens.
        $default = now()->subMonthNoOverflow();
        $month = (int) ($request->month ?: $default->month);
        $year = (int) ($request->year ?: $default->year);

        return [max(1, min(12, $month)), $year];
    }

}
