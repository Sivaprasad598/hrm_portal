<?php

namespace App\Http\Controllers;

use App\Exports\PayrollSheetExport;
use App\Helper\Reply;
use App\Services\Payroll\PayrollImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Payroll -> Payroll Import: a pay period's payslips from a spreadsheet (PayrollImport has
 * the rules). Download a sheet (simple or detailed) or a sample, upload it, read every
 * payslip and the totals, confirm: only then are the payslips generated.
 */
class PayrollImportController extends AccountBaseController
{

    protected const SESSION = 'payroll-import.preview';

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Payroll Import';

        $this->middleware(function ($request, $next) {
            // the same switch as Run Payroll
            abort_403(!\App\Services\Access\PayrollAccess::can('prepare'));

            return $next($request);
        });
    }

    public function index(Request $request, PayrollImport $import)
    {
        $cycle = $import->period($request->query('period'));
        $key = $import->key($cycle);
        $employees = $import->employees($cycle);
        $issued = $import->issued($cycle);

        // the financial year's pay periods, oldest first, with the payslips already issued for each
        $all = collect($import->periods());
        $issuedBy = \Illuminate\Support\Facades\DB::table('payslips')->where('status', 'active')->whereNull('deleted_at')
            ->whereIn('period', $all->map(fn ($c) => $c->label)->values())
            ->selectRaw('period, COUNT(*) as n')->groupBy('period')->pluck('n', 'period');
        $this->periods = $all->reverse()->map(fn ($c, $k) => [
            'key' => $k, 'label' => $c->describe(), 'month' => $c->label,
            'short' => \Carbon\Carbon::create($c->year, $c->month, 1)->format('M'), 'year' => $c->year,
            'dates' => $c->start->format('j M') . ' – ' . $c->end->format('j M'),
            'issued' => (int) ($issuedBy[$c->label] ?? 0),
        ]);
        $this->fyLabel = $import->fyLabel();
        $this->cycle = $cycle;
        $this->periodKey = $key;
        $this->useAttendance = $import->useAttendance();
        $this->employeeCount = $employees->filter(fn ($e) => (float) $e->employee_ctc > 0)->count();
        $this->issuedCount = count(array_intersect_key($issued, $employees->all()));
        $preview = Session::get(self::SESSION);
        $this->preview = $preview && ($preview['period'] ?? null) === $key ? $preview : null;
        $this->otherPreview = $preview && !$this->preview ? $preview : null;
        $this->history = $import->history();

        return view('payroll-import.index', $this->data);
    }

    /** The period's sheet, simple or detailed, everyone with a salary and no payslip yet. */
    public function download(Request $request, PayrollImport $import)
    {
        $cycle = $import->period($request->query('period'));
        $format = $request->query('format') === 'detailed' ? 'detailed' : 'simple';
        @set_time_limit(300);

        return Excel::download(
            new PayrollSheetExport($import->sheet($cycle, $format), $format, $cycle->describe(), $import->useAttendance(),
                null, array_values($import->payElements())),
            'payroll-' . $format . '-' . $cycle->start->format('d-M') . '-to-' . $cycle->end->format('d-M-Y') . '.xlsx'
        );
    }

    /** A small sample of a format. */
    public function sample(Request $request, PayrollImport $import)
    {
        $cycle = $import->period($request->query('period'));
        $format = $request->query('format') === 'detailed' ? 'detailed' : 'simple';

        $sample = $import->sample($cycle, $format);

        return Excel::download(
            new PayrollSheetExport($sample['main'], $format, $cycle->describe(), $import->useAttendance(),
                $import->sampleHeadings($format), array_values($import->payElements()), $import->sampleColumns(), $sample['items']),
            'payroll-' . $format . '-sample.xlsx'
        );
    }

    /** Read an uploaded sheet: every payslip it would generate. Nothing is written. */
    public function preview(Request $request, PayrollImport $import)
    {
        $request->validate([
            'sheet' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'period' => 'required|string',
        ], [], ['sheet' => 'spreadsheet']);

        $periods = $import->periods();

        if (!isset($periods[$request->period])) {
            return Reply::error('Payslips can be imported for the last two pay periods that are over.');
        }

        try {
            $sheets = Excel::toArray(null, $request->file('sheet'));
            $rows = $sheets[0] ?? [];
            // the Earnings & Deductions tab, when the workbook has one (any tab after the first)
            $itemRows = $import->itemsSheet($sheets) ?? [];
        } catch (\Throwable) {
            return Reply::error('That file could not be read as a spreadsheet.');
        }

        if (count($rows) < 2) {
            return Reply::error('That sheet has no lines under its headings.');
        }

        @set_time_limit(300);
        $preview = $import->preview($rows, $periods[$request->period], $itemRows);

        if (!empty($preview['error'])) {
            return Reply::error($preview['error']);
        }

        $preview['file'] = $request->file('sheet')->getClientOriginalName();
        Session::put(self::SESSION, $preview);

        return Reply::success('Read. Nothing has been generated yet.');
    }

    /** Generate the payslips the preview showed. */
    public function apply(PayrollImport $import)
    {
        $preview = Session::get(self::SESSION);

        if (!$preview) {
            return Reply::error('There is nothing waiting to be generated. Upload the sheet again.');
        }

        $periods = $import->periods();

        if (!isset($periods[$preview['period']])) {
            Session::forget(self::SESSION);

            return Reply::error('That period can no longer be imported.');
        }

        if (($preview['entries'] ?? []) === []) {
            return Reply::error('That sheet has no payslips to generate.');
        }

        // the attendance setting decided the figures: if it was changed since, read the sheet again
        if ((bool) $preview['attendance'] !== $import->useAttendance()) {
            Session::forget(self::SESSION);

            return Reply::error('"Use biometric attendance for payroll" was changed since this sheet was read. Upload it again.');
        }

        @set_time_limit(600);
        $result = $import->apply($preview, $periods[$preview['period']], (int) user()->id, $preview['file'] ?? null);
        Session::forget(self::SESSION);

        $message = sprintf('%d payslip(s) generated, net pay %s.', $result['created'], $import->money($result['net']));

        if ($result['skipped']) {
            $message .= ' ' . count($result['skipped']) . ' left out: ' . implode('; ', array_slice($result['skipped'], 0, 5))
                . (count($result['skipped']) > 5 ? '; ...' : '');
        }

        return Reply::successWithData($message, $result + ['redirectUrl' => route('payslips.generated')]);
    }

    public function discard()
    {
        Session::forget(self::SESSION);

        return Reply::success('Discarded. Nothing was generated.');
    }

}
