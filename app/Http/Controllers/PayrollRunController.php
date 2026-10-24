<?php

namespace App\Http\Controllers;

use App\Exports\PayrollRunExport;
use App\Helper\Reply;
use App\Services\Payroll\AttendanceDays;
use App\Services\Payroll\PayComponents;
use App\Services\Payroll\PayPeriod;
use App\Services\Payroll\PayslipWriter;
use App\Services\Payroll\SalaryCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * What a month's payroll will cost, before any payslip exists.
 *
 * Every figure comes from the same two services the payslips use, so the
 * estimate on this screen is what the generated payslips will say.
 */
class PayrollRunController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Payroll';

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

        $this->month = $month;
        $this->year = $year;
        $cycle = PayPeriod::forMonth($month, $year);
        $this->cycle = $cycle;
        $this->periodLabel = $cycle->label;
        $this->monthName = $cycle->describe();
        // Still running: the period has not finished, so later days are not counted yet.
        $this->isCurrentMonth = $cycle->end->gte(now()->startOfDay());
        // Where the biometric chain stands for this cycle: payslips open the day
        // after it ends, and only once the device feed has been pulled that far.
        $pipeline = new \App\Services\Iapp\Pipeline\PipelineStatus();
        $this->deviceInUse = $pipeline->deviceInUse();
        $this->deviceThrough = $pipeline->deviceDataThrough();
        $this->autoPayslips = $pipeline->autoPayslips();
        $this->deviceBehind = $this->deviceInUse && !$this->isCurrentMonth
            && ($this->deviceThrough === null || $this->deviceThrough->lt($cycle->end->copy()->startOfDay()));
        $this->selectedEmployees = array_filter((array) $request->employees);
        $this->departmentId = $request->department;

        $this->employees = $this->employeeOptions();
        $this->departments = DB::table('teams')->orderBy('team_name')->pluck('team_name', 'id');

        $this->includeUnpaid = $request->boolean('include_unpaid');
        $this->rows = $this->rows($month, $year, $this->selectedEmployees, $this->departmentId, $this->includeUnpaid);
        $this->totals = $this->totals($this->rows);
        // Said out loud rather than left to be discovered: how many people the
        // screen is not showing because payroll has nothing to value them by.
        $this->leftOut = $this->leftOut();
        // And whether the income tax being deducted still matches what the
        // calculation says, which nothing else would reveal.
        $this->taxDrift = $this->taxDrift($this->rows);

        // Which design these payslips will be issued in, shown beside the
        // button that issues them.
        $templates = app(\App\Services\Payroll\PayslipTemplates::class);
        $this->payslipTemplate = $templates->selected();
        $this->payslipTemplateName = $templates->name($this->payslipTemplate);

        return view('payroll-run.index', $this->data);
    }

    /**
     * The same table as a spreadsheet, in the company's own column order.
     */
    public function export(Request $request)
    {
        [$month, $year] = $this->period($request);

        $rows = $this->rows($month, $year, array_filter((array) $request->employees), $request->department, $request->boolean('include_unpaid'));
        $name = 'payroll-' . Carbon::create($year, $month, 1)->format('Y-m') . '.xlsx';

        return Excel::download(new PayrollRunExport($rows, PayPeriod::forMonth($month, $year)->describe()), $name);
    }

    /**
     * Issue payslips for a batch of employees.
     *
     * The screen sends them a handful at a time so it can show progress and so
     * a long run cannot outlast the server's time limit.
     */
    public function generate(Request $request, PayslipWriter $writer)
    {
        $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $created = 0;
        $reasons = [];

        foreach ($request->user_ids as $userId) {
            $result = $writer->issue((int) $userId, (int) $request->month, (int) $request->year, user()->id);

            $result['ok'] ? $created++ : $reasons[] = $result['message'];
        }

        return array_merge(
            Reply::success(sprintf('%d payslip(s) issued.', $created)),
            ['created' => $created, 'skipped' => count($reasons), 'reasons' => $reasons]
        );
    }

    /**
     * Add overtime, a bonus, arrears or a recovery for one employee and period.
     */
    public function addComponent(Request $request, PayComponents $components)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'kind' => 'required|in:earning,deduction',
            'label' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0.01|max:9999999',
            'note' => 'nullable|string|max:255',
        ]);

        $cycle = PayPeriod::forMonth((int) $request->month, (int) $request->year);

        // A period whose payslip is already out cannot gain another line. The
        // line would not reach that payslip — attachment happens when a payslip
        // is issued — so it would show on this screen and not on the slip.
        $issued = DB::table('payslips')->where('user_id', $request->user_id)
            ->where('period', $cycle->label)->where('status', 'active')->whereNull('deleted_at')->first(['id', 'approval_status']);

        if ($issued) {
            return Reply::error($issued->approval_status === 'paid'
                ? 'That payslip has been paid, so it cannot change. Put the amount on the next one instead.'
                : 'A payslip for ' . $cycle->describe() . ' has already been issued. Revoke it first and '
                    . 'reissue once the amount is on, or put the amount on next month instead.');
        }

        $components->add((int) $request->user_id, $cycle->start, $request->kind,
            trim($request->label), (float) $request->amount, $request->note);

        return Reply::success('Added to ' . $cycle->describe() . '.');
    }

    /**
     * Put this period's calculated overtime on the run as editable lines
     * ("Overtime (calculated)"), for everyone shown whose payslip is not out.
     */
    public function calculateOvertime(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $rules = \App\Services\Attendance\WorkRules::forCompany(company()->id);

        if (!$rules || !$rules->s->ot_enabled) {
            return Reply::error('Overtime is switched off. Turn it on under Settings -> Attendance Settings -> Overtime.');
        }

        if (($rules->s->ot_mode ?? 'bank') === 'bank') {
            return Reply::error('Overtime is banked (Settings -> Attendance Settings -> Overtime): use the OT bank on each employee to cover LOP or encash it.');
        }

        // Every employee's period is counted again, like the payroll run itself.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        $cycle = PayPeriod::forMonth((int) $request->month, (int) $request->year);
        $calc = new \App\Services\Payroll\OvertimeCalculator();
        $tally = ['added' => 0, 'updated' => 0, 'removed' => 0, 'issued' => 0];
        $hours = 0.0;

        foreach ($this->rows((int) $request->month, (int) $request->year, array_filter((array) $request->employees), $request->department) as $row) {
            if ($row['status'] === 'generated' || $row['status'] === 'blocked') {
                continue;
            }

            $result = $calc->store($row['user_id'], $cycle, $row['ot']);
            $tally[$result] = ($tally[$result] ?? 0) + 1;
            $hours += $row['ot']['hours'];
        }

        return Reply::success(sprintf('Overtime for %s: %d added, %d updated, %d removed (%s hours in all).',
            $cycle->describe(), $tally['added'], $tally['updated'], $tally['removed'], rtrim(rtrim(number_format($hours, 2), '0'), '.') ?: '0'));
    }

    /**
     * OT bank decision for one employee and period: LOP days to cover and hours
     * to encash. The rest carries forward.
     */
    public function otBankDecide(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'compensate_days' => 'nullable|numeric|min:0|max:31',
            'encash_hours' => 'nullable|numeric|min:0|max:9999',
        ]);

        $row = collect($this->rows((int) $request->month, (int) $request->year, [(int) $request->user_id]))->first();

        if (!$row || !$row['ot_bank']) {
            return Reply::error('The OT bank does not apply to this employee (it is off, or their attendance is not counted by hours).');
        }

        try {
            (new \App\Services\Payroll\OtBank())->decide((int) $request->user_id, (int) company()->id,
                PayPeriod::forMonth((int) $request->month, (int) $request->year), $row['ot_bank'],
                (float) $request->compensate_days, (float) $request->encash_hours, user()->id);
        } catch (\RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        return Reply::success('OT bank updated for ' . $row['name'] . '.');
    }

    /** HR / Admin correction of somebody's OT bank, with a reason. */
    public function otBankAdjust(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'hours' => 'required|numeric|between:-9999,9999|not_in:0',
            'reason' => 'required|string|min:5|max:400',
        ], ['hours.not_in' => 'Give the hours to add (+) or take off (-).', 'reason.required' => 'Give a reason for the correction.']);

        try {
            (new \App\Services\Payroll\OtBank())->adjust((int) $request->user_id, (int) company()->id, (float) $request->hours,
                trim($request->reason), user()->id);
        } catch (\RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        return Reply::success('OT bank corrected.');
    }

    /**
     * Set the monthly income tax for an employee.
     *
     * The figure comes from the accountant; nothing here works it out.
     */
    public function setTds(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:0|max:9999999',
        ]);

        DB::table('users')->where('id', $request->user_id)
            ->update(['monthly_tds' => (float) $request->amount ?: null]);

        return Reply::success('Income tax set. It applies to payslips issued from now on.');
    }

    /**
     * Take a line back off, as long as its payslip has not been issued.
     */
    public function removeComponent(Request $request, PayComponents $components)
    {
        $request->validate(['id' => 'required|integer']);

        return $components->remove((int) $request->id)
            ? Reply::success('Removed.')
            : Reply::error('That line belongs to a payslip already issued; revoke the payslip first.');
    }

    /**
     * One employee's pay for the month, as a table row.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function rows(int $month, int $year, array $employeeIds = [], $departmentId = null, bool $includeUnpaid = false): array
    {
        $days = new AttendanceDays();
        $calculator = new SalaryCalculator();
        $components = new PayComponents();
        $cycle = PayPeriod::forMonth($month, $year);
        $periodLabel = $cycle->label;

        // Approved salary revisions whose month has come are on the record first.
        (new \App\Services\Payroll\SalaryRevisions())->applyDue();

        $employees = DB::table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('designations as d', 'd.id', '=', 'ed.designation_id')
            ->leftJoin('teams as t', 't.id', '=', 'ed.department_id')
            ->where('u.status', 'active')
            // People created from the biometric devices have no salary on record
            // yet; payroll is about who is being paid, so they are left out
            // unless they are asked for by name or "include everyone" is ticked.
            ->when(!$includeUnpaid && $employeeIds === [], fn ($q) => $q->where('u.employee_ctc', '>', 0))
            ->when($employeeIds !== [], fn ($q) => $q->whereIn('u.id', $employeeIds))
            ->when($departmentId, fn ($q) => $q->where('ed.department_id', $departmentId))
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.company_id', 'u.employee_ctc', 'u.basic_for_payroll', 'u.monthly_tds', 'ed.employee_card_id',
                'ed.employee_id', 'd.name as designation', 't.team_name as department']);
        $overtime = new \App\Services\Payroll\OvertimeCalculator();
        $bank = new \App\Services\Payroll\OtBank();

        // Which of them already hold a payslip for this month.
        $existing = DB::table('payslips')
            ->where('period', $periodLabel)->where('status', 'active')->whereNull('deleted_at')
            ->pluck('id', 'emp_id');

        $rows = [];
        $serial = 0;

        foreach ($employees as $employee) {
            // The pay in force for this month: a month before a revision is paid at the old rate.
            [$ctc, $basicPct] = \App\Services\Payroll\SalaryRevisions::payFor((int) $employee->id, $cycle, (float) $employee->employee_ctc,
                $employee->basic_for_payroll !== null ? (float) $employee->basic_for_payroll : null);
            $count = $days->forRange((int) $employee->id, $cycle->start, $cycle->end);
            $pay = $calculator->forEmployee($ctc, $basicPct, $count['working_days'], $count['days_present'], (float) ($employee->monthly_tds ?? 0));

            // Overtime as the settings work it out; with the OT bank, the LOP
            // days it covers this period are paid days (as on the payslip).
            $ot = $overtime->forEmployee((int) $employee->company_id, $cycle, $count, $pay);
            $otBank = null;

            if ($bank->enabled((int) $employee->company_id) && ($count['basis'] ?? '') === 'rules') {
                $otBank = $bank->summary((int) $employee->id, (int) $employee->company_id, $cycle, $count, $pay, $ot);

                if ($otBank['comp_days'] > 0) {
                    $count['days_present'] += min($otBank['comp_days'], $otBank['lop_days']);
                    $pay = $calculator->forEmployee($ctc, $basicPct, $count['working_days'], $count['days_present'], (float) ($employee->monthly_tds ?? 0));
                }
            }

            $blockers = [];

            if ($ctc <= 0) {
                $blockers[] = 'no CTC on record';
            }

            if (!$employee->employee_card_id) {
                $blockers[] = 'no employee code';
            }

            $slipId = $existing[$employee->employee_card_id] ?? null;

            // Overtime, bonus, arrears, an advance being recovered.
            $extra = $components->forPeriod((int) $employee->id, $cycle->start);
            $pay['extra_earnings'] = $extra['earning_total'];
            $pay['extra_deductions'] = $extra['deduction_total'];
            $pay['final_net'] = $pay['final_net'] + $extra['earning_total'] - $extra['deduction_total'];

            // "Calculate overtime" (pay every month) writes it as an editable line.
            $otLine = collect($extra['earnings'])->firstWhere('label', \App\Services\Payroll\OvertimeCalculator::LABEL);

            $rows[] = [
                'serial' => ++$serial,
                'basis' => $count['basis'] ?? 'standard',
                'ot' => $ot,
                'ot_on_run' => $otLine ? (float) $otLine->amount : null,
                'ot_bank' => $otBank,
                'extras' => array_merge($extra['earnings'], $extra['deductions']),
                'user_id' => (int) $employee->id,
                'name' => $employee->name,
                'employee_id' => $employee->employee_id,
                'card_id' => $employee->employee_card_id,
                'designation' => $employee->designation,
                'department' => $employee->department,
                'breakdown' => $count['breakdown'],
                'payslip_id' => $slipId,
                'blockers' => $blockers,
                'status' => $slipId ? 'generated' : ($blockers === [] ? 'pending' : 'blocked'),
            ] + $pay;
        }

        return $rows;
    }

    /** @return array<string, float|int> */
    protected function totals(array $rows): array
    {
        $sum = fn (string $key) => array_sum(array_column($rows, $key));
        $payable = array_filter($rows, fn ($r) => $r['blockers'] === []);

        return [
            'employees' => count($rows),
            'payable' => count($payable),
            'generated' => count(array_filter($rows, fn ($r) => $r['status'] === 'generated')),
            'blocked' => count(array_filter($rows, fn ($r) => $r['status'] === 'blocked')),
            'gross' => $sum('gross'),
            'pf_full' => $sum('pf_full'),
            'pf' => $sum('pf'),
            'esi' => $sum('esi'),
            'esi_full' => $sum('esi_full'),
            'pt' => $sum('pt'),
            'tds' => $sum('tds'),
            'deductions' => $sum('pf') + $sum('esi') + $sum('pt') + $sum('tds'),
            'net' => $sum('net'),
            'after_lop' => $sum('after_lop'),
            'extra_earnings' => $sum('extra_earnings'),
            'extra_deductions' => $sum('extra_deductions'),
            'final_net' => $sum('final_net'),
            'lop_days' => $sum('lop_days'),
        ];
    }

    /**
     * Active employees payroll cannot value, and why.
     *
     * Somebody with no CTC is left off the screen entirely, so without this
     * they are invisible until the moment a run is attempted. Somebody with a
     * salary but no employee code does appear, badged, and is counted here too
     * so one line can describe the whole gap.
     *
     * @return array<string, int>
     */
    protected function leftOut(): array
    {
        $active = DB::table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->where('u.status', 'active');

        $noCtc = (clone $active)
            ->where(fn ($q) => $q->whereNull('u.employee_ctc')->orWhere('u.employee_ctc', '<=', 0))
            ->count();

        $noCode = (clone $active)
            ->where('u.employee_ctc', '>', 0)
            ->where(fn ($q) => $q->whereNull('ed.employee_card_id')->orWhere('ed.employee_card_id', ''))
            ->count();

        return [
            'no_ctc' => $noCtc,
            'no_code' => $noCode,
            'total' => $noCtc + $noCode,
            'active' => (clone $active)->count(),
        ];
    }

    /**
     * Employees whose deducted income tax no longer matches the computed one.
     *
     * The figure payroll uses is written on to the employee; the Income Tax
     * screen is what recomputes it. Without this, the two could disagree for a
     * whole year with nothing saying so.
     *
     * @return array<string, mixed>
     */
    protected function taxDrift(array $rows): array
    {
        $calculator = new \App\Services\Payroll\TaxCalculator();
        $cycle = PayPeriod::forMonth($this->month, $this->year);
        $drifted = 0;

        foreach ($rows as $row) {
            if ($row['blockers'] !== []) {
                continue;
            }

            try {
                $computed = $calculator->forEmployee((int) $row['user_id'], $cycle->start);
            } catch (\Throwable $cannotCompute) {
                continue;
            }

            if ($computed['ok'] && abs($computed['monthly_tds'] - (float) $row['tds']) >= 1) {
                $drifted++;
            }
        }

        return ['drifted' => $drifted];
    }

    /** Month and year from the request, defaulting to this month. */
    protected function period(Request $request): array
    {
        $month = (int) ($request->month ?: now()->month);
        $year = (int) ($request->year ?: now()->year);

        return [max(1, min(12, $month)), $year];
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    protected function employeeOptions()
    {
        return DB::table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->where('u.status', 'active')
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'ed.employee_id']);
    }

}
