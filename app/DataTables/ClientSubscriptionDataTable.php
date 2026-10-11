<?php

namespace App\DataTables;

use App\Models\RecurringInvoice;
use Yajra\DataTables\Html\Column;

class ClientSubscriptionDataTable extends BaseDataTable
{

    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->addIndexColumn()
            ->addColumn('status_badge', function ($row) {
                if ($row->status === 'active') {
                    return '<span class="badge badge-success">' . __('app.active') . '</span>';
                }

                return '<span class="badge badge-danger">' . __('app.inactive') . '</span>';
            })
            ->addColumn('amount_formatted', function ($row) {
                $symbol = $row->currency ? $row->currency->currency_symbol : '$';

                return $symbol . number_format($row->total, 2);
            })
            ->addColumn('rotation_label', function ($row) {
                return ucfirst($row->rotation);
            })
            ->addColumn('next_date', function ($row) {
                return $row->next_invoice_date
                    ? $row->next_invoice_date->timezone($this->company->timezone)->format($this->company->date_format)
                    : '--';
            })
            ->addColumn('action', function ($row) {
                $logsUrl   = route('client.subscriptions.logs', $row->id);
                $cancelBtn = '';

                if ($row->status === 'active' && $row->client_can_stop == 1) {
                    $cancelBtn = '<a href="javascript:;" class="dropdown-item btn-client-cancel-sub" data-id="' . $row->id . '">
                        <i class="fa fa-ban mr-2 text-danger"></i>' . __('app.cancelSubscription') . '</a>';
                }

                return '<div class="task_view">
                    <div class="dropdown">
                        <a class="task_view_more d-flex align-items-center justify-content-center dropdown-toggle" type="link"
                            id="dropdownMenuLink-' . $row->id . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="icon-options-vertical icons"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuLink-' . $row->id . '" tabindex="0">
                            <a href="javascript:;" class="dropdown-item btn-client-logs" data-url="' . $logsUrl . '">
                                <i class="fa fa-history mr-2"></i>' . __('app.paymentHistory') . '</a>
                            ' . $cancelBtn . '
                        </div>
                    </div>
                </div>';
            })
            ->rawColumns(['status_badge', 'action'])
            ->filterColumn('status_badge', function ($query, $keyword) {
                $query->where('invoice_recurring.status', $keyword);
            });
    }

    public function query(RecurringInvoice $model)
    {
        $request = $this->request();

        $query = $model->with(['currency'])
            ->where('invoice_recurring.company_id', $this->company->id)
            ->where('invoice_recurring.client_id', user()->id)
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
                'invoice_recurring.client_can_stop'
            );

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('invoice_recurring.status', $request->status);
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
        return $this->setBuilder('client-subscriptions-table', 0)
            ->parameters([
                'initComplete' => 'function () {
                    window.LaravelDataTables["client-subscriptions-table"].buttons().container()
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
            Column::make('status_badge')->title(__('app.status'))->orderable(false)->searchable(false),
            Column::make('amount_formatted')->title(__('app.amount'))->orderable(false)->searchable(false),
            Column::make('rotation_label')->title(__('app.frequency'))->orderable(false)->searchable(false),
            Column::make('next_date')->title(__('app.nextInvoiceDate'))->orderable(false)->searchable(false),
            Column::computed('action')->title(__('app.action'))
                ->exportable(false)->printable(false)->orderable(false)->searchable(false)
                ->addClass('text-right pr-20'),
        ];
    }

}
