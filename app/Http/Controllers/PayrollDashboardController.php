<?php

namespace App\Http\Controllers;

use App\Services\Payroll\PayoutQuery;
use App\Services\Payroll\PayPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * What payroll has paid out over a stretch of time, element by element.
 *
 * The register answers "what do I file for this month". This answers "where
 * has the money gone" across any range of dates: a card for every part of pay,
 * each one openable to see exactly which employees are behind the figure.
 *
 * Both screens read through PayoutQuery, so they cannot disagree.
 */
class PayrollDashboardController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Payroll Dashboard';

        $this->middleware(function ($request, $next) {
            // Whole-company salary figures. This was the administrator role
            // itself, which meant payroll could not be handed to anybody
            // without handing them everything else with it. manage_payroll is
            // the permission for the job; the administrator passes regardless.
            abort_403(user()->permission('manage_payroll') !== 'all' && !in_array('admin', user_roles()));

            return $next($request);
        });
    }

    public function index(Request $request, PayoutQuery $payouts)
    {
        [$from, $to, $preset] = $this->range($request);

        $this->from = $from;
        $this->to = $to;
        $this->preset = $preset;
        $this->presets = $this->presetList();
        $this->includeDrafts = $request->boolean('include_drafts');

        $rows = $payouts->forRange($from, $to, $this->includeDrafts);

        $this->rows = $rows;
        $this->totals = $payouts->totals($rows);
        $this->elements = $payouts->byElement($rows);
        $this->departments = $payouts->byDepartment($rows);
        $this->periods = $this->periodsCovered($rows);

        return view('payroll-dashboard.index', $this->data);
    }

    /**
     * Who is behind one card's figure.
     */
    public function breakdown(Request $request, PayoutQuery $payouts)
    {
        $request->validate(['metric' => 'required|string|max:120']);

        [$from, $to] = $this->range($request);
        $rows = $payouts->forRange($from, $to, $request->boolean('include_drafts'));

        return response()->json($payouts->behind($rows, $request->metric));
    }

    /**
     * The dates being looked at, and which quick pick produced them.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    protected function range(Request $request): array
    {
        $preset = (string) $request->preset;

        if ($request->filled('from') && $request->filled('to')) {
            $from = Carbon::parse($request->from)->startOfDay();
            $to = Carbon::parse($request->to)->startOfDay();

            // Entered the wrong way round: read it the way it was meant.
            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }

            return [$from, $to, $preset ?: 'custom'];
        }

        return $this->fromPreset($preset ?: 'this_year');
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    protected function fromPreset(string $preset): array
    {
        $today = now()->startOfDay();
        // The pay cycle, not the calendar, decides where a month begins.
        $thisCycle = PayPeriod::forMonth($today->month, $today->year);

        [$from, $to] = match ($preset) {
            'this_period' => [$thisCycle->start->copy(), $thisCycle->start->copy()],
            'last_period' => [
                PayPeriod::forMonth($today->copy()->subMonthNoOverflow()->month, $today->copy()->subMonthNoOverflow()->year)->start,
                PayPeriod::forMonth($today->copy()->subMonthNoOverflow()->month, $today->copy()->subMonthNoOverflow()->year)->start,
            ],
            'last_three' => [$thisCycle->start->copy()->subMonthsNoOverflow(2), $thisCycle->start->copy()],
            'last_six' => [$thisCycle->start->copy()->subMonthsNoOverflow(5), $thisCycle->start->copy()],
            // April to March, the Indian financial year.
            'this_year' => [
                Carbon::create($today->month >= 4 ? $today->year : $today->year - 1, 4, 1)->startOfDay(),
                $thisCycle->start->copy(),
            ],
            'last_year' => [
                Carbon::create($today->month >= 4 ? $today->year - 1 : $today->year - 2, 4, 1)->startOfDay(),
                Carbon::create($today->month >= 4 ? $today->year : $today->year - 1, 3, 31)->startOfDay(),
            ],
            default => [
                Carbon::create($today->month >= 4 ? $today->year : $today->year - 1, 4, 1)->startOfDay(),
                $thisCycle->start->copy(),
            ],
        };

        return [$from, $to, $preset];
    }

    /** @return array<string, string> */
    protected function presetList(): array
    {
        return [
            'this_period' => 'This pay period',
            'last_period' => 'Last pay period',
            'last_three' => 'Last 3 periods',
            'last_six' => 'Last 6 periods',
            'this_year' => 'This financial year',
            'last_year' => 'Last financial year',
        ];
    }

    /**
     * Which pay periods the range actually caught, so the heading can say so.
     *
     * @return array<int, string>
     */
    protected function periodsCovered(array $rows): array
    {
        $periods = [];

        foreach ($rows as $row) {
            $periods[(string) $row['period_start']] = $row['period'];
        }

        ksort($periods);

        return array_values($periods);
    }

}
