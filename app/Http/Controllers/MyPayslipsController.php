<?php

namespace App\Http\Controllers;

use App\Services\Payroll\PayslipDocument;
use App\Services\Payroll\TaxCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * An employee's own payslips.
 *
 * Everything here is scoped to the person signed in: the list is built from
 * their id, and a payslip asked for by number is looked up within that same
 * scope, so changing the number in the address bar cannot reach a colleague's
 * slip.
 *
 * Only payslips that have been approved or paid are shown. A draft is still
 * being worked on and a revoked one is no longer valid; neither is something
 * an employee should be reading.
 */
class MyPayslipsController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.payslips';
    }

    public function index()
    {
        $this->payslips = $this->mine()
            ->orderByDesc('p.period_start')->orderByDesc('p.id')
            ->get(['p.id', 'p.payslip_number', 'p.period', 'p.period_start', 'p.period_end',
                'p.basic', 'p.hra', 'p.ca', 'p.lta', 'p.cea', 'p.others', 'p.pf', 'p.esi', 'p.pt', 'p.tds',
                'p.employer_pf', 'p.employer_esi',
                'p.net_salary', 'p.attendance_net', 'p.leave_encashment', 'p.working_days',
                'p.days_present', 'p.approval_status', 'p.generated_at', 'p.sent_at']);

        // What the slip actually pays, extras included — the same figure the
        // printed slip shows, so the list and the document agree.
        $extras = DB::table('payslip_components')
            ->whereIn('payslip_id', $this->payslips->pluck('id'))
            ->selectRaw('payslip_id, kind, SUM(amount) as total')
            ->groupBy('payslip_id', 'kind')
            ->get();

        foreach ($this->payslips as $payslip) {
            $lines = $extras->where('payslip_id', $payslip->id);
            $payslip->extra_earnings = (float) $lines->where('kind', 'earning')->sum('total');
            $payslip->extra_deductions = (float) $lines->where('kind', 'deduction')->sum('total');
            $payslip->take_home = \App\Services\Payroll\NetPay::takeHome($payslip, $payslip->extra_earnings, $payslip->extra_deductions)
                + (float) $payslip->leave_encashment;
        }

        return view('my-payslips.index', $this->data);
    }

    /**
     * The slip itself, as its own page.
     */
    public function show(int $id, PayslipDocument $document)
    {
        $this->payslip = $this->findOrFail($id);
        $this->html = $document->html($id);

        return view('my-payslips.show', $this->data);
    }

    /**
     * This employee's own Form 16, for their tax return.
     *
     * The admin screen produces the same document for anybody; this one is
     * fixed to whoever is signed in.
     */
    public function formSixteen(Request $request, TaxCalculator $tax)
    {
        $year = $request->financial_year ?: TaxCalculator::financialYear();

        // Only once something has actually been issued for that year, so an
        // employee cannot download an empty certificate.
        $issued = $this->mine()
            ->whereDate('p.period_start', '>=', TaxCalculator::yearStart($year)->toDateString())
            ->whereDate('p.period_start', '<=', TaxCalculator::yearStart($year)->copy()->addYear()->subDay()->toDateString())
            ->count();

        abort_if($issued < 1, 404);

        return app(IncomeTaxController::class)->formSixteen(
            $request->merge(['user_id' => user()->id, 'financial_year' => $year]),
            $tax
        );
    }

    /**
     * The same slip to keep or to print.
     */
    public function pdf(int $id, PayslipDocument $document)
    {
        $this->findOrFail($id);

        return $document->pdf($id)->download($document->filename($id));
    }

    /**
     * One of this employee's own payslips, or nothing at all.
     */
    protected function findOrFail(int $id)
    {
        $payslip = $this->mine()->where('p.id', $id)->first(['p.id', 'p.payslip_number', 'p.period']);

        abort_if($payslip === null, 404);

        return $payslip;
    }

    /**
     * The scope every query here is built on: this employee, issued slips only.
     */
    protected function mine()
    {
        return DB::table('payslips as p')
            ->where('p.user_id', user()->id)
            ->where('p.status', 'active')
            ->whereIn('p.approval_status', ['approved', 'paid'])
            ->whereNull('p.deleted_at');
    }

}
