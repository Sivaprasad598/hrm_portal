<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Services\Payroll\SettlementCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Full and final settlement: what somebody is owed when they leave.
 *
 * A settlement moves draft → agreed → paid. While it is a draft the figures are
 * worked out fresh every time it is opened, so a correction anywhere feeds in.
 * Once it is agreed the numbers are frozen on the record, because that is what
 * both sides have signed up to.
 */
class SettlementController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Full & Final Settlement';

        $this->middleware(function ($request, $next) {
            abort_403(!in_array('admin', user_roles()));

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $this->settlements = DB::table('settlements as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->leftJoin('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->orderByRaw("FIELD(s.status, 'draft', 'agreed', 'paid', 'cancelled')")
            ->orderByDesc('s.last_working_day')
            ->get(['s.*', 'u.name', 'ed.employee_card_id'])
            ->all();

        // Anybody who has resigned or been off-boarded and has no settlement yet.
        $settled = DB::table('settlements')->whereIn('status', ['draft', 'agreed', 'paid'])->pluck('user_id');

        $this->awaiting = DB::table('resignations as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->whereNotIn('r.user_id', $settled)
            ->orderByDesc('r.last_working_date')
            ->get(['r.user_id', 'r.last_working_date', 'r.status as resignation_status',
                'u.name', 'u.employee_ctc', 'ed.employee_card_id'])
            ->all();

        $this->employees = DB::table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->whereNotIn('u.id', $settled)
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'ed.employee_card_id'])
            ->all();

        $this->totals = [
            'draft' => count(array_filter($this->settlements, fn ($s) => $s->status === 'draft')),
            'agreed' => count(array_filter($this->settlements, fn ($s) => $s->status === 'agreed')),
            'paid' => count(array_filter($this->settlements, fn ($s) => $s->status === 'paid')),
            'owed' => array_sum(array_map(fn ($s) => in_array($s->status, ['draft', 'agreed']) ? (float) $s->net_payable : 0,
                $this->settlements)),
        ];

        return view('settlements.index', $this->data);
    }

    /**
     * What somebody would be owed, worked out now. Nothing is saved.
     */
    public function draft(Request $request, SettlementCalculator $calculator)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'last_working_day' => 'required|date',
            'leave_days' => 'nullable|numeric|min:0|max:400',
            'notice_shortfall_days' => 'nullable|numeric|min:0|max:400',
            'bonus_and_other' => 'nullable|numeric|min:0|max:9999999',
            'other_recovery' => 'nullable|numeric|min:0|max:9999999',
        ]);

        return response()->json($calculator->draft(
            (int) $request->user_id,
            Carbon::parse($request->last_working_day),
            array_filter($request->only([
                'leave_days', 'notice_shortfall_days', 'bonus_and_other', 'other_recovery',
                'other_note', 'recovery_note',
            ]), fn ($v) => $v !== null && $v !== '')
        ));
    }

    /**
     * Keep a draft, so it can be argued over and come back to.
     */
    public function store(Request $request, SettlementCalculator $calculator)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'last_working_day' => 'required|date',
            'note' => 'nullable|string|max:500',
        ]);

        $existing = DB::table('settlements')->where('user_id', $request->user_id)
            ->whereIn('status', ['agreed', 'paid'])->first(['id', 'status']);

        if ($existing) {
            return Reply::error('That employee already has a settlement which has been '
                . $existing->status . '. It cannot be drafted again.');
        }

        $draft = $calculator->draft(
            (int) $request->user_id,
            Carbon::parse($request->last_working_day),
            array_filter($request->only([
                'leave_days', 'notice_shortfall_days', 'bonus_and_other', 'other_recovery',
            ]), fn ($v) => $v !== null && $v !== '')
        );

        if (!$draft['ok']) {
            return Reply::error($draft['message']);
        }

        $values = [
            'company_id' => DB::table('employee_details')->where('user_id', $request->user_id)->value('company_id'),
            'user_id' => $request->user_id,
            'resignation_id' => DB::table('resignations')->where('user_id', $request->user_id)
                ->orderByDesc('id')->value('id'),
            'last_working_day' => $draft['last_working_day'],
            'joined_on' => $draft['joined_on'],
            'years_served' => $draft['years_served'],
            'salary_days' => $draft['salary_days'],
            'salary_due' => $draft['salary_due'],
            'leave_days' => $draft['leave_days'],
            'leave_encashment' => $draft['leave_encashment'],
            'gratuity' => $draft['gratuity'],
            'bonus_and_other' => $draft['bonus_and_other'],
            'total_payable' => $draft['total_payable'],
            'notice_shortfall_days' => $draft['notice_shortfall_days'],
            'notice_recovery' => $draft['notice_recovery'],
            'advances_outstanding' => $draft['advances_outstanding'],
            'other_recovery' => $draft['other_recovery'],
            'statutory_deductions' => $draft['statutory_deductions'],
            'total_deductions' => $draft['total_deductions'],
            'net_payable' => $draft['net_payable'],
            'workings' => json_encode($draft['workings']),
            'note' => $request->note,
            'prepared_by' => user()->id,
            'updated_at' => now(),
        ];

        $draftId = DB::table('settlements')->where('user_id', $request->user_id)
            ->where('status', 'draft')->value('id');

        if ($draftId) {
            DB::table('settlements')->where('id', $draftId)->update($values);
        } else {
            $draftId = DB::table('settlements')->insertGetId($values + ['status' => 'draft', 'created_at' => now()]);
        }

        return Reply::successWithData('Settlement drafted for ' . $draft['name'] . '.', ['id' => $draftId]);
    }

    /**
     * Agree it, which freezes the figures.
     */
    public function agree(Request $request)
    {
        $request->validate(['id' => 'required|integer|exists:settlements,id']);

        $agreed = DB::table('settlements')->where('id', $request->id)->where('status', 'draft')
            ->update(['status' => 'agreed', 'agreed_at' => now(), 'agreed_by' => user()->id, 'updated_at' => now()]);

        return $agreed
            ? Reply::success('Agreed. The figures are now fixed and will not move.')
            : Reply::error('Only a draft can be agreed.');
    }

    /**
     * Record that the money has gone.
     */
    public function markPaid(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:settlements,id',
            'payment_reference' => 'nullable|string|max:100',
        ]);

        $settlement = DB::table('settlements')->where('id', $request->id)
            ->where('status', 'agreed')->first();

        if (!$settlement) {
            return Reply::error('A settlement has to be agreed before it can be paid.');
        }

        $closed = 0;

        DB::transaction(function () use ($request, $settlement, &$closed) {
            DB::table('settlements')->where('id', $request->id)
                ->update(['status' => 'paid', 'paid_at' => now(), 'paid_by' => user()->id,
                    'payment_reference' => $request->payment_reference, 'updated_at' => now()]);

            // The settlement recovered whatever was still owed, so the standing
            // arrangements are finished. Left running they would draw another
            // instalment on the next payroll and take the same money twice.
            $closed = DB::table('payroll_arrangements')
                ->where('user_id', $settlement->user_id)
                ->where('status', 'active')
                ->update(['status' => 'completed', 'updated_at' => now()]);
        });

        return Reply::success('Marked as paid.'
            . ($closed > 0 ? ' ' . $closed . ' advance(s) closed, so nothing is recovered twice.' : ''));
    }

    /**
     * Withdraw one that should not have been drafted.
     */
    public function cancel(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:settlements,id',
            'reason' => 'required|string|max:500',
        ]);

        $settlement = DB::table('settlements')->find($request->id);

        if ($settlement->status === 'paid') {
            return Reply::error('That settlement has been paid, so it cannot be withdrawn.');
        }

        DB::table('settlements')->where('id', $request->id)->update([
            'status' => 'cancelled',
            'note' => trim(($settlement->note ? $settlement->note . ' — ' : '') . 'Withdrawn: ' . $request->reason),
            'updated_at' => now(),
        ]);

        return Reply::success('Withdrawn.');
    }

    /**
     * The statement, as the leaver receives it.
     */
    public function statement(Request $request)
    {
        $request->validate(['id' => 'required|integer|exists:settlements,id']);

        $settlement = DB::table('settlements as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->leftJoin('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('designations as d', 'd.id', '=', 'ed.designation_id')
            ->where('s.id', $request->id)
            ->first(['s.*', 'u.name', 'u.pan_number', 'ed.employee_card_id',
                'ed.personal_account_number', 'ed.ifsc_code', 'd.name as designation']);

        $this->settlement = $settlement;
        $this->workings = json_decode((string) $settlement->workings, true) ?: [];
        $this->company = DB::table('companies')->first();

        $html = view('settlements.statement', $this->data)->render();

        if ($request->boolean('html')) {
            return response($html);
        }

        return Pdf::loadHTML($html)->setPaper('a4')->download(
            'settlement-' . preg_replace('/[^A-Za-z0-9]+/', '-', (string) $settlement->name) . '.pdf'
        );
    }

}
