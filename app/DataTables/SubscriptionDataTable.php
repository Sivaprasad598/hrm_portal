<?php

namespace App\DataTables;

use App\Models\RecurringInvoice;
use Yajra\DataTables\Html\Column;

class SubscriptionDataTable extends BaseDataTable
{

    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->addIndexColumn()
            ->addColumn('client_name', function ($row) {
                if (!$row->client) {
                    return '--';
                }

                $img = $row->client->image
                    ? '<img src="' . asset_url_local_s3('user-uploads/avatar/' . $row->client->image) . '" class="rounded-circle mr-2" width="28" height="28" alt="">'
                    : '<span class="rounded-circle bg-additional-grey mr-2 d-inline-flex align-items-center justify-content-center" style="width:28px;height:28px;vertical-align:middle;"><i class="fa fa-user text-lightest f-11"></i></span>';

                return '<div class="d-flex align-items-center">' . $img . '<span class="f-14 text-dark-grey">' . e($row->client->name) . '</span></div>';
            })
            ->addColumn('status_badge', function ($row) {
                if ($row->status === 'active') {
                    return '<span class="badge badge-success sub-status-badge" data-id="' . $row->id . '">' . __('app.active') . '</span>';
                }

                return '<span class="badge badge-danger sub-status-badge" data-id="' . $row->id . '">' . __('app.inactive') . '</span>';
            })
            ->addColumn('amount_formatted', function ($row) {
                $symbol = $row->currency ? $row->currency->currency_symbol : '$';

                return $symbol . number_format($row->total, 2);
            })
            ->addColumn('rotation_label', function ($row) {
                return ucfirst($row->rotation);
            })
            ->addColumn('failed_badge', function ($row) {
                $fails = $row->failed_payment_attempts ?? 0;

                if ($fails > 0) {
                    return '<span class="badge badge-warning">' . $fails . '</span>';
                }

                return '<span class="text-muted">0</span>';
            })
            ->addColumn('next_date', function ($row) {
                return $row->next_invoice_date
                    ? $row->next_invoice_date->timezone($this->company->timezone)->format($this->company->date_format)
                    : '--';
            })
            ->addColumn('action', function ($row) {
                $logsUrl       = route('subscriptions.logs', $row->id);
                $actionBtn     = '';

                if ($row->status === 'active') {
                    $actionBtn = '<a href="javascript:;" class="dropdown-item btn-cancel-subscription" data-id="' . $row->id . '">
                        <i class="fa fa-ban mr-2 text-danger"></i>' . __('app.cancelSubscription') . '</a>';
                } else {
                    $actionBtn = '<a href="javascript:;" class="dropdown-item btn-reactivate-subscription" data-id="' . $row->id . '">
                        <i class="fa fa-check-circle mr-2 text-success"></i>' . __('app.reactivateSubscription') . '</a>';
                }

                return '<div class="task_view">
                    <div class="dropdown">
                        <a class="task_view_more d-flex align-items-center justify-content-center dropdown-toggle" type="link"
                            id="dropdownMenuLink-' . $row->id . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="icon-options-vertical icons"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuLink-' . $row->id . '" tabindex="0">
                            <a href="javascript:;" class="dropdown-item btn-view-logs" data-url="' . $logsUrl . '">
                                <i class="fa fa-history mr-2"></i>' . __('app.paymentLogs') . '</a>
                            ' . $actionBtn . '
                        </div>
                    </div>
                </div>';
            })
            ->rawColumns(['client_name', 'status_badge', 'failed_badge', 'action'])
            ->orderColumn('client_name', function ($query, $order) {
                $query->orderBy('users.name', $order);
            });
    }

    public function query(RecurringInvoice $model)
    {
        $request   = $this->request();
        $companyId = $this->company->id;

        $query = $model->with(['client', 'currency'])
            ->leftJoin('users', 'users.id', '=', 'invoice_recurring.client_id')
            ->where('invoice_recurring.company_id', $companyId)
            ->where('invoice_recurring.payfast_sub_status', 1)
            ->select(
                'invoice_recurring.id',
                'invoice_recurring.client_id',
                'invoice_recurring.currency_id',
                'invoice_recurring.total',
                'invoice_recurring.status',
                'invoice_recurring.rotation',
                'invoice_recurring.next_invoice_date',
                'invoice_recurring.failed_payment_attempts',
                'users.name as client_name_sort'
            );

        // Filters sent from the filter bar
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('invoice_recurring.status', $request->status);
        }

        if ($request->filled('client_id') && $request->client_id !== 'all') {
            $query->where('invoice_recurring.client_id', $request->client_id);
        }

        if ($request->filled('rotation') && $request->rotation !== 'all') {
            $query->where('invoice_recurring.rotation', $request->rotation);
        }

        if ($request->filled('startDate') && $request->startDate !== 'null') {
            $query->whereDate('invoice_recurring.next_invoice_date', '>=', companyToDateString($request->startDate));
        }

        if ($request->filled('endDate') && $request->endDate !== 'null') {
            $query->whereDate('invoice_recurring.next_invoice_date', '<=', companyToDateString($request->endDate));
        }

        return $query;
    }

    public function html()
    {
        return $this->setBuilder('subscriptions-table', 0)
            ->parameters([
                'initComplete' => 'function () {
                    window.LaravelDataTables["subscriptions-table"].buttons().container()
                        .appendTo("#table-actions");
                }',
                'fnDrawCallback' => 'function (oSettings) {
                    $("[data-toggle=\"tooltip\"]").tooltip();
                }',
            ]);
    }

    protected function getColumns(): array
    {
        return [
            Column::make('DT_RowIndex')->title('#')->orderable(false)->searchable(false)->width(40),
            Column::make('client_name')->title(__('app.client'))->orderable(true),
            Column::make('status_badge')->title(__('app.status'))->orderable(false)->searchable(false),
            Column::make('amount_formatted')->title(__('app.amount'))->orderable(false)->searchable(false),
            Column::make('rotation_label')->title(__('app.frequency'))->orderable(false)->searchable(false),
            Column::make('failed_badge')->title(__('app.failedAttempts'))->orderable(false)->searchable(false),
            Column::make('next_date')->title(__('app.nextInvoiceDate'))->orderable(false)->searchable(false),
            Column::computed('action')->title(__('app.action'))
                ->exportable(false)->printable(false)->orderable(false)->searchable(false)
                ->addClass('text-right pr-20'),
        ];
    }

}
