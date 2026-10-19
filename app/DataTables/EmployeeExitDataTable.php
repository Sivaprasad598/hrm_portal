<?php

namespace App\DataTables;

use App\Models\EmployeeDetails;
use App\Models\EmployeeExit;
use App\Models\User;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;

class EmployeeExitDataTable extends BaseDataTable
{
    private $viewPermission;

    public function __construct()
    {
        parent::__construct();
        $this->viewPermission = user()->permission('view_employees');
    }

    public function dataTable($query)
    {
        $datatables = datatables()->eloquent($query);

        $datatables->addColumn('check', fn($row) => $this->checkBox($row));

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

        $datatables->addColumn('status_badge', function ($row) {
            return match ($row->effective_status) {
                'probation'     => '<span class="badge badge-warning px-2 py-1">Probation</span>',
                'notice_period' => '<span class="badge badge-info px-2 py-1">Notice Period</span>',
                'off_boarded'   => '<span class="badge badge-danger px-2 py-1">Off Boarded</span>',
                default         => '<span class="badge badge-success px-2 py-1">Confirmed</span>',
            };
        });

        $datatables->addColumn('last_working_date', function ($row) {
            if ($row->exit_last_working_date) {
                return \Carbon\Carbon::parse($row->exit_last_working_date)->format($this->company->date_format);
            }
            if ($row->last_date) {
                return \Carbon\Carbon::parse($row->last_date)->format($this->company->date_format);
            }
            return '--';
        });

        $datatables->addColumn('exit_type', function ($row) {
            if (!$row->exit_type) {
                return '--';
            }
            return ucfirst(str_replace('_', ' ', $row->exit_type));
        });

        $datatables->addColumn('action', function ($row) {
            $status = $row->effective_status;
            $exitId = $row->exit_id;
            $userId = $row->user_id;

            $html = '<div class="task_view"><div class="dropdown dropdown-action">
                        <a href="#" class="task_view_more d-flex align-items-center justify-content-center dropdown-toggle" data-toggle="dropdown">
                            <i class="icon-settings"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">';

            if ($status === 'confirmed' || $status === 'probation') {
                $html .= '<a class="dropdown-item openRightModal" href="' . route('employee-exit.initiate', $userId) . '">
                            <i class="fa fa-sign-out-alt mr-2"></i> Initiate Exit
                          </a>';
            }

            if ($status === 'probation') {
                $html .= '<a class="dropdown-item" href="#" onclick="confirmEmployee(' . $userId . '); return false;">
                            <i class="fa fa-check-circle mr-2"></i> Mark Confirmed
                          </a>';
            }

            if ($exitId) {
                $html .= '<a class="dropdown-item openRightModal" href="' . route('employee-exit.show', $exitId) . '">
                            <i class="fa fa-eye mr-2"></i> View Exit Details
                          </a>
                          <a class="dropdown-item openRightModal" href="' . route('employee-exit.edit', $exitId) . '">
                            <i class="fa fa-edit mr-2"></i> Edit Exit
                          </a>
                          <a class="dropdown-item openRightModal" href="' . route('employee-exit.checklist', $exitId) . '">
                            <i class="fa fa-clipboard-check mr-2"></i> Offboarding Checklist
                          </a>
                          <a class="dropdown-item openRightModal" href="' . route('employee-exit.interview', $exitId) . '">
                            <i class="fa fa-comments mr-2"></i> Exit Interview
                          </a>';

                if ($status === 'notice_period') {
                    $html .= '<a class="dropdown-item" href="#" onclick="offboardEmployee(' . $exitId . '); return false;">
                                <i class="fa fa-door-open mr-2"></i> Mark Off Boarded
                              </a>';
                }
            }

            $html .= '<a class="dropdown-item openRightModal" href="' . route('employee-exit.history', $userId) . '">
                        <i class="fa fa-history mr-2"></i> Exit History
                      </a>';

            // Someone who has left can be brought back as the same employee
            $hasLeft = $status === 'off_boarded'
                || (!empty($row->last_date) && \Carbon\Carbon::parse($row->last_date)->lt(now()->startOfDay()));

            if ($hasLeft && user()->permission('edit_employees') === 'all') {
                $html .= '<a class="dropdown-item" href="' . route('employees.rejoin.create', $userId) . '">
                            <i class="fa fa-user-plus mr-2"></i> Rejoin / Rehire
                          </a>';
            }

            $html .= '</div></div></div>';

            return $html;
        });

        $datatables->rawColumns(['check', 'employee', 'status_badge', 'action']);
        $datatables->setRowId('user_id');

        return $datatables;
    }

    public function query()
    {
        $request = request();
        $statusSql = EmployeeDetails::employmentStatusSql();

        $query = EmployeeDetails::query()
            ->join('users', 'users.id', '=', 'employee_details.user_id')
            ->leftJoin('designations', 'designations.id', '=', 'employee_details.designation_id')
            ->leftJoin('teams', 'teams.id', '=', 'employee_details.department_id')
            ->leftJoin('employee_exits', function ($join) {
                $join->on('employee_exits.user_id', '=', 'employee_details.user_id')
                     ->whereRaw('employee_exits.id = (SELECT MAX(ee2.id) FROM employee_exits ee2 WHERE ee2.user_id = employee_details.user_id)');
            })
            ->where('employee_details.company_id', company()->id)
            ->select([
                'employee_details.id',
                'employee_details.user_id',
                'employee_details.employee_id',
                'employee_details.employment_status',
                'employee_details.probation_end_date',
                'employee_details.notice_period_start_date',
                'employee_details.notice_period_end_date',
                'employee_details.last_date',
                'users.name',
                'users.image',
                'users.email',
                'users.status as user_status',
                'designations.name as designation_name',
                'teams.team_name as department_name',
                'employee_exits.id as exit_id',
                'employee_exits.exit_type',
                'employee_exits.last_working_date as exit_last_working_date',
            ])
            ->selectRaw("{$statusSql} as effective_status");

        if ($request->status && $request->status !== 'all') {
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
        $dataTable = $this->setBuilder('employee-exit-table', 2);

        // This table's column layout changed several times during development —
        // a browser that saved state against an older layout (via localStorage,
        // which a normal refresh does not clear) can end up requesting an
        // out-of-range page or a stale column config and render no rows even
        // though the server has data. Disable state persistence for this table
        // so every load starts clean.
        $dataTable->stateSave(false);

        if (canDataTableExport()) {
            $dataTable->buttons(Button::make(['extend' => 'excel', 'text' => '<i class="fa fa-file-export"></i> ' . trans('app.exportExcel')]));
        }

        return $dataTable;
    }

    protected function getColumns()
    {
        return [
            Column::make('check')->title('')->orderable(false)->exportable(false)->searchable(false)->width(30),
            Column::make('employee')->title('Employee'),
            Column::make('department')->title('Department'),
            Column::make('designation')->title('Designation'),
            Column::make('status_badge')->title('Status'),
            Column::make('exit_type')->title('Exit Type'),
            Column::make('last_working_date')->title('Last Working Date'),
            Column::make('action')->title('Action')->width(100)->exportable(false)->searchable(false)->orderable(false),
        ];
    }
}
