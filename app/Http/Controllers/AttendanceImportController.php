<?php

namespace App\Http\Controllers;

use App\Exports\AttendanceSheetExport;
use App\Helper\Reply;
use App\Services\Attendance\AttendanceImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\Facades\Excel;

/**
 * HR -> Attendance Import: a pay period's attendance from a spreadsheet, until
 * payroll for the period is generated (AttendanceImport has the rules).
 *
 * Three steps, as Employee Import: download the period's sheet, upload it and
 * read what it would change, apply. The upload is only ever a preview.
 */
class AttendanceImportController extends AccountBaseController
{

    protected const SESSION = 'attendance-import.preview';

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Attendance Import';

        $this->middleware(function ($request, $next) {
            // The HR team: attendance for everybody, which payroll pays from.
            abort_403(!array_intersect(['admin', 'manager_admin', 'hr', 'cfo'], user_roles()));   // Admin added 2026-11-23, CFO 2026-11-24

            return $next($request);
        });
    }

    public function index(Request $request, AttendanceImport $import)
    {
        $cycle = $import->period($request->query('period'));
        $key = $import->key($cycle);
        $employees = $import->employees($cycle);
        $locks = $import->locks($employees->pluck('id')->map(fn ($id) => (int) $id)->all(), $cycle);

        $this->periods = collect($import->periods())->map(fn ($c, $k) => [
            'key' => $k, 'label' => $c->describe(), 'running' => $c->end->toDateString() >= now($import->timezone())->toDateString(),
        ]);
        $this->cycle = $cycle;
        $this->periodKey = $key;
        $this->lastDay = $import->lastDay($cycle);
        $this->employeeCount = $employees->count();
        $this->lockedCount = count($locks);
        $preview = Session::get(self::SESSION);
        $this->preview = $preview && ($preview['period'] ?? null) === $key ? $preview : null;
        $this->otherPreview = $preview && !$this->preview ? $preview : null;
        $this->history = $import->history();
        $this->shift = $this->generalShift($import);

        return view('attendance-import.index', $this->data);
    }

    /** The period's sheet, with what is on record filled in. */
    public function download(Request $request, AttendanceImport $import)
    {
        $cycle = $import->period($request->query('period'));

        return Excel::download(
            new AttendanceSheetExport($import->sheet($cycle), $cycle->describe(), $this->generalShift($import)),
            'attendance-' . $cycle->start->format('d-M') . '-to-' . $cycle->end->format('d-M-Y') . '.xlsx'
        );
    }

    /** A small sample: each way a day can be filled (P, HD, A, with and without times). */
    public function sample(Request $request, AttendanceImport $import)
    {
        $cycle = $import->period($request->query('period'));

        return Excel::download(
            new AttendanceSheetExport($import->sample($cycle), $cycle->describe(), $this->generalShift($import)),
            'attendance-upload-sample.xlsx'
        );
    }

    /** Read an uploaded sheet and keep what it would change. Nothing is written. */
    public function preview(Request $request, AttendanceImport $import)
    {
        $request->validate([
            'sheet' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'period' => 'required|string',
        ], [], ['sheet' => 'spreadsheet']);

        $periods = $import->periods();

        if (!isset($periods[$request->period])) {
            return Reply::error('Attendance can only be uploaded for the running pay period and the one before it.');
        }

        $cycle = $periods[$request->period];

        try {
            $rows = Excel::toArray(null, $request->file('sheet'))[0] ?? [];
        } catch (\Throwable $e) {
            return Reply::error('That file could not be read as a spreadsheet.');
        }

        if (count($rows) < 2) {
            return Reply::error('That sheet has no lines under its headings.');
        }

        $preview = $import->preview($rows, $cycle);

        if (!empty($preview['error'])) {
            return Reply::error($preview['error']);
        }

        $preview['file'] = $request->file('sheet')->getClientOriginalName();
        Session::put(self::SESSION, $preview);

        return Reply::success('Read. Nothing has been written yet.');
    }

    /** Write what the preview showed (the locks are checked again). */
    public function apply(AttendanceImport $import)
    {
        $preview = Session::get(self::SESSION);

        if (!$preview) {
            return Reply::error('There is nothing waiting to be applied. Upload the sheet again.');
        }

        $periods = $import->periods();

        if (!isset($periods[$preview['period']])) {
            Session::forget(self::SESSION);

            return Reply::error('That period can no longer be uploaded. Upload the sheet for the current period.');
        }

        if (($preview['changes'] ?? []) === []) {
            return Reply::error('That sheet changes nothing.');
        }

        $result = $import->apply($preview, $periods[$preview['period']], (int) user()->id, $preview['file'] ?? null);
        Session::forget(self::SESSION);

        $message = sprintf('%d day(s) added, %d corrected, %d marked absent.', $result['created'], $result['replaced'], $result['removed']);

        if ($result['skipped']) {
            $message .= ' ' . count($result['skipped']) . ' left out: ' . implode('; ', array_slice($result['skipped'], 0, 5))
                . (count($result['skipped']) > 5 ? '; ...' : '');
        }

        return Reply::successWithData($message, $result);
    }

    /**
     * One upload from "Recent uploads": what it saved, as a register and day by day. A side panel
     * when opened from the page, a page of its own when opened directly.
     */
    public function show(int $id, AttendanceImport $import)
    {
        $upload = $import->upload($id);
        abort_if(!$upload, 404);

        $this->upload = $upload;
        $this->pageTitle = 'Attendance upload: ' . ($upload['import']->file_name ?: $upload['import']->period_label);

        if (request()->ajax()) {
            return Reply::dataOnly(['status' => 'success', 'html' => view('attendance-import.upload', $this->data)->render(), 'title' => $this->pageTitle]);
        }

        return view('attendance-import.upload-page', $this->data);
    }

    /** Forget an uploaded sheet without writing any of it. */
    public function discard()
    {
        Session::forget(self::SESSION);

        return Reply::success('Discarded. Nothing was written.');
    }

    /** The company's default shift (the General Shift), as the sheet and the page describe it. */
    protected function generalShift(AttendanceImport $import): ?array
    {
        return $import->generalShift(company()?->id);
    }

}
