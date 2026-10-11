<?php

namespace App\Http\Controllers;

use App\DataTables\FinanceReportDataTable;
use App\Helper\Reply;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceReportController extends AccountBaseController
{
    private function amountInCompanyCurrency(?int $currencyId, ?float $amount, $exchangeRate, $defaultCurrencyId): float
    {
        if (is_null($amount)) {
            return 0.0;
        }

        $amount = floatval($amount);

        if ((is_null($defaultCurrencyId) && is_null($exchangeRate)) ||
            (!is_null($defaultCurrencyId) && Company()->currency_id != $defaultCurrencyId)) {
            $currency = Currency::find($currencyId);
            $rate = $currency?->exchange_rate ?? 0;
        }
        else {
            $rate = floatval($exchangeRate);
        }

        if (!is_null($currencyId) && $currencyId != $this->company->currency_id && $rate != 0) {
            return $amount * $rate;
        }

        return $amount;
    }

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.financeReport';
    }

    public function index(FinanceReportDataTable $dataTable)
    {
        abort_403(user()->permission('view_finance_report') != 'all');

        $this->fromDate = now($this->company->timezone)->startOfMonth();
        $this->toDate = now($this->company->timezone);
        $this->currencies = Currency::all();
        $this->currentCurrencyId = $this->company->currency_id;

        $this->projects = Project::allProjects();
        $this->clients = User::allClients();

        return $dataTable->render('reports.finance.index', $this->data);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function financeChartData(Request $request)
    {
        $startDate = now($this->company->timezone)->startOfMonth()->toDateString();
        $endDate = now($this->company->timezone)->toDateString();
        $status = $request->status ?? 'complete';

        $payments = Payment::join('currencies', 'currencies.id', '=', 'payments.currency_id')
            ->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->leftJoin('projects', 'projects.id', '=', 'payments.project_id')
            ->when($status !== 'all' && $status !== '' && !is_null($status), function ($q) use ($status) {
                return $q->where('payments.status', $status);
            });

        if ($request->startDate !== null && $request->startDate != 'null' && $request->startDate != '') {
            $startDate = companyToDateString($request->startDate);
        }

        $payments = $payments->where(DB::raw('DATE(COALESCE(payments.`paid_on`, payments.`created_at`))'), '>=', $startDate);

        if ($request->endDate !== null && $request->endDate != 'null' && $request->endDate != '') {
            $endDate = companyToDateString($request->endDate);
        }

        $payments = $payments->where(DB::raw('DATE(COALESCE(payments.`paid_on`, payments.`created_at`))'), '<=', $endDate);

        if ($request->projectID != 'all' && !is_null($request->projectID)) {
            $payments = $payments->where('payments.project_id', '=', $request->projectID);
        }

        if ($request->clientID != 'all' && !is_null($request->clientID)) {
            $clientId = $request->clientID;
            $payments = $payments->where(function ($query) use ($clientId) {
                $query->where('projects.client_id', $clientId)
                    ->orWhere('invoices.client_id', $clientId);
            });
        }

        $payments = $payments->orderBy(DB::raw('COALESCE(paid_on, payments.`created_at`)'), 'ASC')
            ->get([
                DB::raw('DATE_FORMAT(COALESCE(paid_on, payments.`created_at`),"%d-%M-%y") as date'),
                DB::raw('YEAR(COALESCE(paid_on, payments.`created_at`)) year, MONTH(COALESCE(paid_on, payments.`created_at`)) month'),
                DB::raw('amount as total'),
                'currencies.id as currency_id',
                'payments.invoice_id',
                'payments.project_id',
                'payments.exchange_rate',
                'payments.default_currency_id'
            ]);

        $incomes = array();

        foreach ($payments as $invoice) {
            if (!isset($incomes[$invoice->date])) {
                $incomes[$invoice->date] = 0;
            }

            $incomes[$invoice->date] += $this->amountInCompanyCurrency(
                intval($invoice->currency_id),
                floatval($invoice->total),
                $invoice->exchange_rate,
                $invoice->default_currency_id
            );
        }

        $dates = array_keys($incomes);

        $graphData = array();

        foreach ($dates as $date) {
            $graphData[] = [
                'date' => $date,
                'total' => isset($incomes[$date]) ? round($incomes[$date], 2) : 0,
            ];
        }

        usort($graphData, function ($a, $b) {
            $t1 = strtotime($a['date']);
            $t2 = strtotime($b['date']);
            return $t1 - $t2;
        });

        // return $graphData;
        $graphData = collect($graphData);

        $data['labels'] = $graphData->pluck('date')->toArray();
        $data['values'] = $graphData->pluck('total')->toArray();
        $totalEarning = $graphData->sum('total');
        $data['colors'] = [$this->appTheme->header_color];
        $data['name'] = __('modules.dashboard.totalEarnings');

        $uniqueInvoiceCount = $payments->pluck('invoice_id')->filter()->unique()->count();
        $paidDays = max(1, $graphData->count());
        $avgDailyReceipt = round(($totalEarning / $paidDays), 2);

        $pendingQuery = Payment::join('currencies', 'currencies.id', '=', 'payments.currency_id')
            ->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->leftJoin('projects', 'projects.id', '=', 'payments.project_id')
            ->where('payments.status', 'pending')
            ->where(DB::raw('DATE(COALESCE(payments.`paid_on`, payments.`created_at`))'), '>=', $startDate)
            ->where(DB::raw('DATE(COALESCE(payments.`paid_on`, payments.`created_at`))'), '<=', $endDate);

        if ($request->projectID != 'all' && !is_null($request->projectID)) {
            $pendingQuery = $pendingQuery->where('payments.project_id', '=', $request->projectID);
        }

        if ($request->clientID != 'all' && !is_null($request->clientID)) {
            $clientId = $request->clientID;
            $pendingQuery = $pendingQuery->where(function ($query) use ($clientId) {
                $query->where('projects.client_id', $clientId)
                    ->orWhere('invoices.client_id', $clientId);
            });
        }

        $pendingPayments = $pendingQuery->get([
            'payments.amount as total',
            'currencies.id as currency_id',
            'payments.exchange_rate',
            'payments.default_currency_id'
        ]);

        $pendingTotalDefault = 0;

        foreach ($pendingPayments as $pendingPayment) {
            $pendingTotalDefault += $this->amountInCompanyCurrency(
                intval($pendingPayment->currency_id),
                floatval($pendingPayment->total),
                $pendingPayment->exchange_rate,
                $pendingPayment->default_currency_id
            );
        }

        // --- By Project (doughnut) ---
        $projectAgg = [];
        $projectLabels = [];

        if ($payments->count() > 0) {
            $projectIds = $payments->pluck('project_id')->filter()->unique()->values()->all();
            $projectNames = Project::whereIn('id', $projectIds)->pluck('project_name', 'id');

            foreach ($payments as $row) {
                $pid = $row->project_id;
                if (is_null($pid)) {
                    continue;
                }
                if (!isset($projectAgg[$pid])) {
                    $projectAgg[$pid] = 0;
                }

                $projectAgg[$pid] += $this->amountInCompanyCurrency(
                    intval($row->currency_id),
                    floatval($row->total),
                    $row->exchange_rate,
                    $row->default_currency_id
                );
            }
        }

        arsort($projectAgg);
        $projectAgg = array_slice($projectAgg, 0, 6, true);
        $projectLabels = array_map(function ($pid) use ($projectNames) {
            return $projectNames[$pid] ?? __('app.na');
        }, array_keys($projectAgg));
        $projectValues = array_values(array_map(fn($v) => round($v, 2), $projectAgg));

        $palette = ['#22c55e', '#3b82f6', '#f59e0b', '#8b5cf6', '#ef4444', '#14b8a6'];

        // --- Monthly comparison (current year vs previous year) ---
        $monthNames = [__('app.months.jan'), __('app.months.feb'), __('app.months.mar'), __('app.months.apr'), __('app.months.may'), __('app.months.jun'), __('app.months.jul'), __('app.months.aug'), __('app.months.sep'), __('app.months.oct'), __('app.months.nov'), __('app.months.dec')];
        $endYear = intval(date('Y', strtotime($endDate)));
        $prevYear = $endYear - 1;

        $monthly = [
            $endYear => array_fill(1, 12, 0),
            $prevYear => array_fill(1, 12, 0),
        ];

        foreach ($payments as $row) {
            $y = intval($row->year);
            $m = intval($row->month);
            if (!isset($monthly[$y]) || $m < 1 || $m > 12) {
                continue;
            }

            $monthly[$y][$m] += $this->amountInCompanyCurrency(
                intval($row->currency_id),
                floatval($row->total),
                $row->exchange_rate,
                $row->default_currency_id
            );
        }

        $currentYearValues = array_values(array_map(fn($v) => round($v, 2), $monthly[$endYear]));
        $previousYearValues = array_values(array_map(fn($v) => round($v, 2), $monthly[$prevYear]));

        // --- Top clients ---
        $clientAgg = [];

        $clientRows = Payment::leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->leftJoin('projects', 'projects.id', '=', 'payments.project_id')
            ->leftJoin('currencies', 'currencies.id', '=', 'payments.currency_id')
            ->leftJoin('users as clients', 'clients.id', '=', DB::raw('COALESCE(projects.client_id, invoices.client_id)'))
            ->when($status !== 'all' && $status !== '' && !is_null($status), function ($q) use ($status) {
                return $q->where('payments.status', $status);
            })
            ->where(DB::raw('DATE(COALESCE(payments.`paid_on`, payments.`created_at`))'), '>=', $startDate)
            ->where(DB::raw('DATE(COALESCE(payments.`paid_on`, payments.`created_at`))'), '<=', $endDate);

        if ($request->projectID != 'all' && !is_null($request->projectID)) {
            $clientRows = $clientRows->where('payments.project_id', '=', $request->projectID);
        }

        if ($request->clientID != 'all' && !is_null($request->clientID)) {
            $clientId = $request->clientID;
            $clientRows = $clientRows->where(function ($query) use ($clientId) {
                $query->where('projects.client_id', $clientId)
                    ->orWhere('invoices.client_id', $clientId);
            });
        }

        $clientRows = $clientRows->get([
            'clients.id as client_id',
            'clients.name as client_name',
            DB::raw('payments.amount as total'),
            'currencies.id as currency_id',
            'payments.exchange_rate',
            'payments.default_currency_id'
        ]);

        foreach ($clientRows as $row) {
            if (is_null($row->client_id)) {
                continue;
            }

            if (!isset($clientAgg[$row->client_id])) {
                $clientAgg[$row->client_id] = [
                    'name' => $row->client_name ?? __('app.na'),
                    'total' => 0,
                ];
            }

            $clientAgg[$row->client_id]['total'] += $this->amountInCompanyCurrency(
                intval($row->currency_id),
                floatval($row->total),
                $row->exchange_rate,
                $row->default_currency_id
            );
        }

        uasort($clientAgg, fn($a, $b) => $b['total'] <=> $a['total']);
        $clientAgg = array_slice($clientAgg, 0, 7, true);
        $topClientLabels = array_values(array_map(fn($r) => $r['name'], $clientAgg));
        $topClientValues = array_values(array_map(fn($r) => round($r['total'], 2), $clientAgg));

        $this->chartData = $data;
        $html = view('reports.timelogs.chart', $this->data)->render();
        return Reply::dataOnly([
            'status' => 'success',
            'html' => $html,
            'title' => $this->pageTitle,
            'totalEarnings' => currency_format($totalEarning, company()->currency_id),
            'totalInvoices' => $uniqueInvoiceCount,
            'pendingAmount' => currency_format($pendingTotalDefault, company()->currency_id),
            'pendingCount' => $pendingPayments->count(),
            'avgDailyReceipt' => currency_format($avgDailyReceipt, company()->currency_id),
            'paidDays' => $paidDays,
            'charts' => [
                'trend' => [
                    'labels' => $data['labels'],
                    'values' => $data['values'],
                ],
                'byProject' => [
                    'labels' => $projectLabels,
                    'values' => $projectValues,
                    'colors' => array_slice($palette, 0, count($projectValues)),
                ],
                'monthlyComparison' => [
                    'labels' => $monthNames,
                    'currentYearLabel' => (string) $endYear,
                    'currentYearValues' => $currentYearValues,
                    'previousYearLabel' => (string) $prevYear,
                    'previousYearValues' => $previousYearValues,
                ],
                'topClients' => [
                    'labels' => $topClientLabels,
                    'values' => $topClientValues,
                ],
            ],
        ]);
    }

}
