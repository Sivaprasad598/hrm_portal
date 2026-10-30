<?php

namespace App\Http\Controllers;

use App\Exports\StatutoryReturnExport;
use App\Services\Payroll\PayoutQuery;
use App\Services\Payroll\PayPeriod;
use App\Services\Payroll\StatutoryReturns;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The monthly filings: provident fund, employee insurance, professional tax.
 *
 * Each is built from the payslips issued for the month, so what is filed and
 * what was paid cannot disagree. The screen shows what each one will contain
 * and, more usefully, what would stop it being accepted.
 */
class StatutoryReturnsController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Statutory Returns';

        $this->middleware(function ($request, $next) {
            abort_403(!\App\Services\Access\PayrollAccess::can('settings'));

            return $next($request);
        });
    }

    public function index(Request $request, PayoutQuery $payouts, StatutoryReturns $returns)
    {
        [$month, $year] = $this->period($request);
        $cycle = PayPeriod::forMonth($month, $year);

        $this->month = $month;
        $this->year = $year;
        $this->cycle = $cycle;
        $this->monthName = $cycle->describe();

        $rows = $payouts->forCycle($cycle);
        $this->rows = $rows;
        $this->pf = $returns->providentFund($rows);
        $this->esi = $returns->employeeInsurance($rows);
        $this->pt = $returns->professionalTax($rows);
        $this->readiness = $returns->readiness($rows);
        $this->totals = $payouts->totals($rows);

        return view('statutory-returns.index', $this->data);
    }

    /**
     * The provident fund return as the portal takes it: a plain text file.
     */
    public function providentFund(Request $request, PayoutQuery $payouts, StatutoryReturns $returns)
    {
        [$month, $year] = $this->period($request);
        $cycle = PayPeriod::forMonth($month, $year);
        $return = $returns->providentFund($payouts->forCycle($cycle, $request->boolean('include_drafts')));

        return response($return['text'], 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="ecr-'
                . Carbon::create($year, $month, 1)->format('Y-m') . '.txt"',
        ]);
    }

    /**
     * The insurance and professional tax returns, as spreadsheets.
     */
    public function spreadsheet(Request $request, PayoutQuery $payouts, StatutoryReturns $returns)
    {
        $request->validate(['which' => 'required|in:pf,esi,pt,tds,all']);

        [$month, $year] = $this->period($request);
        $cycle = PayPeriod::forMonth($month, $year);
        $export = $this->export($request->which, $payouts->forCycle($cycle, $request->boolean('include_drafts')), $returns, $cycle);

        $name = ['pf' => 'pf-return', 'esi' => 'esi-return', 'pt' => 'professional-tax', 'tds' => 'tds-return',
            'all' => 'statutory-returns'][$request->which];

        return Excel::download($export, $name . '-' . Carbon::create($year, $month, 1)->format('Y-m') . '.xlsx');
    }

    /**
     * Every return for the month in one ZIP: the PF ECR text file the portal
     * takes, and one workbook per return (PF, ESI, PT, TDS) plus all together.
     */
    public function bundle(Request $request, PayoutQuery $payouts, StatutoryReturns $returns)
    {
        [$month, $year] = $this->period($request);
        $cycle = PayPeriod::forMonth($month, $year);
        $rows = $payouts->forCycle($cycle, $request->boolean('include_drafts'));
        $stamp = Carbon::create($year, $month, 1)->format('Y-m');

        $path = tempnam(sys_get_temp_dir(), 'returns');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('ecr-' . $stamp . '.txt', $returns->providentFund($rows)['text']);

        foreach (['pf' => 'pf-return', 'esi' => 'esi-return', 'pt' => 'professional-tax', 'tds' => 'tds-return',
            'all' => 'statutory-returns'] as $which => $name) {
            $zip->addFromString($name . '-' . $stamp . '.xlsx',
                Excel::raw($this->export($which, $rows, $returns, $cycle), \Maatwebsite\Excel\Excel::XLSX));
        }

        $zip->close();

        return response()->download($path, 'statutory-returns-' . $stamp . '.zip')->deleteFileAfterSend();
    }

    protected function export(string $which, array $rows, StatutoryReturns $returns, PayPeriod $cycle): StatutoryReturnExport
    {
        return new StatutoryReturnExport(
            $which,
            $returns->providentFund($rows),
            $returns->employeeInsurance($rows),
            $returns->professionalTax($rows),
            $cycle,
            $returns->incomeTax($rows),
        );
    }

    /** Month and year from the request, defaulting to last month. */
    protected function period(Request $request): array
    {
        $default = now()->subMonthNoOverflow();
        $month = (int) ($request->month ?: $default->month);
        $year = (int) ($request->year ?: $default->year);

        return [max(1, min(12, $month)), $year];
    }

}
