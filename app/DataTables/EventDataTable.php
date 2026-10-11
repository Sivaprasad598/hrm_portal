<?php

namespace App\DataTables;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use App\Models\CustomField;
use App\Models\CustomFieldGroup;
use App\Helper\UserService;
use App\Helper\Common;

class EventDataTable extends BaseDataTable
{

    private $editPermission;
    private $deletePermission;
    private $viewPermission;

    public function __construct()
    {
        parent::__construct();
        $this->viewPermission = user()->permission('view_events');
        $this->editPermission = user()->permission('edit_events');
        $this->deletePermission = user()->permission('delete_events');
    }

    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return \Yajra\DataTables\DataTableAbstract
     */
    public function dataTable($query)
    {
        $datatables = datatables()->eloquent($query);
        $datatables->addIndexColumn()
            ->addColumn('check', fn($row) => $this->checkBox($row))
            ->editColumn('start_date', function ($row) {

                return Carbon::parse($row->start_date_time)->translatedFormat($this->company->date_format);
            })
            ->editColumn('start_time', function ($row) {

                return Carbon::parse($row->start_date_time)->translatedFormat($this->company->time_format);
            })
            ->editColumn('end_date', function ($row) {

                return Carbon::parse($row->end_date_time)->translatedFormat($this->company->date_format);
            })
            ->editColumn('end_time', function ($row) {

                return Carbon::parse($row->end_date_time)->translatedFormat($this->company->time_format);
            })
            ->addColumn('event', function ($row) {

                $recurring = '';

                if ($row->repeat == 'yes') {
                    $recurring = '<span class="badge badge-primary"> ' . __('app.recurring') . ' </span>';
                }

                return '<div class="media align-items-center">
                            <div class="media-body">
                        <h5 class="mb-0 f-13 text-darkest-grey"><a href="' . route('events.show', [$row->id]) . '">' . $row->event_name . '</a></h5><p class="mb-0">' . $recurring . '</p>
                        </div>
                    </div>';
            })
            ->addColumn('place', function ($row) {
                return $row->where;
            })
            ->editColumn('attendees', function ($row) {
                $attendees = $row->attendee;

                if ($attendees->isEmpty()) {
                    return '--';
                }

                $members = '<div class="position-relative">';
                $count = 0;

                foreach ($attendees as $attendee) {
                    $user = $attendee->user;

                    if ($user && $count < 4) {
                        $img = '<img data-toggle="tooltip" data-original-title="' . e($user->name) . '" src="' . e($user->image_url) . '">';
                        $position = $count > 0 ? 'position-absolute' : '';

                        $members .= '<div class="taskEmployeeImg rounded-circle ' . $position . '" style="left: ' . ($count * 13) . 'px"><a href="' . route('employees.show', $user->id) . '">' . $img . '</a></div> ';
                        $count++;
                    }
                }

                if ($attendees->count() > 4) {
                    $members .= '<div class="taskEmployeeImg more-user-count text-center rounded-circle bg-amt-grey position-absolute" style="left: 52px"><a href="' . route('events.show', [$row->id]) . '" class="text-dark f-10">+' . ($attendees->count() - 4) . '</a></div>';
                }

                $members .= '</div>';

                return $members;
            })
            ->addColumn('attendees_name', function ($row) {
                $members = [];

                foreach ($row->attendee as $member) {
                    $user = $member->user;
                    $members[] = $user->name;
                }

                return implode(',', $members);
            })
            ->addColumn('status', function ($row) {

                $statusClass = match ($row->status) {
                    'pending' => 'text-yellow',
                    'cancelled' => 'text-red',
                    'completed' => 'text-dark-green',
                };
                return '<i class="fa fa-circle mr-1 ' . $statusClass . ' f-10"></i>' . __('app.' . $row->status);
            })
            ->addColumn('action', function ($row) {

                $actions = '<div class="task_view">

                    <div class="dropdown">
                        <a class="task_view_more d-flex align-items-center justify-content-center dropdown-toggle" type="link" id="dropdownMenuLink-41" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="icon-options-vertical icons"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuLink-41" tabindex="0" x-placement="bottom-end" style="position: absolute; transform: translate3d(-137px, 26px, 0px); top: 0px; left: 0px; will-change: transform;">';

                $actions .= '<a href="' . route('events.show', [$row->id]) . '" class="dropdown-item openRightModal"><i class="mr-2 fa fa-eye"></i>' . __('app.view') . '</a>';

                if (request()->recurringID == '') {
                    $type = 'table-view';
                } else {
                    $type = 'recurring-view';
                }
                if ($this->editPermission == 'all' || ($this->editPermission == 'added' && user()->id == $row->added_by)) {
                    $actions .= '<a class="dropdown-item openRightModal" href="' . route('events.edit', [$row->id, 'type' => $type]) . '">
                                    <i class="mr-2 fa fa-edit"></i>
                                    ' . __('app.edit') . '
                            </a>';
                }

                // if (request()->recurringID == '') {
                if ($this->deletePermission == 'all' || ($this->deletePermission == 'added' && user()->id == $row->added_by)) {
                    $actions .= '<a data-event-id=' . $row->id . '
                                class="dropdown-item delete-table-row" href="javascript:;">
                                <i class="mr-2 fa fa-trash"></i>
                                    ' . __('app.delete') . '
                            </a>';
                }
                // }

                $actions .= '</div> </div> </div>';

                return $actions;
            })
            ->smart(false)
            ->setRowId(fn($row) => 'row-' . $row->id)
            ->orderColumn('start_date', 'start_date_time $1')
            ->orderColumn('end_date', 'end_date_time $1')
            ->orderColumn('start_time', 'start_date_time $1')
            ->orderColumn('end_time', 'end_date_time $1')
            ->orderColumn('event', 'event_name $1')
            ->orderColumn('place', 'where')
            ->rawColumns(['attendees_names']);
        $customFieldColumns = CustomField::customFieldData($datatables, Event::CUSTOM_FIELD_MODEL);

        $datatables->rawColumns(['check', 'attendees', 'action', 'event', 'status'], $customFieldColumns);

        return $datatables;
    }

