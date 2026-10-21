<?php

namespace App\DataTables;

use App\Models\Asset;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;

class AssetsDataTable extends BaseDataTable
{

    public function dataTable($query)
    {
        $format = $this->company->date_format;

        return datatables()->eloquent($query)
            ->addColumn('asset', function ($row) {
                $sub = trim(implode(' · ', array_filter([trim($row->brand . ' ' . $row->model), $row->serial_number ? 'SN ' . $row->serial_number : null])));
                $img = $row->image_url
                    ? '<img src="' . $row->image_url . '" class="rounded mr-2" width="36" height="36" style="object-fit:cover" alt="">'
                    : '<span class="rounded mr-2 d-inline-flex align-items-center justify-content-center bg-light text-dark-grey" style="width:36px;height:36px"><i class="bi bi-laptop"></i></span>';

                return '<div class="d-flex align-items-center">' . $img . '<div>'
                    . '<a href="' . route('assets.show', $row->id) . '" class="openRightModal text-darkest-grey f-13 font-weight-500">' . e($row->name) . '</a>'
                    . '<br><span class="f-11 text-dark-grey">' . e($row->asset_code) . ($sub ? ' · ' . e($sub) : '') . '</span></div></div>';
            })
            ->addColumn('type', fn ($row) => e($row->type?->name ?? '--'))
            ->addColumn('branch', fn ($row) => e($row->branch?->branch_name ?? '--'))
            ->addColumn('status_badge', fn ($row) => $row->statusBadge())
            ->addColumn('held_by', function ($row) use ($format) {
                $current = $row->currentAssignment;

                if (!$current || !$current->user) {
                    return '--';
                }

                $overdue = $current->isOverdue() ? ' <span class="badge badge-danger">Overdue</span>' : '';

                return '<a href="' . route('employees.show', $current->user_id) . '?tab=assets" class="text-darkest-grey">' . e($current->user->name) . '</a>'
                    . '<br><span class="f-11 text-dark-grey">since ' . $current->assigned_on->format($format) . '</span>' . $overdue;
            })
            ->addColumn('warranty', function ($row) use ($format) {
                if (!$row->warranty_until) {
                    return '--';
                }

                $class = $row->warranty_until->isPast() ? 'text-danger' : ($row->warranty_until->lte(now()->addDays(30)) ? 'text-warning' : '');

                return '<span class="' . $class . '">' . $row->warranty_until->format($format) . '</span>';
            })
            ->addColumn('next_due', function ($row) {
                if (!$row->next_due_on) {
                    return '--';
                }

                return '<span class="f-12 text-dark-grey d-block">' . e($row->next_due_what) . '</span>' . Asset::dueBadge($row->next_due_on);
            })
            ->addColumn('export_next_due', fn ($row) => $row->next_due_on ? $row->next_due_what . ' ' . $row->next_due_on->format($format) : '')
            ->addColumn('action', function ($row) {
                $items = '<a class="dropdown-item openRightModal" href="' . route('assets.show', $row->id) . '"><i class="fa fa-eye mr-2"></i>View &amp; History</a>'
                    . '<a class="dropdown-item openRightModal" href="' . route('assets.edit', $row->id) . '"><i class="fa fa-edit mr-2"></i>Edit</a>';

                if ($row->status === 'available') {
                    $items .= '<a class="dropdown-item openRightModal" href="' . route('assets.assign-form', ['asset_id' => $row->id]) . '"><i class="fa fa-user-plus mr-2"></i>Assign</a>';
                }

                if ($row->status === 'assigned' && $row->currentAssignment) {
                    $items .= '<a class="dropdown-item openRightModal" href="' . route('assets.return-form', $row->currentAssignment->id) . '"><i class="fa fa-undo mr-2"></i>Take Return</a>'
                        . '<a class="dropdown-item" target="_blank" href="' . route('assets.handover', ['assignment' => $row->currentAssignment->id]) . '"><i class="fa fa-print mr-2"></i>Print Handover</a>';
                }

                if ($row->status !== 'disposed') {
                    $items .= '<a class="dropdown-item openRightModal" href="' . route('assets.status-form', $row->id) . '"><i class="fa fa-exchange-alt mr-2"></i>Change Status</a>';
                }

                if (!in_array($row->status, ['lost', 'disposed'], true)) {
                    $items .= '<a class="dropdown-item openRightModal" href="' . route('assets.service-form', $row->id) . '"><i class="fa fa-tools mr-2"></i>Log Service / Repair</a>'
                        . '<a class="dropdown-item openRightModal" href="' . route('assets.renewal-form', $row->id) . '"><i class="fa fa-file-signature mr-2"></i>Add Renewal</a>';
                }

                $items .= '<a class="dropdown-item delete-asset" href="javascript:;" data-asset-id="' . $row->id . '"><i class="fa fa-trash mr-2"></i>Delete</a>';

                return '<div class="task_view"><div class="dropdown dropdown-action">'
                    . '<a href="#" class="task_view_more d-flex align-items-center justify-content-center dropdown-toggle" data-toggle="dropdown"><i class="icon-options-vertical icons"></i></a>'
                    . '<div class="dropdown-menu dropdown-menu-right">' . $items . '</div></div></div>';
            })
            ->addColumn('export_held_by', fn ($row) => $row->currentAssignment?->user?->name ?? '')
            ->orderColumn('asset', 'name $1')
            ->rawColumns(['asset', 'status_badge', 'held_by', 'warranty', 'next_due', 'action']);
    }

