<?php

namespace App\DataTables;

use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Button;

class consolidatedTaskReportDataTable extends BaseDataTable
{

    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)

            ->editColumn('heading', function ($row) {
                return $row->heading;
            })

            ->addColumn('estimate_hours', function ($row) {
                return $row->estimate_hours . ' ' . trans('app.hour') . ' ' . $row->total_minutes . ' ' . trans('app.minutes');
            })

            ->editColumn('spent_minutes', function ($row) {
                // Ensure spent_minutes is a valid number and within reasonable bounds
                $spentMinutes = (float) $row->spent_minutes;
                
                // Cap at a reasonable maximum (e.g., 1 million minutes = ~16,666 hours)
                if ($spentMinutes > 1000000 || $spentMinutes < 0) {
                    $spentMinutes = 0;
                }
                
                $hours = floor($spentMinutes / 60);
                $minutes = (int) ($spentMinutes % 60);

                return $hours . ' ' . __('app.hour') . ' ' . $minutes . ' ' . __('app.minutes');
            })

            ->setRowId(fn($row) => 'row-' . $row->id)
            ->rawColumns(['total_logged_hours']);

    }

    /**
     * Get the query source of dataTable.
     */
    public function query()
    {
        $request = $this->request();

        $startDate = null;
        $endDate = null;

        // Build date filters for subquery
        $timeLogQuery = DB::table('project_time_logs')
            ->leftJoin('project_time_log_breaks', 'project_time_logs.id', '=', 'project_time_log_breaks.project_time_log_id')
            ->select(
                'project_time_logs.task_id',
                DB::raw('GREATEST(0, IFNULL(SUM(CAST(project_time_logs.total_minutes AS SIGNED)), 0) - IFNULL(SUM(CAST(project_time_log_breaks.total_minutes AS SIGNED)), 0)) AS spent_minutes')
            )
            ->whereNotNull('project_time_logs.task_id')
            ->where('project_time_logs.total_minutes', '>=', 0)
            ->where('project_time_logs.total_minutes', '<=', 1000000)
            ->groupBy('project_time_logs.task_id');

        if ($request->startDate !== null && $request->startDate != 'null' && $request->startDate != '') {
            $startDate = companyToDateString($request->startDate);
            $timeLogQuery->where('project_time_logs.start_time', '>=', $startDate);
        }

        if ($request->endDate !== null && $request->endDate != 'null' && $request->endDate != '') {
            $endDate = companyToDateString($request->endDate);
            $timeLogQuery->where('project_time_logs.start_time', '<=', $endDate);
        }

        $model = Task::leftJoinSub($timeLogQuery, 'time_logs', function ($join) {
                $join->on('tasks.id', '=', 'time_logs.task_id');
            })
            ->select(
                'tasks.id',
                'tasks.heading',
                'tasks.estimate_hours',
                'tasks.estimate_minutes',
                DB::raw('IFNULL(time_logs.spent_minutes, 0) AS spent_minutes')
            );

        if ($request->project_id !== null && $request->project_id != 'null' && $request->project_id != 'all' && $request->project_id != '') {
            $model = $model->where('tasks.project_id', $request->project_id);
        }

        if ($request->assignedTo !== null && $request->assignedTo != 'null' && $request->assignedTo != 'all' && $request->assignedTo != '') {
            $model = $model->whereExists(function ($query) use ($request) {
                $query->select(DB::raw(1))
                    ->from('task_users')
                    ->whereColumn('task_users.task_id', 'tasks.id')
                    ->where('task_users.user_id', $request->assignedTo);
            });
        }
        $model = $model->groupBy('tasks.id', 'tasks.heading', 'tasks.estimate_hours');

        return $model;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html()
    {
        $dataTable = $this->setBuilder('consolidated-task-table')
            ->parameters([
                'initComplete' => 'function () {
                   window.LaravelDataTables["consolidated-task-table"].buttons().container()
                    .appendTo("#table-actions");

                    var colBtnParent = $(".buttons-colvis").parent();

                    $(".buttons-colvis").appendTo("#column-visibilty").removeClass("btn-secondary").addClass("p-0 f-13 mr-2 text-dark-grey");
                    colBtnParent.remove();

                }',
                'fnDrawCallback' => 'function( oSettings ) {
                    $("#consolidated-task-table .select-picker").selectpicker();
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
            __('app.task') => ['data' => 'heading', 'name' => 'heading', 'exportable' => true, 'title' => __('app.task')],
            __('modules.tasks.estimateHours') => ['data' => 'estimate_hours', 'name' => 'estimate_hours', 'title' => __('modules.tasks.estimateHours')],
            __('modules.tasks.totalHoursSpent') => ['data' => 'spent_minutes', 'name' => 'spent_minutes', 'title' => __('modules.tasks.totalHoursSpent')],
        ];

    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'EmployeeWiseTask_' . date('YmdHis');
    }

}