    /**
     * @param Event $model
     * @return \Illuminate\Database\Query\Builder
     */
    public function query(Event $model)
    {
        $userId = UserService::getUserId();
        $events = $model->with('attendee', 'attendee.user')->newQuery();

        if (!is_null(request()->year)) {
            $events->where(DB::raw('Year(events.start_date_time)'), request()->year);
        }

        if (request()->client && request()->client != 'all') {
            $clientId = request()->client;
            $events->whereHas('attendee.user', function ($query) use ($clientId) {
                $query->where('user_id', $clientId);
            });
        }

        if (request()->status && request()->status != 'all') {
            $status = request()->status;
            $events->where('events.status', $status);
        }

        if (request()->employee && request()->employee != 'all' && request()->employee != 'undefined') {
            $employeeId = request()->employee;
            $events->whereHas('attendee.user', function ($query) use ($employeeId) {
                $query->where('user_id', $employeeId);
            });
        }

        if (!is_null(request()->month)) {
            $events->where(DB::raw('Month(events.start_date_time)'), request()->month);
        }

        if (request()->searchText != '') {
            $safeTerm = Common::safeString(request('searchText'));
            $events->where('events.event_name', 'like', '%' . $safeTerm . '%');
        }

        if ($this->viewPermission == 'added') {
            $events->leftJoin('mention_users', 'mention_users.event_id', 'events.id');
            $events->where('events.added_by', $userId);
            $events->orWhere('mention_users.user_id', $userId);
        }

        if ($this->viewPermission == 'owned') {
            $events->whereHas('attendee.user', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })->orWhere('events.host', $userId);
        }

        if (request()->recurringID != '') {
            $model = $events->where('id', request()->recurringID)->orWhere('parent_id', request()->recurringID);
        }

        if (in_array('client', user_roles())) {
            $events->whereHas('attendee.user', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            });
        }

        if ($this->viewPermission == 'both') {
            $events->where('events.added_by', $userId);
            $events->orWhereHas('attendee.user', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })->orWhere('events.host', $userId);
        }

        $events->orderBy('id', 'desc');
        return $events;
    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return \Yajra\DataTables\Html\Builder
     */
    public function html()
    {
        $dataTable = $this->setBuilder('event-table')
            ->parameters([
                'initComplete' => 'function () {
                   window.LaravelDataTables["event-table"].buttons().container()
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
        $data = [
            'check' => [
                'title' => '<input type="checkbox" name="select_all_table" id="select-all-table" onclick="selectAllTable(this)">',
                'exportable' => false,
                'orderable' => false,
                'searchable' => false
            ],
            '#' => ['data' => 'DT_RowIndex', 'orderable' => false, 'searchable' => false, 'visible' => false, 'title' => '#'],
            __('modules.events.eventName') => ['data' => 'event', 'name' => 'event_name', 'title' => __('modules.events.eventName')],
            __('modules.events.where') => ['data' => 'place', 'name' => 'where', 'title' => __('modules.events.where')],
            __('app.startDate') => ['data' => 'start_date', 'name' => 'start_date', 'title' => __('app.startDate')],
            __('modules.employees.startTime') => ['data' => 'start_time', 'name' => 'start_time', 'title' => __('modules.employees.startTime')],
            __('app.endDate') => ['data' => 'end_date', 'name' => 'end_date', 'title' => __('app.endDate')],
            __('modules.employees.endTime') => ['data' => 'end_time', 'name' => 'end_time', 'title' => __('modules.employees.endTime')],
            __('modules.events.attendees') => ['data' => 'attendees', 'name' => 'attendees', 'exportable' => false, 'title' => __('modules.events.attendees')],
            'attendees_names' => ['data' => 'attendees_name', 'name' => 'attendees_name', 'visible' => false, 'title' => __('modules.events.attendees')],
            __('modules.status') => ['data' => 'status', 'name' => 'status', 'title' => __('app.status')],
        ];

        $action = [
            Column::computed('action', __('app.action'))
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->addClass('text-right pr-20')
        ];

        return array_merge($data, CustomFieldGroup::customFieldsDataMerge(new Event()), $action);
    }
}
