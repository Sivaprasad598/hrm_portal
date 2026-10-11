<?php

namespace App\Http\Controllers;

use App\Exports\EmployeeDetailsExport;
use App\Helper\Reply;
use App\Services\Payroll\EmployeeImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Filling in salary and statutory details for everyone at once.
 *
 * Three steps, in this order on purpose: take away what is on record, fill in
 * the blanks, bring it back. The upload is only ever a preview; nothing is
 * written until the changes have been read and accepted.
 */
class EmployeeImportController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Import Employee Details';

        $this->middleware(function ($request, $next) {
            // Salary data for the whole company.
            abort_403(!in_array('admin', user_roles()));

            return $next($request);
        });
    }

    public function index(EmployeeImport $import)
    {
        $this->columns = EmployeeImport::COLUMNS;
        $this->preview = Session::get('employee-import.preview');
        $this->missing = $this->missing($import);

        return view('employee-import.index', $this->data);
    }

    /**
     * The sheet to fill in: everyone, with what is already known.
     */
    public function template(EmployeeImport $import)
    {
        return Excel::download(
            new EmployeeDetailsExport($import->current()),
            'employee-details-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * Read an uploaded sheet and show what it would change. Nothing is written.
     */
    public function preview(Request $request, EmployeeImport $import)
    {
        $request->validate([
            'sheet' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ], [], ['sheet' => 'spreadsheet']);

        $sheets = Excel::toArray(null, $request->file('sheet'));
        $rows = $sheets[0] ?? [];

        if ($rows === []) {
            return back()->with('employee-import.error', 'That file has no rows in it.');
        }

        $preview = $import->preview($rows);
        $preview['file'] = $request->file('sheet')->getClientOriginalName();

        Session::put('employee-import.preview', $preview);

        return redirect()->route('employee-import.index');
    }

    /**
     * Write what the preview showed.
     */
    public function apply(EmployeeImport $import)
    {
        $preview = Session::get('employee-import.preview');

        if (!$preview) {
            return Reply::error('There is nothing waiting to be applied. Upload the sheet again.');
        }

        if (($preview['summary']['changes'] ?? 0) < 1) {
            return Reply::error('That sheet changes nothing.');
        }

        $result = $import->apply($preview, user()->id);
        Session::forget('employee-import.preview');

        return Reply::successWithData(
            sprintf('%d employee(s) updated, %d field(s) filled in.', $result['employees'], $result['fields']),
            $result
        );
    }

    /**
     * Forget an uploaded sheet without writing any of it.
     */
    public function discard()
    {
        Session::forget('employee-import.preview');

        return redirect()->route('employee-import.index');
    }

    /**
     * What is still missing across the company, so the point of this is plain.
     *
     * @return array<string, int>
     */
    protected function missing(EmployeeImport $import): array
    {
        $rows = $import->current();
        $count = fn (string $key) => count(array_filter($rows,
            fn ($r) => $r[$key] === null || $r[$key] === '' || $r[$key] === 0.0));

        return [
            'employees' => count($rows),
            'ctc' => $count('Annual CTC'),
            'code' => $count('Employee Code'),
            'pan' => $count('PAN'),
            'bank' => $count('Bank Account No'),
            'pf' => $count('PF Account No'),
            'esi' => $count('ESI Number'),
        ];
    }

}
