<?php

namespace App\DataTables;

use App\Models\Resignation;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;

class ResignationDataTable extends BaseDataTable
{
    public function dataTable($query)
    {
        $datatables = datatables()->eloquent($query);

        $datatables->addColumn('employee', function ($row) {
            $img = $row->image
                ? '<img src="' . asset_url('avatar/' . $row->image) . '" class="rounded-circle" width="32" height="32" alt="">'
                : '<img src="' . asset('img/gravatar.png') . '" class="rounded-circle" width="32" height="32" alt="">';

            return '<div class="d-flex align-items-center">
                        <div class="mr-2">' . $img . '</div>
                        <div>
                            <a href="' . route('employees.show', $row->user_id) . '" class="text-darkest-grey f-13 font-weight-500">'
                                . e($row->name) .
                            '</a>
                            <br><span class="f-11 text-dark-grey">' . e($row->employee_id ?? '') . '</span>
                        </div>
                    </div>';
        });

        $datatables->addColumn('department', fn($row) => e($row->department_name ?? '--'));

        $datatables->addColumn('designation', fn($row) => e($row->designation_name ?? '--'));

        $datatables->addColumn('resignation_date', function ($row) {
            return $row->resignation_date ? \Carbon\Carbon::parse($row->resignation_date)->format($this->company->date_format) : '--';
        });

        $datatables->addColumn('last_working_date', function ($row) {
            return $row->last_working_date ? \Carbon\Carbon::parse($row->last_working_date)->format($this->company->date_format) : '--';
        });

        $datatables->addColumn('notice_period', fn($row) => $row->notice_period_days !== null ? $row->notice_period_days . ' Days' : '--');

        $datatables->addColumn('status_badge', function ($row) {
            return match ($row->effective_status) {
                'approved'      => '<span class="badge badge-success px-2 py-1">Approved</span>',
                'rejected'      => '<span class="badge badge-danger px-2 py-1">Rejected</span>',
                'notice_period' => '<span class="badge badge-info px-2 py-1">Notice Period</span>',
                'completed'     => '<span class="badge badge-secondary px-2 py-1">Off Boarded</span>',
                default         => '<span class="badge badge-warning px-2 py-1">Pending</span>',
            };
        });

        $datatables->addColumn('action', function ($row) {
            $html = '<div class="d-flex" style="gap:5px;">';
            $html .= '<a href="' . route('resignation-management.show', $row->id) . '" class="btn btn-xs btn-outline-secondary openRightModal">View</a>';

            if ($row->effective_status === 'pending') {
                $html .= '<a href="#" class="btn btn-xs btn-outline-success" onclick="approveResignation(' . $row->id . '); return false;">Approve</a>';
                $html .= '<a href="#" class="btn btn-xs btn-outline-danger" onclick="rejectResignation(' . $row->id . '); return false;">Reject</a>';
            } elseif (in_array($row->status, ['approved', 'rejected'])) {
                $html .= '<a href="#" class="btn btn-xs btn-outline-warning" onclick="undoResignation(' . $row->id . '); return false;">Undo</a>';
            }

            $html .= '</div>';
            return $html;
        });

        $datatables->rawColumns(['employee', 'status_badge', 'action']);
        $datatables->setRowId('id');

        return $datatables;
    }

    public function query()
    {
        $request = request();
        $statusSql = Resignation::effectiveStatusSql();

        $query = Resignation::query()
            ->join('users', 'users.id', '=', 'resignations.user_id')
            ->leftJoin('employee_details', 'employee_details.user_id', '=', 'resignations.user_id')
            ->leftJoin('designations', 'designations.id', '=', 'employee_details.designation_id')
            ->leftJoin('teams', 'teams.id', '=', 'employee_details.department_id')
            ->where('resignations.company_id', company()->id)
            ->select([
                'resignations.id',
                'resignations.user_id',
                'resignations.status',
                'resignations.reason',
                'resignations.resignation_date',
                'resignations.notice_period_days',
                'resignations.last_working_date',
                'users.name',
                'users.image',
                'users.email',
                'employee_details.employee_id',
                'designations.name as designation_name',
                'teams.team_name as department_name',
                'employee_details.department_id',
                'employee_details.designation_id',
            ])
            ->selectRaw("{$statusSql} as effective_status");

        if ($request->status === 'approved') {
            // "Approved" is a rollup independent of the notice_period/completed
            // split that effective_status derives, so it needs the raw column.
            $query->where('resignations.status', 'approved');
        } elseif ($request->status && $request->status !== 'all') {
            $query->whereRaw("{$statusSql} = ?", [$request->status]);
        }

        if ($request->department && $request->department !== 'all') {
            $query->where('employee_details.department_id', $request->department);
        }

        if ($request->designation && $request->designation !== 'all') {
            $query->where('employee_details.designation_id', $request->designation);
        }

        if ($request->searchText) {
            $query->where(function ($q) use ($request) {
                $q->where('users.name', 'like', '%' . $request->searchText . '%')
                  ->orWhere('employee_details.employee_id', 'like', '%' . $request->searchText . '%')
                  ->orWhere('users.email', 'like', '%' . $request->searchText . '%');
            });
        }

        return $query;
    }

    public function html()
    {
        $dataTable = $this->setBuilder('resignation-management-table', 4);

        // Same class of layout-vs-stale-localStorage issue we already hit on the
        // Employee Exit table — start every load clean.
        $dataTable->stateSave(false);

        if (canDataTableExport()) {
            $dataTable->buttons(Button::make(['extend' => 'excel', 'text' => '<i class="fa fa-file-export"></i> ' . trans('app.exportExcel')]));
        }

        return $dataTable;
    }

    protected function getColumns()
    {
        return [
            Column::make('employee')->title('Employee'),
            Column::make('department')->title('Department'),
            Column::make('designation')->title('Designation'),
            Column::make('resignation_date')->title('Resignation Date'),
            Column::make('last_working_date')->title('Last Working Date'),
            Column::make('notice_period')->title('Notice Period')->orderable(false)->searchable(false),
            Column::make('status_badge')->title('Status'),
            Column::make('action')->title('Action')->width(160)->exportable(false)->searchable(false)->orderable(false),
        ];
    }
}
