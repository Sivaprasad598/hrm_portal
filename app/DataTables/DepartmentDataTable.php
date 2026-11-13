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
            // One name column: a division in bold with how many sub divisions it
            // has, each sub division indented under it - the division's name is not
            // repeated on every row. While searching, a sub division says whose it is.
            ->addColumn('division', function ($row) {
                if (!$row->parent_id) {
                    $subs = $this->childCounts()[$row->id] ?? 0;

                    return '<a href="' . route('departments.show', [$row->id]) . '" class="openRightModal dv-division">' . e($row->team_name) . '</a>'
                        . ($subs ? ' <span class="dv-count">· ' . $subs . ' sub division' . ($subs > 1 ? 's' : '') . '</span>' : '');
                }

                $parent = request()->searchText ? '<span class="dv-parent">in ' . e($this->teamNames()[$row->parent_id] ?? '-') . '</span>' : '';

                return '<span class="dv-sub"><a href="' . route('departments.show', [$row->id]) . '" class="openRightModal">' . e($row->team_name) . '</a>' . $parent . '</span>';
            })
            ->addColumn('division_name', fn ($row) => $row->parent_id ? ($this->teamNames()[$row->parent_id] ?? '') : $row->team_name)
            ->addColumn('sub_division_name', fn ($row) => $row->parent_id ? $row->team_name : '')
            // Each division, then its sub divisions.
            ->order(fn ($query) => $query->orderByRaw('COALESCE(parent_id, id), parent_id IS NOT NULL, team_name'))
            ->addIndexColumn()
            ->smart(false)
            ->setRowId(fn($row) => 'row-' . $row->id)
            ->rawColumns(['action', 'division', 'check']);
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

    private ?array $counts = null;

    private function childCounts(): array
    {
        return $this->counts ??= Team::whereNotNull('parent_id')->selectRaw('parent_id, COUNT(*) n')->groupBy('parent_id')->pluck('n', 'parent_id')->all();
    }

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
            'Division' => ['data' => 'division', 'name' => 'team_name', 'searchable' => false, 'orderable' => false, 'exportable' => false, 'title' => 'Division / Sub Division'],
            // the Excel export keeps the two plain columns
            'Division ' => ['data' => 'division_name', 'name' => 'team_name', 'visible' => false, 'searchable' => false, 'orderable' => false, 'title' => 'Division'],
            'Sub Division' => ['data' => 'sub_division_name', 'name' => 'parent_id', 'visible' => false, 'searchable' => false, 'orderable' => false, 'title' => 'Sub Division'],
            Column::computed('action', __('app.action'))
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->addClass('text-right pr-20')
        ];
    }

}
