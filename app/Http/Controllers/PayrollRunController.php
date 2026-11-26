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
        $this->pageTitle = 'Run Payroll';

        $this->middleware(function ($request, $next) {
            // Whole-company salary figures. This was the administrator role
            // itself, which meant payroll could not be handed to anybody
            // without handing them everything else with it. manage_payroll is
            // the permission for the job; the administrator passes regardless.
            abort_403(!\App\Services\Access\PayrollAccess::can('prepare'));

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
        $this->branchId = $request->branch;

        $this->employees = $this->employeeOptions();
        $this->departments = DB::table('teams')->orderBy('team_name')->pluck('team_name', 'id');
        $this->branches = DB::table('branches')->where('status', 1)->orderBy('branch_name')->pluck('branch_name', 'id');

        $this->includeUnpaid = $request->boolean('include_unpaid');
        $this->rows = $this->rows($month, $year, $this->selectedEmployees, $this->departmentId, $this->includeUnpaid, $this->branchId);
        $this->totals = $this->totals($this->rows);
        // The calculation switches (company-wide, changed from the top of this screen) and the counts
        // under the tiles (OT / earnings / deductions / LOP / TDS / own settings), each a list on click.
        $this->switches = $this->companySwitches();
        $this->canSwitch = $this->canChangeSettings();
        $this->counts = $this->counts($this->rows);
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

        $rows = $this->rows($month, $year, array_filter((array) $request->employees), $request->department, $request->boolean('include_unpaid'), $request->branch);
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
        // Held only because the device feed is behind: HR can issue them anyway
        $overridable = [];
        $anyway = $request->boolean('anyway');

        foreach ($request->user_ids as $userId) {
            $result = $writer->issue((int) $userId, (int) $request->month, (int) $request->year, user()->id, $anyway);

            if ($result['ok']) {
                $created++;
            } else {
                $reasons[] = $result['message'];

                if (!empty($result['overridable'])) {
                    $overridable[] = (int) $userId;
                }
            }
        }

        return array_merge(
            Reply::success(sprintf('%d payslip(s) issued.', $created)),
            ['created' => $created, 'skipped' => count($reasons), 'reasons' => $reasons, 'overridable' => $overridable]
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
            return Reply::error('Overtime is banked (Settings -> Attendance Settings -> Overtime): use the Overtime Balance on each employee to cover LOP or encash it.');
        }

        // Every employee's period is counted again, like the payroll run itself.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        $cycle = PayPeriod::forMonth((int) $request->month, (int) $request->year);
        $calc = new \App\Services\Payroll\OvertimeCalculator();
        $tally = ['added' => 0, 'updated' => 0, 'removed' => 0, 'issued' => 0];
        $hours = 0.0;

        foreach ($this->rows((int) $request->month, (int) $request->year, array_filter((array) $request->employees), $request->department, false, $request->branch) as $row) {
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
            return Reply::error('The overtime balance does not apply to this employee (it is off, or their attendance is not counted by hours).');
        }

        try {
            (new \App\Services\Payroll\OtBank())->decide((int) $request->user_id, (int) company()->id,
                PayPeriod::forMonth((int) $request->month, (int) $request->year), $row['ot_bank'],
                (float) $request->compensate_days, (float) $request->encash_hours, user()->id);
        } catch (\RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        return Reply::success('Overtime balance updated for ' . $row['name'] . '.');
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

        return Reply::success('Overtime balance corrected.');
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
    protected function rows(int $month, int $year, array $employeeIds = [], $departmentId = null, bool $includeUnpaid = false, $branchId = null): array
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
            ->leftJoin('branches as br', 'br.id', '=', 'ed.branch_id')
            ->where('u.status', 'active')
            ->whereNotIn('u.id', \App\Services\Access\SystemAccounts::hidden())   // not the Super Admin login
            // People created from the biometric devices have no salary on record
            // yet; payroll is about who is being paid, so they are left out
            // unless they are asked for by name or "include everyone" is ticked.
            ->when(!$includeUnpaid && $employeeIds === [], fn ($q) => $q->where('u.employee_ctc', '>', 0))
            ->when($employeeIds !== [], fn ($q) => $q->whereIn('u.id', $employeeIds))
            ->when($departmentId, fn ($q) => $q->where('ed.department_id', $departmentId))
            ->when($branchId, fn ($q) => $q->where('ed.branch_id', $branchId))
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.company_id', 'u.employee_ctc', 'u.basic_for_payroll', 'u.monthly_tds', 'ed.employee_card_id',
                'ed.employee_id', 'd.name as designation', 't.team_name as department', 'br.branch_name as branch']);
        $overtime = new \App\Services\Payroll\OvertimeCalculator();
        $bank = new \App\Services\Payroll\OtBank();

        // Every employee's shifts, leave and punches for the month in a few
        // queries, rather than a handful per employee.
        $days->preload($employees->pluck('id')->all(), $cycle->start, $cycle->end);

        // ...and the OT bank, the extra lines and the tax figures, the same way
        // (they asked 18 queries per employee: ~6,000 for the whole company)
        $ids = $employees->pluck('id')->all();
        \App\Services\Payroll\OtBank::preload($ids, $cycle);
        PayComponents::preload($ids, $cycle->start);
        \App\Services\Payroll\TaxCalculator::preload($ids, $cycle->start);

        // Which of them already hold a payslip for this month.
        $existing = DB::table('payslips')
            ->where('period', $periodLabel)->where('status', 'active')->whereNull('deleted_at')
            ->get(['id', 'emp_id', 'approval_status'])->keyBy('emp_id');

        $rows = [];
        $serial = 0;

        foreach ($employees as $employee) {
            // The pay in force for this month: a month before a revision is paid at the old rate.
            [$ctc, $basicPct] = \App\Services\Payroll\SalaryRevisions::payFor((int) $employee->id, $cycle, (float) $employee->employee_ctc,
                $employee->basic_for_payroll !== null ? (float) $employee->basic_for_payroll : null);
            $count = $days->forRange((int) $employee->id, $cycle->start, $cycle->end);
            // The employee's own settings for this period (Payroll Run), over the company's.
            $own = \App\Services\Payroll\PayrollSwitches::own((int) $employee->id, $cycle->start->toDateString());
            $prorate = $own['prorate_statutory'] ?? null;
            $pay = $calculator->forEmployee($ctc, $basicPct, $count['working_days'], $count['days_present'], (float) ($employee->monthly_tds ?? 0), $prorate, \App\Services\Payroll\Statutory::flags((int) $employee->id));

            // Overtime as the settings work it out; with the OT bank, the LOP
            // days it covers this period are paid days (as on the payslip).
            $ot = $overtime->forEmployee((int) $employee->company_id, $cycle, $count, $pay);
            $otBank = null;

            if ($bank->enabled((int) $employee->company_id) && ($count['basis'] ?? '') === 'rules') {
                $otBank = $bank->summary((int) $employee->id, (int) $employee->company_id, $cycle, $count, $pay, $ot);

                if ($otBank['comp_days'] > 0) {
                    $count['days_present'] += min($otBank['comp_days'], $otBank['lop_days']);
                    $pay = $calculator->forEmployee($ctc, $basicPct, $count['working_days'], $count['days_present'], (float) ($employee->monthly_tds ?? 0), $prorate, \App\Services\Payroll\Statutory::flags((int) $employee->id));
                }
            }

            $blockers = [];

            if ($ctc <= 0) {
                $blockers[] = 'no CTC on record';
            }

            if (!$employee->employee_card_id) {
                $blockers[] = 'no employee code';
            }

            $slipId = $existing[$employee->employee_card_id]->id ?? null;
            $slipStatus = $existing[$employee->employee_card_id]->approval_status ?? null;

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
                'branch' => $employee->branch,
                'own' => $own,
                'statutory' => \App\Services\Payroll\Statutory::label((int) $employee->id),
                'paid_by' => \App\Services\Payroll\Statutory::paidBy((int) $employee->id),
                'breakdown' => $count['breakdown'],
                'payslip_id' => $slipId,
                'payslip_status' => $slipStatus,
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
            ->where('u.status', 'active')
            ->whereNotIn('u.id', \App\Services\Access\SystemAccounts::hidden());   // not the Super Admin login

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
    /** LOP days HR decides for the period: the sheet to fill in (every active employee, with what is set). */
    public function lopSheetExport(Request $request)
    {
        [$month, $year] = $this->period($request);
        $cycle = PayPeriod::forMonth($month, $year);
        $book = (new \App\Services\Payroll\LopDays())->sheet($cycle);

        return response()->streamDownload(fn () => (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output'),
            'LOP days - ' . $cycle->label . '.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** Upload the filled LOP sheet: the period is then paid as its days less these (AttendanceDays). */
    public function lopUpload(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        [$month, $year] = $this->period($request);
        $cycle = PayPeriod::forMonth($month, $year);

        try {
            $r = (new \App\Services\Payroll\LopDays())->upload($request->file('file')->getRealPath(), $cycle, user()->id);
        } catch (\Throwable $e) {
            return Reply::error($e->getMessage());
        }

        $message = sprintf('%s: LOP days set for %d employee(s)%s.', $cycle->describe(), $r['saved'],
            $r['cleared'] ? ", {$r['cleared']} back to attendance" : '');
        $message .= $r['unknown'] ? ' Not found: ' . implode(', ', array_slice($r['unknown'], 0, 10)) . (count($r['unknown']) > 10 ? '...' : '') . '.' : '';
        $message .= $r['invalid'] ? ' Not a number of days: ' . implode(', ', array_slice($r['invalid'], 0, 10)) . '.' : '';

        return Reply::success($message);
    }

    /**
     * Top of the screen: a calculation switch for the whole company (Payroll Settings ->
     * Deduct late comings / Reduce PF / ESI for unpaid days). The screen reloads with it.
     */
    public function saveSwitch(Request $request)
    {
        abort_403(!$this->canChangeSettings());
        $request->validate(['switch' => 'required|in:' . implode(',', \App\Services\Payroll\PayrollSwitches::KEYS)]);
        $on = $request->boolean('on');

        if ($request->switch === 'deduct_late_comings') {
            \App\Services\Payroll\AttendanceGate::setOption(company()?->id, 'deduct_late_comings', $on);
        } else {
            $id = DB::table('payroll_calculations')->orderByDesc('id')->value('id');
            $id && DB::table('payroll_calculations')->where('id', $id)->update(['prorate_statutory' => $on]);
        }

        return Reply::success(sprintf('%s %s for everyone (employees with their own setting keep it).',
            self::SWITCH_LABELS[$request->switch], $on ? 'on' : 'off'));
    }

    /**
     * One row: the employee's own setting for this period (on / off / back to the company's),
     * then the row worked out again and sent back to replace the one on screen.
     */
    public function rowSwitch(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'switch' => 'required|in:' . implode(',', \App\Services\Payroll\PayrollSwitches::KEYS),
            'value' => 'required|in:on,off,company',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);
        $cycle = PayPeriod::forMonth((int) $request->month, (int) $request->year);
        $userId = (int) $request->user_id;

        $card = DB::table('employee_details')->where('user_id', $userId)->value('employee_card_id');
        $slip = $card ? DB::table('payslips')->where('emp_id', (string) $card)->where('period', $cycle->label)
            ->where('status', 'active')->whereNull('deleted_at')->first(['id', 'approval_status']) : null;

        // Approved or paid: it stands; a draft is made again below with the new setting.
        if ($slip && $slip->approval_status !== 'draft') {
            return Reply::error("This employee's payslip for " . $cycle->describe() . ' is ' . $slip->approval_status
                . '. Revoke it (Issued Payslips) to change how it is worked out.');
        }
        if ($slip && !\App\Services\Access\PayrollAccess::can('revoke')) {
            return Reply::error('Changing this re-makes the draft payslip, which needs the permission to revoke payslips.');
        }

        $value = ['on' => true, 'off' => false, 'company' => null][$request->value];

        try {
            DB::transaction(function () use ($userId, $cycle, $request, $value, $slip) {
                \App\Services\Payroll\PayrollSwitches::setOwn($userId, $cycle, $request->switch, $value, user()->id, company()?->id);

                if ($slip) {
                    $this->remakeDraft((int) $slip->id, $userId, $cycle, self::SWITCH_LABELS[$request->switch] . ' changed on Payroll Run');
                }
            });
        } catch (\RuntimeException $e) {
            \App\Services\Payroll\PayrollSwitches::forget();

            return Reply::error($e->getMessage());
        }

        $row = collect($this->rows($cycle->month, $cycle->year, [$userId]))->first();

        if (!$row) {
            return Reply::error('Employee not found.');
        }

        $row['serial'] = (int) $request->serial ?: $row['serial'];

        return Reply::dataOnly([
            'status' => 'success',
            'html' => $this->rowHtml($row, $cycle->month, $cycle->year),
            'figures' => self::figures($row),
        ]);
    }

    /**
     * A draft payslip made again: revoked as Issued Payslips -> Revoke does it (its pay lines, leave encashment and OT
     * bank released), then issued afresh with the settings now in force. Throws (and the caller rolls back) when the
     * new one cannot be issued, so the employee is never left without the draft they had.
     */
    protected function remakeDraft(int $payslipId, int $userId, PayPeriod $cycle, string $reason): void
    {
        DB::table('payslips')->where('id', $payslipId)->update([
            'status' => 'revoked', 'revoke_reason' => $reason, 'revoked_at' => now(), 'revoked_by' => user()->id,
        ]);
        if (\Illuminate\Support\Facades\Schema::hasTable('leave_encashments')) {
            DB::table('leave_encashments')->where('payslip_id', $payslipId)->where('status', 'paid')
                ->update(['status' => 'pending', 'payslip_id' => null, 'paid_at' => null, 'updated_at' => now()]);
        }
        (new PayComponents())->releaseFromPayslip($payslipId);
        (new \App\Services\Payroll\OtBank())->onRevoke($payslipId);

        $result = app(PayslipWriter::class)->issue($userId, $cycle->month, $cycle->year, user()->id, true);

        if (!$result['ok']) {
            throw new \RuntimeException('The draft could not be made again: ' . $result['message']);
        }
    }

    public const SWITCH_LABELS = [
        'deduct_late_comings' => 'Deduct late comings',
        'prorate_statutory' => 'Reduce PF / ESI for unpaid days',
    ];

    /** @return array<string, bool> */
    protected function companySwitches(): array
    {
        return collect(\App\Services\Payroll\PayrollSwitches::KEYS)
            ->mapWithKeys(fn ($k) => [$k => \App\Services\Payroll\PayrollSwitches::company($k, company()?->id)])->all();
    }

    protected function canChangeSettings(): bool
    {
        return user()->permission('manage_company_setting') === 'all' || \App\Services\Access\PayrollAccess::can('settings');
    }

    /** The figures the footer and the tiles add up, for a row swapped in place. */
    public static function figures(array $row): array
    {
        return collect(['gross', 'pf_full', 'pf', 'esi', 'esi_full', 'pt', 'tds', 'net', 'lop_days', 'after_lop',
            'extra_earnings', 'extra_deductions', 'final_net'])->mapWithKeys(fn ($k) => [$k => round((float) ($row[$k] ?? 0), 2)])->all();
    }

    protected function rowHtml(array $row, int $month, int $year): string
    {
        $cycle = PayPeriod::forMonth($month, $year);

        return preg_replace('/\s+/', ' ', view('payroll-run.row', [
            'row' => $row, 'month' => $month, 'year' => $year, 'cycle' => $cycle,
            'isCurrentMonth' => $cycle->end->gte(now()->startOfDay()), 'switches' => $this->companySwitches(),
        ])->render());
    }

    /**
     * Who has what this period, for the counts above the table: each a list of employees on click.
     *
     * @return array<string, array{label: string, hint: string, people: array<int, array>}>
     */
    protected function counts(array $rows): array
    {
        $money = fn ($v) => '₹' . number_format((float) $v, 2);
        $days = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . ' day(s)';
        $isOt = fn ($e) => $e->kind === 'earning' && preg_match('/\bOT\b|overtime/i', (string) $e->label);
        $labels = fn (array $r, string $kind) => implode(', ', array_map(fn ($e) => $e->label, array_filter($r['extras'], fn ($e) => $e->kind === $kind)));
        $person = fn ($r, $value) => ['user_id' => $r['user_id'], 'name' => $r['name'], 'code' => $r['card_id'] ?: $r['employee_id'],
            'branch' => $r['branch'], 'value' => $value];

        $out = [
            'ot' => ['label' => 'OT adjusted', 'hint' => 'Overtime paid as a line, or OT bank hours covering LOP / encashed', 'people' => []],
            'earnings' => ['label' => 'Earnings', 'hint' => 'Extra earnings on the run (pay elements: OT, add-ons, bonus, arrears)', 'people' => []],
            'deductions' => ['label' => 'Deductions', 'hint' => 'Extra deductions on the run (advances, recoveries)', 'people' => []],
            'lop' => ['label' => 'LOP adjusted', 'hint' => 'LOP days set by HR (Upload LOP days) instead of attendance, more than 0', 'people' => []],
            'tds' => ['label' => 'TDS deducted', 'hint' => 'Income tax deducted this month', 'people' => []],
            'exempt' => ['label' => 'PF/ESI/PT exempt', 'hint' => 'Employees with PF, ESI or PT not applicable (employee record), and who are paid in cash', 'people' => []],
            'own' => ['label' => 'Own settings', 'hint' => 'Employees with their own late / PF-ESI setting this period', 'people' => []],
        ];

        foreach ($rows as $r) {
            $otAmount = array_sum(array_map(fn ($e) => (float) $e->amount, array_filter($r['extras'], $isOt)));
            $bank = $r['ot_bank'] ?? null;
            $banked = $bank && ($bank['comp_days'] > 0 || $bank['enc_hours'] > 0);

            if ($otAmount > 0 || $banked) {
                $out['ot']['people'][] = $person($r, implode(' · ', array_filter([
                    $otAmount > 0 ? $money($otAmount) : null,
                    $bank && $bank['comp_days'] > 0 ? 'covers ' . $days($bank['comp_days']) . ' LOP' : null,
                    $bank && $bank['enc_hours'] > 0 ? 'encash ' . $bank['enc_hours'] . 'h' : null,
                ])));
            }
            if (($r['extra_earnings'] ?? 0) > 0) {
                $out['earnings']['people'][] = $person($r, '+' . $money($r['extra_earnings']) . ' · ' . $labels($r, 'earning'));
            }
            if (($r['extra_deductions'] ?? 0) > 0) {
                $out['deductions']['people'][] = $person($r, '−' . $money($r['extra_deductions']) . ' · ' . $labels($r, 'deduction'));
            }
            if (($r['breakdown']['hr_lop_days'] ?? 0) > 0) {
                $out['lop']['people'][] = $person($r, $days($r['breakdown']['hr_lop_days']) . ' LOP · ' . $money($r['after_lop']));
            }
            if (($r['tds'] ?? 0) > 0) {
                $out['tds']['people'][] = $person($r, $money($r['tds']));
            }
            if (($r['statutory'] ?? '') !== '' || ($r['paid_by'] ?? 'bank') === 'cash') {
                $out['exempt']['people'][] = $person($r, trim(($r['statutory'] ?? '') . (($r['paid_by'] ?? '') === 'cash' ? ' · paid in cash' : ''), ' ·'));
            }
            if ($r['own'] !== []) {
                $out['own']['people'][] = $person($r, implode(' · ', array_map(
                    fn ($k, $v) => self::SWITCH_LABELS[$k] . ': ' . ($v ? 'on' : 'off'), array_keys($r['own']), $r['own'])));
            }
        }

        return $out;
    }

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
            ->whereNotIn('u.id', \App\Services\Access\SystemAccounts::hidden())   // not the Super Admin login
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'ed.employee_id', 'ed.department_id']);
    }

}
