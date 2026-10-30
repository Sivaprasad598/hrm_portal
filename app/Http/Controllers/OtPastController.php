<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Services\Payroll\OtPast;
use App\Services\Payroll\OtPastImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Past overtime (OtPast): entered on the employee's OT Bank tab - a year of
 * months at a time, or day by day - or imported for everybody from a
 * spreadsheet on the OT Hours page. HR / Admin only.
 */
class OtPastController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            abort_403(!\App\Services\Access\PayrollAccess::can('settings'));

            return $next($request);
        });
    }

    /** The entry grid for one employee and year. */
    public function year(Request $request, OtPast $past)
    {
        $request->validate(['user_id' => 'required|integer', 'year' => 'nullable|integer|min:2000|max:2100']);
        $this->employee = $this->employee((int) $request->user_id);
        $this->goLive = OtPast::goLive(company()->id);
        $this->year = (int) ($request->year ?: $this->goLive->copy()->subDay()->year);
        $this->years = range($this->goLive->copy()->subDay()->year, 2015);
        $this->grid = $past->year($this->employee->id, $this->year);
        $this->problems = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => $past->monthProblem($this->employee->id, company()->id, $m, $this->year)])->all();

        return Reply::dataOnly(['status' => 'success', 'html' => view('employees.ajax.ot-past-year', $this->data)->render()]);
    }

    public function saveYear(Request $request, OtPast $past)
    {
        $request->validate(['user_id' => 'required|integer', 'year' => 'required|integer|min:2000|max:2100', 'months' => 'array']);
        $employee = $this->employee((int) $request->user_id);
        $grid = $past->year($employee->id, (int) $request->year);

        // Months entered day by day, or not open for past figures, are shown but not posted.
        $months = collect((array) $request->months)->only(range(1, 12))
            ->reject(fn ($v, $m) => $grid[$m]['day_count'] > 0)->all();

        try {
            $count = $past->saveYear($employee->id, company()->id, (int) $request->year, $months);
        } catch (RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        return Reply::success($count ? 'Past OT for ' . $request->year . ' saved.' : 'Nothing changed.');
    }

    public function saveDay(Request $request, OtPast $past)
    {
        $request->validate(['user_id' => 'required|integer', 'date' => 'required|date', 'ot_hours' => 'required', 'id' => 'nullable|integer'],
            ['ot_hours.required' => 'Give the hours banked that day.']);
        $employee = $this->employee((int) $request->user_id);

        try {
            $past->saveDay($employee->id, company()->id, $request->date, $request->only('ot_hours', 'raw_hours', 'note'), $request->id ? (int) $request->id : null);
        } catch (RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        return Reply::success('Day saved.');
    }

    public function destroy(Request $request, OtPast $past)
    {
        $request->validate(['id' => 'required|integer']);

        try {
            $past->delete((int) $request->id);
        } catch (RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        return Reply::success('Removed.');
    }

    public function template(OtPastImport $import)
    {
        return response()->download($import->template(), 'past-ot-import-template.xlsx')->deleteFileAfterSend();
    }

    /** Read the sheet and show every row with what will happen to it. Nothing is saved yet. */
    public function importPreview(Request $request, OtPastImport $import)
    {
        $request->validate(['import_file' => 'required|file|mimes:xlsx,xls,csv|max:10240'], ['import_file.required' => 'Choose the spreadsheet to import.']);

        $this->preview = $import->preview($request->file('import_file')->getRealPath(), company()->id);

        if ($this->preview['error']) {
            return Reply::error($this->preview['error']);
        }

        $this->token = Str::random(32);
        cache()->put('ot_past_import_' . user()->id . '_' . $this->token, $this->preview['rows'], now()->addHour());

        return Reply::dataOnly(['status' => 'success', 'html' => view('ot-hours.import-review', $this->data)->render()]);
    }

    public function importRun(Request $request, OtPastImport $import)
    {
        $key = 'ot_past_import_' . user()->id . '_' . $request->token;
        $rows = cache()->get($key);

        if (!$rows) {
            return Reply::error('This preview has expired. Upload the file again.');
        }

        try {
            $result = $import->apply($rows, company()->id);
        } catch (RuntimeException $e) {
            return Reply::error('Nothing was imported: ' . $e->getMessage() . ' Check the file and upload it again.');
        }

        cache()->forget($key);

        return Reply::successWithData(
            sprintf('Past OT imported for %d employee(s): %d month(s)%s.', $result['employees'], $result['months'], $result['days'] ? ', ' . $result['days'] . ' day(s)' : ''),
            ['redirectUrl' => route('ot-hours.index')]
        );
    }

    protected function employee(int $userId): object
    {
        $e = DB::table('users as u')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->where('u.id', $userId)->first(['u.id', 'u.name', 'ed.employee_id']);
        abort_if(!$e, 404);

        return $e;
    }

}
