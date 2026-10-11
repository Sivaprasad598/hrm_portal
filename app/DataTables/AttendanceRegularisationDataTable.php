<?php

namespace App\DataTables;

use App\Models\AttendanceRegularisation;
use App\Models\AttendanceSetting;
use App\Models\CustomFieldGroup;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class AttendanceRegularisationDataTable extends BaseDataTable
{
    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return \Yajra\DataTables\DataTableAbstract
     */
    public function dataTable($query)
    {
        $attendanceRegularisation = AttendanceSetting::first();
        $regulariseRoles = $attendanceRegularisation->attendance_regularize_roles;
        $regulariseRolesArray = json_decode($regulariseRoles, true) ?? [];
        $userRoles = user_roles();

        $lastRole = end($userRoles);
        $condition = in_array($lastRole, $regulariseRolesArray) || $lastRole === 'admin';

        return datatables()
            ->eloquent($query)

            ->addColumn('action', function ($row) use ($condition) {
                $action = '<div class="task_view">
                    <div class="dropdown">
                        <a class="task_view_more d-flex align-items-center justify-content-center dropdown-toggle" type="link"
                            id="dropdownMenuLink-' . $row->id . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="icon-options-vertical icons"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuLink-' . $row->id . '" tabindex="0">';

                // View button - always available
                $action .= '<a data-view-id=' . $row->id . ' class="dropdown-item view-attendance-regularisation" href="javascript:;">
                        <i class="mr-2 fa fa-eye"></i>' . trans('app.view') . '
                    </a>';

                if ($row->status == 'pending' && ($condition || in_array('admin', user_roles()))) {
                    // Accept button
                    $action .= '<a data-accept-id=' . $row->id . ' class="dropdown-item accept-action" href="javascript:;">
                                    <i class="fa fa-check mr-2"></i>' . __('app.accept') . '
                                </a>';

                    // Reject button
                    $action .= '<a data-reject-id=' . $row->id . ' class="dropdown-item reject-action" href="javascript:;">
                                    <i class="fa fa-times mr-2"></i>' . __('app.reject') . '
                                </a>';

                    // Edit button
                    $action .= '<a data-edit-id=' . $row->id . ' class="dropdown-item edit-attendance-regularisation" href="javascript:;">
                            <i class="mr-2 fa fa-edit"></i>' . trans('app.edit') . '
                        </a>';
                }

                $action .= '</div>
                    </div>
                </div>';

                return $action;
            })

        ->addColumn('employee_name', function ($row) {
            return $row->name;
        })

        ->addColumn('date', function ($row) {
            return Carbon::parse($row->date)->translatedFormat($this->company->date_format);
        })

        ->addColumn('clock_in_time', function ($row) {
            return Carbon::parse($row->clock_in_time)->timezone($this->company->timezone)->translatedFormat($this->company->date_format . ' ' . $this->company->time_format);

        })

        ->addColumn('clock_out_time', function ($row) {
            if (is_null($row->clock_out_time)) {
                return ' -- ';
            }
            return Carbon::parse($row->clock_out_time)->timezone($this->company->timezone)->translatedFormat($this->company->date_format . ' ' . $this->company->time_format);
        })

        ->addColumn('working_from', function ($row) {
            return ucfirst($row->working_from);
        })

        ->addColumn('status', function ($row) {
            if ($row->status == 'accept') {
                $class = 'text-light-green';
                $status = __('app.accept');
            }
            else if ($row->status == 'pending') {
                $class = 'text-yellow';
                $status = __('app.pending');
            }
            else {
                $class = 'text-red';
                $status = __('app.reject');
            }

            $regulariseStatus = '<i class="fa fa-circle mr-1 ' . $class . ' f-10"></i> ' . $status;

            return $regulariseStatus;
        })

        ->setRowId(fn($row) => 'row-' . $row->id)
        ->rawColumns(['action', 'name', 'employee_name', 'date', 'clock_in_time', 'clock_out_time', 'working_from', 'status']);

    }


    public function query(AttendanceRegularisation $model)
    {
        $request = $this->request();

        $model = $model
        ->select([
            'attendance_regularisations.id',
            'users.name as name',
            'attendance_regularisations.date',
            'attendance_regularisations.clock_in_time',
            'attendance_regularisations.clock_out_time',
            'attendance_regularisations.working_from',
            'attendance_regularisations.status',
        ])
        ->join('users', 'attendance_regularisations.user_id', '=', 'users.id');

        if ($request->employee != 'all' && $request->employee != '') {
            $model = $model->where('users.id', $request->employee);
        }

        if ($request->status != 'all' && $request->status != '') {
            $model = $model->where('attendance_regularisations.status', $request->status);
        }

        if ($request->month != 'all' && $request->month != '') {
            $model = $model->whereMonth('attendance_regularisations.date', $request->month);
        }

        if ($request->year != 'all' && $request->year != '') {
            $model = $model->whereYear('attendance_regularisations.date', $request->year);
        }

        return $model;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html()
    {
        $dataTable = $this->setBuilder('attendanceregularisation-table')
            ->parameters([
                'initComplete' => 'function () {
                   window.LaravelDataTables["attendanceregularisation-table"].buttons().container()
                    .appendTo("#table-actions");
                }',
                'fnDrawCallback' => 'function( oSettings ) {
                    $("#attendanceregularisation-table .select-picker").selectpicker();
                }',
                'columnDefs' => [
                    [
                        'targets' => 1,
                        'className' => 'noVis'
                    ]
                ]
            ]);

        if (canDataTableExport()) {
            $dataTable->buttons(Button::make(['extend' => 'excel', 'text' => '<i class="fa fa-file-export"></i> ' . trans('app.exportExcel')]));
        }

        return $dataTable;

    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            '#' => ['data' => 'id', 'name' => 'id', 'visible' => false, 'class' => 'noVis'],
            __('modules.employees.employeeName') => ['data' => 'name', 'name' => 'name', 'visible' => true, 'exportable' => false, 'title' => __('modules.employees.employeeName')],
            __('app.name') => ['data' => 'employee_name', 'name' => 'employee_name', 'visible' => false, 'title' => __('app.name')],
            __('app.date') => ['data' => 'date', 'name' => 'attendance_regularisations.date', 'title' => __('app.date')],
            __('modules.attendance.clock_in') => ['data' => 'clock_in_time', 'name' => 'attendance_regularisations.clock_in_time', 'title' => __('modules.attendance.clock_in')],
            __('modules.attendance.clock_out') => ['data' => 'clock_out_time', 'name' => 'attendance_regularisations.clock_out_time', 'title' => __('modules.attendance.clock_out')],
            __('modules.attendance.working_from') => ['data' => 'working_from', 'name' => 'attendance_regularisations.working_from', 'title' => __('modules.attendance.working_from')],
            __('app.status') => ['data' => 'status', 'name' => 'status', 'title' => __('app.status')],
            Column::computed('action', __('app.action'))
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(50)
                ->addClass('text-right pr-20')
        ];

    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'AttendanceRegularisation_' . date('YmdHis');
    }
}
