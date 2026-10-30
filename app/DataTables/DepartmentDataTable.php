<?php

namespace App\DataTables;

use App\Models\Team;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use App\Helper\Common;

class DepartmentDataTable extends BaseDataTable
{

    private $editDepartmentPermission;
    private $deleteDepartmentPermission;
    public $arr = [];

    public function __construct()
    {
        parent::__construct();
        $this->editDepartmentPermission = user()->permission('edit_department');
        $this->deleteDepartmentPermission = user()->permission('delete_department');
    }

    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return \Yajra\DataTables\DataTableAbstract
     */
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->addColumn('check', fn($row) => $this->checkBox($row))
            // View and Edit as buttons on the row, delete beside them (no menu to open).
            ->addColumn('action', function ($row) {
                $action = '<div class="row-actions">'
                    . '<a href="' . route('departments.show', [$row->id]) . '" class="row-action openRightModal">'
                    . '<i class="fa fa-eye mr-1"></i>' . __('app.view') . '</a>';

                if ($this->editDepartmentPermission == 'all') {
                    $action .= '<a href="' . route('departments.edit', [$row->id]) . '" class="row-action openRightModal">'
                        . '<i class="fa fa-edit mr-1"></i>' . __('app.edit') . '</a>';
                }

                if ($this->deleteDepartmentPermission == 'all') {
                    $action .= '<a href="javascript:;" class="row-action row-action-danger delete-table-row" data-department-id="' . $row->id . '"'
                        . ' title="' . __('app.delete') . '" data-toggle="tooltip"><i class="fa fa-trash"></i></a>';
                }

                return $action . '</div>';
            })
            // Shown as Division and Sub Division: a department with a parent is a
            // sub division of it, one without is a division.
            ->addColumn('division', function ($row) {
                return $row->parent_id
                    ? e($this->teamNames()[$row->parent_id] ?? '-')
                    : $this->teamLink($row);
            })
            ->addColumn('sub_division', fn ($row) => $row->parent_id ? $this->teamLink($row) : '-')
            // Each division, then its sub divisions.
            ->order(fn ($query) => $query->orderByRaw('COALESCE(parent_id, id), parent_id IS NOT NULL, team_name'))
            ->addIndexColumn()
            ->smart(false)
            ->setRowId(fn($row) => 'row-' . $row->id)
            ->rawColumns(['action', 'division', 'sub_division', 'check']);
    }

    /**
     * Get query source of dataTable.
     *
     * @param \App\Models\Team $model
     * @return \Illuminate\Database\Eloquent\Builder
     */

    public function query(Team $model)
    {
        $request = $this->request();
        $model = $model->select('*');

        if (request()->searchText != '') {
            $safeTerm = Common::safeString(request('searchText'));
            $model->where('team_name', 'like', '%' . $safeTerm . '%');
        }

        if ($request->parentId != 'all' && $request->parentId != null) {
            $departments = Team::with('childs')->where('id', $request->parentId)->get();

            foreach ($departments as $department) {
                if ($department->childs) {
                    $this->child($department->childs);
                    array_push($this->arr, $department->id);
                }
            }

            $model->whereIn('id', $this->arr);
        }

        return $model;
    }

    private ?array $names = null;

    private function teamNames(): array
    {
        return $this->names ??= Team::pluck('team_name', 'id')->all();
    }

    private function teamLink($row): string
    {
        return '<h5 class="mb-0 f-13 text-darkest-grey"><a href="' . route('departments.show', [$row->id]) . '" class="openRightModal">' . e($row->team_name) . '</a></h5>';
    }

    public function child($child)
    {
        foreach ($child as $item) {
            array_push($this->arr, $item->id);

            if ($item->childs) {
                $this->child($item->childs);
            }
        }
    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return \Yajra\DataTables\Html\Builder
     */
    public function html()
    {
        $dataTable = $this->setBuilder('departments-table', 2)
            ->parameters([
                'initComplete' => 'function () {
                   window.LaravelDataTables["departments-table"].buttons().container()
                    .appendTo("#table-actions")
                }',
                'fnDrawCallback' => 'function( oSettings ) {
                    $("body").tooltip({
                        selector: \'[data-toggle="tooltip"]\'
                    })
                }',
            ]);

        if (canDataTableExport()) {
            $dataTable->buttons(Button::make(['extend' => 'excel', 'text' => '<i class="fa fa-file-export"></i> ' . trans('app.exportExcel')]));
        }

        return $dataTable;
    }

    /**
     * Get columns.
     *
     * @return array
     */
    protected function getColumns()
    {
        return [
            'check' => [
                'title' => '<input type="checkbox" name="select_all_table" id="select-all-table" onclick="selectAllTable(this)">',
                'exportable' => false,
                'orderable' => false,
                'searchable' => false,
                'visible' => !in_array('client', user_roles())
            ],
            '#' => ['data' => 'DT_RowIndex', 'orderable' => false, 'searchable' => false, 'visible' => false, 'title' => '#'],
            'Division' => ['data' => 'division', 'name' => 'team_name', 'searchable' => false, 'title' => 'Division'],
            'Sub Division' => ['data' => 'sub_division', 'name' => 'parent_id', 'searchable' => false, 'orderable' => false, 'exportable' => true, 'title' => 'Sub Division'],
            Column::computed('action', __('app.action'))
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->addClass('text-right pr-20')
        ];
    }

}
