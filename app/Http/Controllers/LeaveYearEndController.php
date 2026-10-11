<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Services\Leaves\LeaveYearEnd;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Closing a financial year for leave: preview first, then apply once.
 *
 * Deliberately a screen rather than a scheduled job. Closing the year discards
 * leave, carries it into a new year and puts money on payslips, so it happens
 * when someone has looked at the figures and pressed the button — and never
 * twice for the same year.
 */
class LeaveYearEndController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Leave Year End';

        $this->middleware(function ($request, $next) {
            // The people who set leave policy are the people who close the year.
            abort_403(!(user()->permission('manage_leave_setting') == 'all' && in_array('leaves', user_modules())));

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $engine = LeaveYearEnd::forCompany(company());

        $this->years = $engine->yearOptions();

        // The requested year, else the most recent one that has ended and is
        // still open, else the current year.
        $selected = collect($this->years)->firstWhere('label', $request->fy)
            ?? collect($this->years)->first(fn ($y) => $y['ended'] && !$y['closed'])
            ?? $this->years[0];

        $this->fy = $selected;
        $this->result = $selected['closed']
            ? $engine->stored((int) $selected['run_id'])
            : $engine->preview($selected['start'], $selected['end']);

        $this->yearStartMonth = Carbon::create(null, (int) (company()->year_starts_from ?: 1), 1)->format('F');

        return view('leave-year-end.index', $this->data);
    }

    public function apply(Request $request)
    {
        $request->validate(['fy' => 'required|string']);

        $engine = LeaveYearEnd::forCompany(company());
        $year = collect($engine->yearOptions())->firstWhere('label', $request->fy);

        if (!$year) {
            return Reply::error('That financial year is not available to close.');
        }

        $result = $engine->apply($year['start'], $year['end'], $year['label'], user()->id);

        return $result['ok']
            ? Reply::successWithData($result['message'], ['run_id' => $result['run_id']])
            : Reply::error($result['message']);
    }

}
