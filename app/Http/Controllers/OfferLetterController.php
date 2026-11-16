<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Services\Employee\OfferLetter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Offer letters for employees: pick the employee (here, or from the Employees
 * list / profile), the record is checked, HR confirms the reference number
 * (the next one suggested), and the letter opens to print or download. The
 * PDF goes into the employee's documents. Wording: Offer Letter Settings.
 */
class OfferLetterController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Offer Letters';

        $this->middleware(function ($request, $next) {
            abort_403(!self::canUse());

            return $next($request);
        });
    }

    /** Whoever registers employees (Edit Employees), the administrators, and payroll. */
    public static function canUse(): bool
    {
        return (bool) array_intersect(['admin', 'manager_admin'], user_roles())
            || user()->permission('edit_employees') === 'all'
            || \App\Services\Access\PayrollAccess::can('prepare');
    }

    public function index()
    {
        $this->employees = DB::table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('designations as d', 'd.id', '=', 'ed.designation_id')
            ->where('u.company_id', company()->id)
            ->where('u.status', 'active')
            ->whereNull('ed.last_date')
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'ed.employee_id as code', 'd.name as designation']);

        $this->letters = DB::table('offer_letters as o')
            ->join('users as u', 'u.id', '=', 'o.user_id')
            ->leftJoin('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('users as g', 'g.id', '=', 'o.generated_by')
            ->where('o.company_id', company()->id)
            ->orderByDesc('o.id')
            ->limit(200)
            ->get(['o.id', 'o.reference_no', 'o.letter_date', 'u.name', 'ed.employee_id as code', 'g.name as generated_by']);

        $this->canEditSettings = OfferLetterSettingController::canEdit();

        return view('offer-letters.index', $this->data);
    }

    /**
     * Before the reference is asked: what is missing on the employee (each
     * line to put right), else the number to suggest and any letter already
     * issued.
     */
    public function check($userId)
    {
        $problems = OfferLetter::problems((int) $userId);
        $latest = OfferLetter::latestFor((int) $userId);

        return Reply::dataOnly([
            'status' => 'success',
            'problems' => $problems,
            'edit_url' => route('employees.edit', $userId),
            'profile_url' => route('employees.show', $userId),
            'suggested' => OfferLetter::suggestReference(),
            'existing' => $latest ? [
                'reference' => $latest->reference_no,
                'date' => \Carbon\Carbon::parse($latest->letter_date)->format(company()->date_format),
                'url' => route('offer-letters.show', $latest->id),
            ] : null,
        ]);
    }

    public function issue(Request $request, $userId)
    {
        $problems = OfferLetter::problems((int) $userId);

        if ($problems) {
            return Reply::error('Put these right on the employee first: ' . implode(' ', $problems));
        }

        [$letter, $error] = OfferLetter::issue((int) $userId, (string) $request->reference);

        if (!$letter) {
            return Reply::error($error);
        }

        return Reply::successWithData('Offer letter ' . $letter->reference_no . ' generated.', [
            'redirect_url' => route('offer-letters.show', $letter->id),
        ]);
    }

    /** The letter with Print / Download. */
    public function show($id)
    {
        $this->letter = $this->letter($id);
        $this->employee = DB::table('users')->where('id', $this->letter->user_id)->first(['id', 'name']);
        $this->problems = OfferLetter::problems((int) $this->letter->user_id);
        $this->pageTitle = 'Offer Letter ' . $this->letter->reference_no;

        return view('offer-letters.show', $this->data);
    }

    /** The letter itself, for the frame on the show page. */
    public function view($id)
    {
        return response(OfferLetter::html($this->letter($id), false));
    }

    /** The PDF: downloaded, or shown in the browser to print (?inline=1). Kept with the employee's documents. */
    public function pdf(Request $request, $id)
    {
        $letter = $this->letter($id);
        $pdf = OfferLetter::pdf($letter);
        $binary = $pdf->output();

        try {
            OfferLetter::keep($letter, $binary);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Offer letter not kept with the documents: ' . $e->getMessage());
        }

        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . OfferLetter::fileName($letter) . '"',
        ]);
    }

    protected function letter($id): object
    {
        $letter = DB::table('offer_letters')->where('id', $id)->where('company_id', company()->id)->first();
        abort_if(!$letter, 404);

        return $letter;
    }

}