    public function query(Asset $model)
    {
        $request = request();

        return $model->newQuery()
            ->with(['type', 'branch', 'currentAssignment.user'])
            ->select('assets.*')
            ->when($request->status && $request->status !== 'all', fn ($q) => $q->where('assets.status', $request->status))
            ->when($request->type && $request->type !== 'all', fn ($q) => $q->where('assets.asset_type_id', $request->type))
            ->when($request->branch && $request->branch !== 'all', fn ($q) => $q->where('assets.branch_id', $request->branch))
            ->when($request->holder && $request->holder !== 'all',
                fn ($q) => $q->whereHas('currentAssignment', fn ($a) => $a->where('user_id', $request->holder)))
            ->when($request->due === 'soon', fn ($q) => $q->whereBetween('assets.next_due_on',
                [now()->toDateString(), now()->addDays(Asset::DUE_SOON_DAYS)->toDateString()]))
            ->when($request->due === 'overdue', fn ($q) => $q->whereDate('assets.next_due_on', '<', now()->toDateString()))
            ->when($request->due === 'service', fn ($q) => $q->whereNotNull('assets.next_service_on')
                ->whereDate('assets.next_service_on', '<=', now()->addDays(Asset::DUE_SOON_DAYS)->toDateString())
                ->whereNotIn('assets.status', ['lost', 'disposed']))
            ->when($request->warranty === 'soon', fn ($q) => $q->whereNotIn('assets.status', ['lost', 'disposed'])
                ->whereBetween('assets.warranty_until', [now()->toDateString(), now()->addDays(30)->toDateString()]))
            ->when($request->warranty === 'expired', fn ($q) => $q->whereNotIn('assets.status', ['lost', 'disposed'])
                ->whereDate('assets.warranty_until', '<', now()->toDateString()))
            ->when($request->searchText, function ($q, $s) {
                $q->where(fn ($w) => $w->where('assets.name', 'like', "%$s%")
                    ->orWhere('assets.asset_code', 'like', "%$s%")
                    ->orWhere('assets.serial_number', 'like', "%$s%")
                    ->orWhere('assets.brand', 'like', "%$s%")
                    ->orWhere('assets.model', 'like', "%$s%")
                    ->orWhereHas('currentAssignment.user', fn ($u) => $u->where('name', 'like', "%$s%")));
            });
    }

    public function html()
    {
        $dataTable = $this->setBuilder('assets-table', 0);
        $dataTable->stateSave(false);

        if (canDataTableExport()) {
            $dataTable->buttons(Button::make(['extend' => 'excel', 'text' => '<i class="fa fa-file-export"></i> ' . trans('app.exportExcel')]));
        }

        return $dataTable;
    }

    protected function getColumns()
    {
        return [
            Column::make('asset')->name('name')->title('Asset'),
            Column::make('type')->title('Type')->orderable(false),
            Column::make('branch')->title('Branch')->orderable(false),
            Column::make('status_badge')->name('status')->title('Status'),
            Column::make('held_by')->title('Held By')->orderable(false)->exportable(false),
            Column::make('export_held_by')->title('Held By')->visible(false)->printable(false)->orderable(false),
            Column::make('warranty')->name('warranty_until')->title('Warranty Until'),
            Column::make('next_due')->name('next_due_on')->title('Next Due')->exportable(false),
            Column::make('export_next_due')->title('Next Due')->visible(false)->printable(false)->orderable(false),
            Column::make('action')->title('Action')->width(80)->exportable(false)->orderable(false)->addClass('text-right pr-20'),
        ];
    }

}
