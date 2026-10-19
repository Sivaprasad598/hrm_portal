<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Mail\PayslipIssued;
use App\Services\Payroll\PayslipDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Read-only list of the payslips that have been generated.
 *
 * The payslip pages themselves are legacy PHP rendered in an iframe, and every
 * query behind them ends in `LIMIT 1` — they show one slip at a time and there
 * has never been a way to see who has been paid for a given month. This is that
 * view, and nothing more: it never creates, edits or deletes a payslip.
 *
 * Two quirks of the underlying table shape everything here:
 *
 *  - `period` is a string like "July - 2026", not a date, so months cannot be
 *    compared or sorted in SQL. They are parsed once into a real year/month for
 *    ordering and filtering.
 *  - Generating a payslip appends a row rather than replacing one. One employee
 *    has 23 rows for a single month representing two actual outcomes, so the
 *    list shows the newest per employee per period unless asked otherwise.
 */
class PayslipListController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.generatedPayslips';

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
        $this->activeTab = 'generated';

        $rows = $this->rows();

        $this->periods = $this->periodOptions($rows);
        $this->employees = $rows->pluck('emp_name', 'emp_id')->filter()->unique()->sort()->all();
        $this->departments = DB::table('teams')
            ->when(company(), fn ($q) => $q->where('company_id', company()->id))
            ->orderBy('team_name')
            ->pluck('team_name', 'id')
            ->all();

        $this->filters = [
            'period' => $request->period,
            'year' => $request->year,
            'employee' => $request->employee,
            'department' => $request->department,
            'all_versions' => $request->boolean('all_versions'),
        ];

        $filtered = $this->applyFilters($rows, $this->filters);

        // Newest first within a period, newest period first.
        $filtered = $filtered->sortByDesc(fn ($row) => [$row->sort_key, $row->id])->values();

        $this->payslips = $filtered;
        $this->summary = $this->summarise($filtered);

        return view('payslips.generated', $this->data);
    }

    /**
     * Headline figures, with every component that makes them up.
     *
     * A single "deductions" number hides which statutory head the money went
     * to, and those behave differently: PF is a percentage of basic, PT is a
     * flat monthly amount, and ESI applies only below a gross threshold — so
     * zero ESI is a fact worth stating rather than a gap in the data.
     *
     * Each part is summed from the payslips themselves rather than recomputed,
     * so the breakdown always reconciles with the totals beside it.
     */
    protected function summarise($rows): array
    {
        $sum = fn (string $column) => $rows->sum(fn ($r) => (float) $r->{$column});

        $gross = $sum('gross');
        $deductions = $sum('total_deductions');

        $earnings = [
            'Basic' => $sum('basic'),
            'HRA' => $sum('hra'),
            'CA' => $sum('ca'),
            'LTA' => $sum('lta'),
            'CEA' => $sum('cea'),
            'Special Allowance' => $sum('others'),
        ];

        $heads = [
            'PF' => ['value' => $sum('pf'), 'note' => 'Provident Fund, % of basic'],
            'ESI' => ['value' => $sum('esi'), 'note' => 'Employee State Insurance, below the gross threshold only'],
            'PT' => ['value' => $sum('pt'), 'note' => 'Professional Tax, flat monthly'],
        ];

        // Largest first, so the head that actually moves the number leads.
        uasort($heads, fn ($a, $b) => $b['value'] <=> $a['value']);

        $employees = $rows->pluck('emp_id')->unique()->count();

        return [
            'count' => $rows->count(),
            'employees' => $employees,
            'periods' => $rows->pluck('period')->unique()->count(),
            'gross' => $gross,
            'deductions' => $deductions,
            // The same net the printed payslips show.
            'net' => $sum('take_home'),
            'lop' => $sum('lop_amount'),
            'employer' => $rows->sum(fn ($r) => (float) ($r->employer_pf ?? $r->pf) + (float) ($r->employer_esi ?? $r->esi)),
            // Paid with the salary but not part of it, so kept out of net.
            'encashment' => $sum('leave_encashment'),
            'encashed_days' => $sum('encashed_days'),
            'total_payable' => $sum('take_home') + $sum('leave_encashment'),
            'earnings' => $earnings,
            'heads' => $heads,
            // Shown so the card can say what proportion of pay is withheld.
            'deduction_pct' => $gross > 0 ? round($deductions / $gross * 100, 1) : 0.0,
            'avg_net' => $employees > 0 ? $sum('take_home') / $employees : 0.0,
            'flagged' => $rows->filter(fn ($r) => $r->warnings !== [])->count(),
            // If these disagree the stored figures are inconsistent, which is
            // worth saying out loud rather than quietly rendering both.
            'reconciles' => abs(($gross - $deductions) - $sum('net_salary')) < 0.01,
        ];
    }

    /**
     * Withdraw a payslip, with the reason it was withdrawn.
     *
     * The row stays: it is pay history, and a slip that reached someone needs to
     * remain visible together with why it was taken back. Revoking releases that
     * employee's month, so a corrected payslip can be generated.
     */
    /**
     * Approve payslips, so they count as issued.
     *
     * Taken as a batch, so a month's payroll can be approved in one go with the
     * screen reporting as it goes.
     */
    public function approve(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);

        $approved = 0;
        $reasons = [];

        foreach (DB::table('payslips')->whereIn('id', $request->ids)->whereNull('deleted_at')->get() as $slip) {
            if ($slip->status !== 'active') {
                $reasons[] = ($slip->emp_name ?: 'A payslip') . ' — ' . $slip->status . ', not approved';
                continue;
            }

            if ($slip->approval_status !== 'draft') {
                $reasons[] = ($slip->emp_name ?: 'A payslip') . ' — already ' . $slip->approval_status;
                continue;
            }

            DB::table('payslips')->where('id', $slip->id)->update([
                'approval_status' => 'approved', 'approved_by' => user()->id, 'approved_at' => now(),
            ]);

            $approved++;
        }

        return array_merge(
            Reply::success($approved . ' payslip(s) approved.'),
            ['created' => $approved, 'skipped' => count($reasons), 'reasons' => $reasons]
        );
    }

    /**
     * Record that approved payslips have been paid.
     *
     * This is the point of no return: a paid payslip cannot be revoked or
     * deleted, because the money has left the bank.
     */
    public function markPaid(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'reference' => 'nullable|string|max:100',
        ]);

        $paid = 0;
        $reasons = [];

        foreach (DB::table('payslips')->whereIn('id', $request->ids)->whereNull('deleted_at')->get() as $slip) {
            if ($slip->approval_status !== 'approved') {
                $reasons[] = ($slip->emp_name ?: 'A payslip') . ' — '
                    . ($slip->approval_status === 'paid' ? 'already paid' : 'not approved yet');
                continue;
            }

            DB::table('payslips')->where('id', $slip->id)->update([
                'approval_status' => 'paid', 'paid_by' => user()->id, 'paid_at' => now(),
                'payment_reference' => $request->reference,
            ]);

            $paid++;
        }

        return array_merge(
            Reply::success($paid . ' payslip(s) marked paid.'),
            ['created' => $paid, 'skipped' => count($reasons), 'reasons' => $reasons]
        );
    }

    /**
     * The payslip as a PDF — the same document the screen shows.
     */
    public function pdf($id, PayslipDocument $document)
    {
        $slip = DB::table('payslips')->whereNull('deleted_at')->find($id);

        abort_unless($slip, 404);

        return $document->pdf((int) $id)->download($document->filename((int) $id));
    }

    /**
     * Send payslips to the employees they belong to.
     *
     * Takes a batch so the screen can show progress, and records what was sent
     * where, so sending again does not quietly duplicate.
     */
    public function email(Request $request, PayslipDocument $document)
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);

        $sent = 0;
        $reasons = [];
        $company = company()?->company_name ?: config('app.name');

        $slips = DB::table('payslips as p')
            ->leftJoin('users as u', 'u.id', '=', 'p.user_id')
            ->whereIn('p.id', $request->ids)
            ->whereNull('p.deleted_at')
            ->get(['p.*', 'u.email as work_email', 'u.name as employee_name']);

        foreach ($slips as $slip) {
            $address = $slip->work_email;

            if ($slip->status !== 'active') {
                $reasons[] = ($slip->emp_name ?: 'A payslip') . ' — ' . $slip->status . ', not sent';
                continue;
            }

            // A draft has not been approved by anyone; it should not reach an
            // employee as though it were their payslip.
            if ($slip->approval_status === 'draft') {
                $reasons[] = ($slip->emp_name ?: 'A payslip') . ' — still a draft, approve it first';
                continue;
            }

            if (!$address || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $reasons[] = ($slip->emp_name ?: 'Someone') . ' — no e-mail address on record';
                continue;
            }

            try {
                Mail::to($address)->send(new PayslipIssued($slip, $company));

                DB::table('payslips')->where('id', $slip->id)->update([
                    'sent_at' => now(), 'sent_to' => $address, 'sent_by' => user()->id,
                ]);

                $sent++;
            } catch (\Throwable $failure) {
                $reasons[] = ($slip->emp_name ?: 'A payslip') . ' — ' . $failure->getMessage();
            }
        }

        return array_merge(
            Reply::success($sent . ' payslip(s) sent.'),
            ['created' => $sent, 'skipped' => count($reasons), 'reasons' => $reasons]
        );
    }

    public function revoke(Request $request, $id)
    {
        $request->validate(
            ['reason' => 'required|string|min:5|max:500'],
            ['reason.required' => 'Give a reason for revoking this payslip.',
             'reason.min' => 'Please describe the reason in a few words.']
        );

        $slip = DB::table('payslips')->whereNull('deleted_at')->find($id);

        if (!$slip) {
            return Reply::error('That payslip no longer exists.');
        }

        if ($slip->status !== 'active') {
            return Reply::error('That payslip is already ' . $slip->status . '.');
        }

        // Money that has left the bank is not something to pretend away: a paid
        // payslip stands, and a correction belongs in the next one.
        if ($slip->approval_status === 'paid') {
            return Reply::error('That payslip has been paid'
                . ($slip->paid_at ? ' on ' . \Carbon\Carbon::parse($slip->paid_at)->format('d M Y') : '')
                . ', so it cannot be revoked. Correct it on the next payslip instead.');
        }

        DB::table('payslips')->where('id', $id)->update([
            'status' => 'revoked',
            'revoke_reason' => trim($request->reason),
            'revoked_at' => now(),
            'revoked_by' => user()->id,
        ]);

        $released = $this->releaseEncashment((int) $id);
        (new \App\Services\Payroll\PayComponents())->releaseFromPayslip((int) $id);

        return Reply::success(sprintf(
            'Payslip revoked for %s, %s. A replacement can now be generated.%s',
            $slip->emp_name ?: 'this employee',
            $slip->period ?: 'that period',
            $released ? ' Its leave encashment will be paid on the replacement.' : ''
        ));
    }

    /**
     * Remove a payslip from the lists.
     *
     * Soft: the row is kept underneath. A salary record should not be
     * destroyed beyond recovery by one click, and the month is released either
     * way, which is what the person clicking actually wants.
     */
    public function destroy($id)
    {
        $slip = DB::table('payslips')->whereNull('deleted_at')->find($id);

        if (!$slip) {
            return Reply::error('That payslip no longer exists.');
        }

        if ($slip->approval_status === 'paid') {
            return Reply::error('That payslip has been paid, so it cannot be deleted. '
                . 'It is part of the payment record.');
        }

        DB::table('payslips')->where('id', $id)->update([
            'deleted_at' => now(),
            'revoked_by' => user()->id,
        ]);

        $this->releaseEncashment((int) $id);
        (new \App\Services\Payroll\PayComponents())->releaseFromPayslip((int) $id);

        return Reply::success(sprintf(
            'Payslip deleted for %s, %s.',
            $slip->emp_name ?: 'this employee',
            $slip->period ?: 'that period'
        ));
    }

    /**
     * Put a withdrawn payslip's leave encashment back in the queue.
     *
     * The money was owed before this payslip and is still owed after it, so it
     * returns to pending and goes out on the next payslip generated. Without
     * this, revoking a slip would quietly cancel the encashment with it.
     *
     * @return int  encashment rows released
     */
    protected function releaseEncashment(int $payslipId): int
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('leave_encashments')) {
            return 0;
        }

        return DB::table('leave_encashments')
            ->where('payslip_id', $payslipId)
            ->where('status', 'paid')
            ->update(['status' => 'pending', 'payslip_id' => null, 'paid_at' => null, 'updated_at' => now()]);
    }

    /**
     * Every payslip, decorated with the employee record it belongs to.
     *
     * `emp_id` on a payslip is the employee's card ID, which is how it reaches
     * `employee_details` — all six employees on record match. The join is left,
     * so a payslip for someone since removed still appears rather than silently
     * dropping out of a payroll total.
     */
    protected function rows()
    {
        $companyId = company()?->id;

        $rows = DB::table('payslips as p')
            ->leftJoin('employee_details as ed', function ($join) use ($companyId) {
                $join->on(DB::raw('CAST(ed.employee_card_id AS CHAR)'), '=', DB::raw('CAST(p.emp_id AS CHAR)'));

                if ($companyId) {
                    $join->where('ed.company_id', '=', $companyId);
                }
            })
            ->leftJoin('users as u', 'u.id', '=', 'ed.user_id')
            ->leftJoin('teams as t', 't.id', '=', 'ed.department_id')
            ->select(
                'p.*',
                'ed.user_id',
                'ed.department_id',
                't.team_name as department_name',
                'u.name as erp_name',
                'u.employee_ctc',
                'u.basic_for_payroll',
                'rb.name as revoked_by_name'
            )
            ->leftJoin('users as rb', 'rb.id', '=', 'p.revoked_by')
            // Deleting is a soft delete, so a removed slip disappears from every
            // list while the row survives underneath.
            ->whereNull('p.deleted_at')
            ->get();

        $latest = $this->latestKeys($rows);
        $extras = \App\Services\Payroll\NetPay::extras($rows->pluck('id'));

        foreach ($rows as $row) {
            [$row->sort_key, $row->period_label] = $this->parsePeriod($row->period);
            $row->is_latest = isset($latest[$this->slipKey($row)]) && $latest[$this->slipKey($row)] === (int) $row->id;
            $row->warnings = $this->warningsFor($row);

            [$row->lop_amount, $row->take_home] = $this->printedNet($row, ...($extras[(int) $row->id] ?? [0.0, 0.0]));
        }

        return $rows;
    }

    /**
     * The LOP deduction and net salary exactly as the printed payslip shows them.
     *
     * Both work from the figures stored when the payslip was generated: net
     * salary, less the attendance (LOP) deduction, less employee and employer
     * PF/ESI, which the slip takes out of CTC. A payslip is never recalculated
     * from the employee's present-day salary, so the list and the slip agree
     * however often pay is revised afterwards.
     *
     * @return array{0: float, 1: float} [lop, net]
     */
    protected function printedNet($row, float $extraEarnings = 0.0, float $extraDeductions = 0.0): array
    {
        // The same figure the slip prints, TDS and extra lines included.
        return [\App\Services\Payroll\NetPay::lop($row),
            \App\Services\Payroll\NetPay::takeHome($row, $extraEarnings, $extraDeductions)];
    }

    /**
     * The payslip that currently stands for each employee-and-period pair.
     *
     * The active one if there is one; otherwise the newest, so a month whose
     * only payslip has been revoked still appears rather than vanishing.
     */
    protected function latestKeys($rows): array
    {
        $latest = [];

        foreach ($rows as $row) {
            $key = $this->slipKey($row);
            $isActive = $row->status === 'active';

            if (!isset($latest[$key])) {
                $latest[$key] = ['id' => (int) $row->id, 'active' => $isActive];

                continue;
            }

            $beats = $isActive && !$latest[$key]['active'];
            $newer = $isActive === $latest[$key]['active'] && (int) $row->id > $latest[$key]['id'];

            if ($beats || $newer) {
                $latest[$key] = ['id' => (int) $row->id, 'active' => $isActive];
            }
        }

        return array_map(fn ($entry) => $entry['id'], $latest);
    }

    protected function slipKey($row): string
    {
        return (string) $row->emp_id . '|' . (string) $row->period;
    }

    /**
     * Turn "July - 2026" into something sortable.
     *
     * Alphabetical order puts August before July, so a real year-month is
     * derived once here rather than ordering on the raw string.
     *
     * @return array{0:string, 1:string}  sort key, and a tidy label
     */
    protected function parsePeriod(?string $period): array
    {
        $period = trim((string) $period);

        if ($period === '') {
            return ['0000-00', '—'];
        }

        // Pulled apart rather than handed to a date parser: "July - 2026" is not
        // a format Carbon accepts, and it failed silently — every period
        // collapsed to the same sort key and the list came out unordered.
        if (preg_match('/([A-Za-z]+)\D*(\d{4})/', $period, $matches)) {
            $timestamp = strtotime($matches[1] . ' 1 ' . $matches[2]);

            if ($timestamp !== false) {
                return [date('Y-m', $timestamp), date('F Y', $timestamp)];
            }
        }

        // An unrecognised format still shows, it just sorts last.
        return ['0000-00', $period];
    }

    /**
     * Figures that genuinely cannot be right.
     *
     * Deliberately narrow. Days Present exceeding Working Days looks wrong and
     * is not: the calculator defines Working Days as physical attendance only
     * (present + half-days), while Days Present adds paid day-offs, holidays,
     * casual and sick leave on top and subtracts loss-of-pay. On any month
     * containing a weekend or a holiday, Days Present is expected to be the
     * larger of the two — the calculator's own comment calls negative unpaid
     * days "extra days worked". Flagging that would mark almost every correct
     * payslip as broken, so only things that are actually impossible appear
     * here.
     *
     * @return array<int, string>
     */
    protected function warningsFor($row): array
    {
        $warnings = [];

        // Per-day pay is net ÷ working days, so zero working days makes the
        // attendance-adjusted figure collapse to nothing.
        if ((float) $row->working_days <= 0) {
            $warnings[] = 'Working days is zero, so per-day pay and attendance-adjusted pay are both nil';
        }

        if ((float) $row->net_salary <= 0) {
            $warnings[] = 'Net salary is zero or negative';
        }

        // The three stored figures must agree with each other.
        $expectedNet = (float) $row->gross - (float) $row->total_deductions;

        if (abs($expectedNet - (float) $row->net_salary) > 0.01) {
            $warnings[] = 'Gross less deductions (' . number_format($expectedNet, 2)
                . ') does not equal the stored net salary (' . number_format((float) $row->net_salary, 2) . ')';
        }

        // Components should account for the whole of gross.
        $components = (float) $row->basic + (float) $row->hra + (float) $row->ca
            + (float) ($row->lta ?? 0) + (float) ($row->cea ?? 0) + (float) $row->others;

        if ((float) $row->gross > 0 && abs($components - (float) $row->gross) > 0.01) {
            $warnings[] = 'Basic, HRA, CA, LTA, CEA and Special Allowance do not add up to gross';
        }

        if ($row->user_id === null) {
            $warnings[] = 'No matching ERP employee for card ID ' . $row->emp_id;
        }

        return $warnings;
    }

    /**
     * Distinct periods, newest first, for the filter.
     */
    protected function periodOptions($rows): array
    {
        return $rows->unique('period')
            ->sortByDesc('sort_key')
            ->mapWithKeys(fn ($row) => [$row->period => $row->period_label])
            ->all();
    }

    protected function applyFilters($rows, array $filters)
    {
        return $rows->filter(function ($row) use ($filters) {
            if (!$filters['all_versions'] && !$row->is_latest) {
                return false;
            }

            if ($filters['period'] && $row->period !== $filters['period']) {
                return false;
            }

            if ($filters['year'] && substr((string) $row->sort_key, 0, 4) !== (string) $filters['year']) {
                return false;
            }

            if ($filters['employee'] && (string) $row->emp_id !== (string) $filters['employee']) {
                return false;
            }

            if ($filters['department'] && (string) $row->department_id !== (string) $filters['department']) {
                return false;
            }

            return true;
        });
    }

}
