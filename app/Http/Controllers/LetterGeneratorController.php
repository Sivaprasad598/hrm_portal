<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Services\Employee\OfferLetter;
use App\Services\Employee\RelievingLetter;
use App\Services\Payroll\SalaryRevisionLetter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The Letter Generator: an offer letter (a candidate, or anyone) or a
 * relieving letter (someone who left before the system) typed into a form,
 * with the letter drawn live beside it and the salary worked out from the CTC
 * as payroll does. The same letterhead and wording as the letters made from an
 * employee; each letter keeps what it printed.
 */
class LetterGeneratorController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Letter Generator';

        $this->middleware(function ($request, $next) {
            abort_403(!OfferLetterController::canUse() && !RelievingLetterController::canUse());

            return $next($request);
        });
    }

    protected function type(Request $request): string
    {
        $type = $request->type === 'relieving' ? 'relieving' : 'offer';
        abort_403($type === 'offer' ? !OfferLetterController::canUse() : !RelievingLetterController::canUse());

        return $type;
    }

    public function create(Request $request)
    {
        $this->type = $this->type($request);
        $this->settings = OfferLetter::settings();
        $this->suggested = OfferLetter::suggestReference();
        $this->details = [];

        // Duplicate: start from a letter already issued.
        if ($request->duplicate) {
            $table = $this->type === 'offer' ? 'offer_letters' : 'relieving_letters';
            $from = DB::table($table)->where('id', $request->duplicate)->where('company_id', company()->id)->first();
            $this->details = $from && $from->details ? json_decode($from->details, true) : [];
        }

        // Who can be picked to fill the form: active staff for an offer, anyone for a relieving letter.
        $this->people = DB::table('users as u')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->where('u.company_id', company()->id)
            ->when($this->type === 'offer', fn ($q) => $q->where('u.status', 'active'))
            ->orderBy('u.name')->get(['u.id', 'u.name', 'ed.employee_id as code', 'ed.last_date']);
        $this->designations = DB::table('designations')->where('company_id', company()->id)->orderBy('name')->pluck('name');
        $this->branches = DB::table('branches')->orderBy('branch_name')->pluck('branch_name');
        $this->departments = DB::table('teams')->where('company_id', company()->id)->orderBy('team_name')->pluck('team_name');
        $this->noticeOptions = DB::table('notice_period_settings')->where('company_id', company()->id)->orderByDesc('months')->get(['name', 'months']);
        $this->basicDefault = (float) ((new \App\Services\Payroll\SalaryCalculator())->settings()->basic_pct ?? 40);

        return view('letter-generator.form', $this->data);
    }

    /** An employee's details, to fill the form (everything stays editable). */
    public function prefill(Request $request, $userId)
    {
        $type = $this->type($request);
        abort_if(!DB::table('users')->where('id', $userId)->where('company_id', company()->id)->exists(), 404);

        return Reply::dataOnly(['status' => 'success',
            'details' => $type === 'offer' ? OfferLetter::detailsFor((int) $userId) : RelievingLetter::detailsFor((int) $userId)]);
    }

    /** The letter as it stands on the form, and (offer) the salary worked out from the CTC. */
    public function preview(Request $request)
    {
        $type = $this->type($request);
        $details = (array) $request->input('d', []);

        if ($type === 'relieving') {
            return Reply::dataOnly(['status' => 'success', 'html' => RelievingLetter::html(null, false, null, $details),
                'tenure' => RelievingLetter::tenure($details['joining_date'] ?? null, $details['relieving_date'] ?? null)]);
        }

        $ctc = (float) ($details['ctc'] ?? 0);
        $pay = $ctc > 0 ? OfferLetter::pay($ctc, ($details['basic_pct'] ?? '') !== '' ? (float) $details['basic_pct'] : null) : null;

        return Reply::dataOnly(['status' => 'success', 'html' => OfferLetter::html(null, false, null, null, $details),
            'pay' => $pay ? [
                'rows' => collect($pay['earnings'])->map(fn ($v, $k) => [$k, SalaryRevisionLetter::inr($v)])->values(),
                'gross' => SalaryRevisionLetter::inr($pay['gross']),
                'deductions' => SalaryRevisionLetter::inr($pay['deductions_total']),
                'net' => SalaryRevisionLetter::inr($pay['net']),
                'words' => SalaryRevisionLetter::words($ctc) . ' Rupees Only',
            ] : null]);
    }

    public function store(Request $request)
    {
        $type = $this->type($request);
        $details = (array) $request->input('d', []);
        $userId = $request->user_id && DB::table('users')->where('id', $request->user_id)->where('company_id', company()->id)->exists()
            ? (int) $request->user_id : null;

        if ($type === 'relieving') {
            if ($problems = RelievingLetter::formProblems($details)) {
                return Reply::error(implode(' ', $problems));
            }
            $letter = RelievingLetter::issueManual($details, $request->letter_date, $userId);

            return Reply::successWithData('Relieving letter generated.', ['redirect_url' => route('relieving-letters.show', $letter->id)]);
        }

        if ($problems = OfferLetter::formProblems($details)) {
            return Reply::error(implode(' ', $problems));
        }

        [$letter, $error] = OfferLetter::issueManual($details, (string) $request->reference, $request->letter_date, $userId);

        if (!$letter) {
            return Reply::error($error);
        }

        return Reply::successWithData('Offer letter ' . $letter->reference_no . ' generated.', ['redirect_url' => route('offer-letters.show', $letter->id)]);
    }

}
