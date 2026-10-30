<?php

namespace App\DataTables;

use App\Models\Designation;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use App\Helper\Common;

class DesignationDataTable extends BaseDataTable
{

    private $editDesignationPermission;
    private $deleteDesignationPermission;
    public $arr = [];

    public function __construct()
    {
        parent::__construct();
        $this->editDesignationPermission = user()->permission('edit_designation');
        $this->deleteDesignationPermission = user()->permission('delete_designation');
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
            ->addIndexColumn()
            ->addColumn('check', fn($row) => $this->checkBox($row))
            ->editColumn('name', function ($row) {
                $name = '<h5 class="mb-0 f-13 text-darkest-grey"><a href="' . route('designations.show', [$row->id]) . '" class="openRightModal">' . $row->name . '</a></h5>';

                return $name;
            })
            // View and Edit as buttons on the row, delete beside them (no menu to open).
            ->addColumn('action', function ($row) {
                $action = '<div class="row-actions">'
                    . '<a href="' . route('designations.show', [$row->id]) . '" class="row-action openRightModal">'
                    . '<i class="fa fa-eye mr-1"></i>' . __('app.view') . '</a>';

                if ($this->editDesignationPermission == 'all') {
                    $action .= '<a href="' . route('designations.edit', [$row->id]) . '" class="row-action openRightModal">'
                        . '<i class="fa fa-edit mr-1"></i>' . __('app.edit') . '</a>';
                }

                if ($this->deleteDesignationPermission == 'all') {
                    $action .= '<a href="javascript:;" class="row-action row-action-danger delete-table-row" data-designation-id="' . $row->id . '"'
                        . ' title="' . __('app.delete') . '" data-toggle="tooltip"><i class="fa fa-trash"></i></a>';
                }

                return $action . '</div>';
            })
            ->smart(false)
            ->setRowId(fn($row) => 'row-' . $row->id)
            ->rawColumns(['check', 'action', 'name']);
    }

    /**
     * @param Designation $model
     * @return \Illuminate\Database\Query\Builder
     */
    public function query(Designation $model)
    {
        $request = $this->request();
        $model = $model->select('*');

        if (request()->searchText != '') {
            $safeTerm = Common::safeString(request('searchText'));
            $model->where('name', 'like', '%' . $safeTerm . '%');
        }

        if ($request->parentId != 'all' && $request->parentId != null) {
            $departments = Designation::with('childs')->where('id', $request->parentId)->get();

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

    public function child($child)
    {
        foreach ($child as $item) {
            $this->arr[] = $item->id;

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
        $dataTable = $this->setBuilder('Designation-table')
            ->parameters([
                'initComplete' => 'function () {
                   window.LaravelDataTables["Designation-table"].buttons().container()
                    .appendTo("#table-actions")
                }',
                'fnDrawCallback' => 'function( oSettings ) {
                    $("body").tooltip({
                        selector: \'[data-toggle="tooltip"]\'
                    });
                    $(".statusChange").selectpicker();
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
                'searchable' => false
            ],
            '#' => ['data' => 'DT_RowIndex', 'orderable' => false, 'searchable' => false, 'visible' => false, 'title' => '#'],
            __('app.name') => ['data' => 'name', 'name' => 'name', 'exportable' => true, 'title' => __('app.name')],
            Column::computed('action', __('app.action'))
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->addClass('text-right pr-20')
        ];
    }
}
