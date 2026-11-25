<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Services\Employee\MasterSheetImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Employees → Import: the HR master sheet, reviewed before it is saved.
 *
 * Upload reads the sheet and shows everything it would do; nothing is written
 * until Import is pressed on that review. The review is kept per person for two
 * hours, so the problems report can be downloaded and the import applied from
 * exactly what was shown.
 */
class EmployeeMasterImportController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = __('app.importExcel') . ' ' . __('app.employee');

        $this->middleware(function ($request, $next) {
            // Salaries, bank details and identity numbers for the whole company.
            abort_403(user()->permission('add_employees') !== 'all');

            return $next($request);
        });
    }

    protected function key(): string
    {
        return 'employee-master-import:' . user()->id;
    }

    /** The sample file: the master sheet's own layout. */
    public function sample(MasterSheetImport $import)
    {
        return $this->download($import->sample(), 'employee-import-sample.xlsx');
    }

    /** Read the uploaded sheet and show the review. Nothing is written. */
    public function review(Request $request, MasterSheetImport $import)
    {
        $request->validate(['import_file' => 'required|file|mimes:xlsx,xls|max:20480'], [], ['import_file' => 'file']);

        session()->save();   // reading a large sheet takes a few seconds; do not hold the session

        try {
            $preview = $import->preview($request->file('import_file')->getRealPath(), $request->file('import_file')->getClientOriginalName());
        } catch (\Throwable $e) {
            report($e);

            return Reply::error('The file could not be read: ' . $e->getMessage());
        }

        if (!$preview['ok']) {
            return Reply::error($preview['message']);
        }

        // a copy of the file, for "Download these rows" (the filtered rows, to fix and upload again)
        $dir = storage_path('app/employee-imports');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($dir);
        $copy = $dir . '/' . user()->id . '.xlsx';
        @copy($request->file('import_file')->getRealPath(), $copy);
        $preview['source'] = is_file($copy) ? $copy : null;

        Cache::store('file')->put($this->key(), $preview, now()->addHours(2));

        $this->preview = $preview;
        $this->defaultPassword = MasterSheetImport::DEFAULT_PASSWORD;

        return Reply::successWithData('Sheet read. Review it before importing.', [
            'view' => view('employees.ajax.import-review', $this->data)->render(),
        ]);
    }

    /** Write what the review showed. */
    public function apply(Request $request, MasterSheetImport $import)
    {
        $request->validate([
            'password' => 'required|string|min:6|max:50',
            'branches' => 'nullable|array',
            'device_links' => 'nullable|array',
        ]);

        $preview = Cache::store('file')->get($this->key());

        if (!$preview) {
            return Reply::error('The review has expired. Upload the sheet again.');
        }

        session()->save();

        try {
            $result = $import->apply($preview, [
                'password' => $request->password,
                'branches' => (array) $request->branches,
                'device_links' => array_map('strval', (array) $request->device_links),
            ], user()->id);
        } catch (\Throwable $e) {
            report($e);

            return Reply::error('Nothing was imported: ' . $e->getMessage());
        }

        Cache::store('file')->put($this->key() . ':done', $preview, now()->addHours(2));
        Cache::store('file')->forget($this->key());

        $this->result = $result;
        $this->preview = $preview;

        return Reply::successWithData(sprintf('%d employee(s) added, %d updated.', $result['created'], $result['updated']), [
            'view' => view('employees.ajax.import-done', $this->data)->render(),
        ]);
    }

    /** Every problem and correction the review found, as a sheet. */
    public function report(MasterSheetImport $import)
    {
        $preview = Cache::store('file')->get($this->key()) ?? Cache::store('file')->get($this->key() . ':done');

        abort_if(!$preview, 404, 'Upload the sheet again to get its report.');

        return $this->download($import->report($preview), 'employee-import-problems-' . now()->format('Y-m-d') . '.xlsx');
    }

    /** The rows of one review filter, from the uploaded file in its own layout, to fix and upload again. */
    public function rows(Request $request, MasterSheetImport $import)
    {
        $preview = Cache::store('file')->get($this->key()) ?? Cache::store('file')->get($this->key() . ':done');
        abort_if(!$preview || empty($preview['source']) || !is_file($preview['source']), 404, 'Upload the sheet again to download its rows.');

        $filter = array_key_exists((string) $request->filter, MasterSheetImport::FILTERS) ? (string) $request->filter : 'all';
        $base = pathinfo((string) $preview['file'], PATHINFO_FILENAME) ?: 'employees';
        $name = $base . ' - ' . MasterSheetImport::FILTERS[$filter] . '.xlsx';

        return $this->download($import->rowsExport($preview['source'], $preview, $filter), $name);
    }

    protected function download($book, string $name)
    {
        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

}
