<?php

namespace App\Http\Controllers;

use App\Scopes\ActiveScope;
use Carbon\Carbon;
use App\Models\User;
use App\Helper\Reply;
use App\Models\Event;
use App\Models\Team;
use App\Models\EventAttendee;
use App\Events\EventInviteEvent;
use App\Events\EventInviteMentionEvent;
use App\Events\EventStatusNoteEvent;
use App\DataTables\EventDataTable;
use App\Http\Requests\Events\StoreEvent;
use App\Http\Requests\Events\StoreEventNote;
use App\Http\Requests\Events\UpdateEvent;
use App\Models\MentionUser;
use Illuminate\Http\Request;
use App\Helper\UserService;
use App\Events\EventCompletedEvent;
use App\Helper\Common;

class EventCalendarController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.events';
        $this->middleware(function ($request, $next) {
            abort_403(!in_array('events', $this->user->modules));
            return $next($request);
        });
    }

    public function index()
    {
        $viewPermission = user()->permission('view_events');
        abort_403(!in_array($viewPermission, ['all', 'added', 'owned', 'both']));

        if (in_array('client', user_roles())) {
            $this->clients = User::client();
        } else {
            $this->clients = User::allClients();
            $this->employees = User::allEmployees(null, true, ($viewPermission == 'all' ? 'all' : null));
        }

        $userId = UserService::getUserId();

        if (request('start') && request('end')) {
            $model = Event::with('attendee', 'attendee.user');

            if (request()->clientId && request()->clientId != 'all') {
                $clientId = request()->clientId;
                $model->whereHas('attendee.user', function ($query) use ($clientId) {
                    $query->where('user_id', $clientId);
                });
            }

            if (request()->status && request()->status != 'all') {
                $status = request()->status;
                $model->where('status', $status);
            }

            if (request()->employeeId && request()->employeeId != 'all' && request()->employeeId != 'undefined') {
                $employeeId = request()->employeeId;
                $model->whereHas('attendee.user', function ($query) use ($employeeId) {
                    $query->where('user_id', $employeeId);
                });
            }

            if (request()->searchText && request()->searchText != 'all') {
                $safeTerm = Common::safeString(request('searchText'));
                $model->where('event_name', 'like', '%' . $safeTerm . '%');
            }

            if ($viewPermission == 'added') {
                $model->leftJoin('mention_users', 'mention_users.event_id', 'events.id');
                $model->where('added_by', $userId);
                $model->orWhere('mention_users.user_id', $userId);
            }

            if ($viewPermission == 'owned') {
                $model->whereHas('attendee.user', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })->orWhere('host', $userId);
            }

            if (in_array('client', user_roles())) {
                $model->whereHas('attendee.user', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                });
            }

            if ($viewPermission == 'both') {
                $model->where('added_by', $userId);
                $model->orWhereHas('attendee.user', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })->orWhere('host', $userId);
            }

            $events = $model->get();

            $eventData = array();

            foreach ($events as $key => $event) {
                $eventData[] = [
                    'id' => $event->id,
                    'title' => $event->event_name,
                    'start' => $event->start_date_time,
                    'end' => $event->end_date_time,
                    'color' => $event->label_color
                ];
            }

            return $eventData;
        }

        return view('event-calendar.index', $this->data);
    }

    public function tableView(EventDataTable $dataTable)
    {
        $viewPermission = user()->permission('view_events');
        abort_403(!in_array($viewPermission, ['all', 'added', 'owned', 'both']));

        if (in_array('client', user_roles())) {
            $this->clients = User::client();
        } else {
            $this->clients = User::allClients();
            $this->employees = User::allEmployees(null, true, ($viewPermission == 'all' ? 'all' : null));
        }

        $this->pageTitle = __('app.menu.listView');
        $this->currentYear = now()->format('Y');
        $this->currentMonth = now()->month;

        /* year range from last 5 year to next year */
        $years = [];

        $latestFifthYear = (int)now()->subYears(5)->format('Y');
        $nextYear = (int)now()->addYear()->format('Y');

        for ($i = $latestFifthYear; $i <= $nextYear; $i++) {
            $years[] = $i;
        }

        $this->years = $years;

        return $dataTable->render('event-calendar.ajax.table-view', $this->data);
    }

    public function applyQuickAction(Request $request)
    {
        abort_403(!in_array(user()->permission('delete_events'), ['all', 'added']));

        if ($request->action_type === 'delete') {

            $this->deleteRecords($request);
            return Reply::success(__('messages.deleteSuccess'));
        } elseif ($request->action_type === 'change-status') {
            $this->changeBulkStatus($request);

            return Reply::success(__('messages.updateSuccess'));
        }

        return Reply::error(__('messages.selectAction'));
    }

    protected function changeBulkStatus($request)
    {

        $event = Event::withoutGlobalScope(ActiveScope::class)->whereIn('id', explode(',', $request->row_ids))->get();

        $event->each(function ($event) use ($request) {
            $event->status = $request->status;
            $event->save();
        });
    }

    protected function deleteRecords($request)
    {
        abort_403(user()->permission('delete_events') != 'all');

        Event::whereIn('id', explode(',', $request->row_ids))->delete();
    }

    public function create()
    {
        $addPermission = user()->permission('add_events');
        abort_403(!in_array($addPermission, ['all', 'added']));

        $this->redirectUrl = request()->type == 'table-view' ? route('events.table_view') : route('events.index');
        $this->employees = User::allEmployees(null, true);
        $this->clients = User::allClients();
        $this->pageTitle = __('modules.events.addEvent');
        $userData = [];

        $usersData = $this->employees;

        foreach ($usersData as $user) {

            $url = route('employees.show', [$user->id]);

            $userData[] = ['id' => $user->id, 'value' => $user->name, 'image' => $user->image_url, 'link' => $url];
        }

        $event = new Event();
        $getCustomFieldGroupsWithFields = $event->getCustomFieldGroupsWithFields();

        if ($getCustomFieldGroupsWithFields) {
            $this->fields = $getCustomFieldGroupsWithFields->fields;
        }

        $this->userData = $userData;
        $this->teams = Team::all();
        $this->view = 'event-calendar.ajax.create';

        if (request()->ajax()) {
            return $this->returnAjax($this->view);
        }

        return view('event-calendar.create', $this->data);
    }

    public function store(StoreEvent $request)
    {
        $addPermission = user()->permission('add_events');
        abort_403(!in_array($addPermission, ['all', 'added']));

        $event = new Event();
        $event->event_name = $request->event_name;
        $event->where = $request->where;
        $event->description = trim_editor($request->description);

        $start_date_time = Carbon::createFromFormat($this->company->date_format, $request->start_date, $this->company->timezone)->format('Y-m-d') . ' ' . Carbon::createFromFormat($this->company->time_format, $request->start_time)->format('H:i:s');
        $event->start_date_time = Carbon::parse($start_date_time)->setTimezone('UTC');

        $end_date_time = Carbon::createFromFormat($this->company->date_format, $request->end_date, $this->company->timezone)->format('Y-m-d') . ' ' . Carbon::createFromFormat($this->company->time_format, $request->end_time)->format('H:i:s');
        $event->end_date_time = Carbon::parse($end_date_time)->setTimezone('UTC');
        $event->departments = json_encode($request->team_id);
        $event->repeat = $request->repeat ? $request->repeat : 'no';
        $event->send_reminder = $request->send_reminder ? $request->send_reminder : 'no';
        $event->repeat_every = $request->repeat_count;
        $event->repeat_cycles = $request->repeat_cycles;
        $event->repeat_type = $request->repeat_type ?: 'day';
        $event->remind_time = $request->remind_time;
        $event->remind_type = $request->remind_type;
        $event->label_color = $request->label_color;
        $event->event_link = $request->event_link;
        $event->host = $request->host;
        $event->status = $request->status;
        $event->save();

        // To add custom fields data
        if ($request->custom_fields_data) {
            $event->updateCustomFieldData($request->custom_fields_data);
        }

        if ($request->all_employees) {
            $attendees = User::allEmployees(null, false);

            // Prepare bulk insert data
            $attendeeData = $attendees->map(function ($attendee) use ($event) {
                return [
                    'user_id' => $attendee->id,
                    'event_id' => $event->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            })->toArray();

            // Bulk insert
            EventAttendee::insert($attendeeData);

            event(new EventInviteEvent($event, $attendees));
        }

        if ($request->user_id) {
            foreach ($request->user_id as $userId) {
                EventAttendee::firstOrCreate(['user_id' => $userId, 'event_id' => $event->id]);
            }

            $attendees = User::whereIn('id', $request->user_id)->get();

            event(new EventInviteEvent($event, $attendees));
        }

        // Add repeated event
        if ($request->has('repeat') && $request->repeat == 'yes') {
            $repeatCount = $request->repeat_count;
            $repeatType = $request->repeat_type;
            $repeatCycles = $request->repeat_cycles;
            $startDate = Carbon::createFromFormat($this->company->date_format, $request->start_date);
            $dueDate = Carbon::createFromFormat($this->company->date_format, $request->end_date);

            if ($repeatType == 'monthly-on-same-day') {

                $startDateOriginal = $startDate->copy();
                $dueDateDiff = $dueDate->diffInDays($startDate);
                $weekOfMonth = $startDateOriginal->weekOfMonth;
                $weekDay = $startDateOriginal->dayOfWeek;
                $startDateOriginal->startOfMonth();

                for ($i = 1; $i < $repeatCycles; $i++) {
                    $eventStartDate = $startDateOriginal->addMonths($repeatCount)->copy();

                    if ($weekOfMonth == 1) {
                        $eventStartDate->startOfMonth();
                        $eventStartDateCopy = $eventStartDate->copy();
                        $eventStartDate->addWeeks($weekOfMonth - 1);
                        $eventStartDate->startOfWeek();
                        $eventStartDate->addDays($weekDay - 1);

                        if ($eventStartDateCopy->month != $eventStartDate->month) {
                            $eventStartDate->addWeek();
                        }
                    } elseif ($weekOfMonth == 5) {
                        $eventStartDate->endOfMonth();
                        $eventStartDate->startOfWeek();
                        $eventStartDateCopy = $eventStartDate->copy();
                        $eventStartDate->addDays($weekDay - 1);

                        if ($eventStartDateCopy->month != $eventStartDate->month) {
                            $eventStartDate->subWeek();
                        }

                        if ($eventStartDate->copy()->addWeek()->month == $eventStartDate->month) {
                            $eventStartDate->addWeek();
                        }
                    } else {
                        $eventStartDate->startOfMonth();
                        $eventStartDate->addWeeks($weekOfMonth - 1);
                        $eventStartDate->startOfWeek();
                        $eventStartDate->addDays($weekDay - 1);

                        if ($eventStartDate->weekOfMonth != $weekOfMonth && $eventStartDate->copy()->addWeek()->month == $eventStartDate->month) {
                            $eventStartDate->addWeek();
                        }
                    }

                    $eventDueDate = $eventStartDate->copy()->addDays($dueDateDiff);

                    $this->addRepeatEvent($event, $request, $eventStartDate, $eventDueDate);
                }
            } else {
                for ($i = 1; $i < $repeatCycles; $i++) {
                    $startDate = $startDate->add($repeatCount, str_plural($repeatType));
                    $dueDate = $dueDate->add($repeatCount, str_plural($repeatType));

                    $this->addRepeatEvent($event, $request, $startDate, $dueDate);
                }
            }
        }

        if ($request->mention_user_ids != '' || $request->mention_user_ids != null) {
            $event->mentionUser()->sync($request->mention_user_ids);
            $mentionUserIds = explode(',', $request->mention_user_ids);
            $mentionUser = User::whereIn('id', $mentionUserIds)->get();
            event(new EventInviteMentionEvent($event, $mentionUser));
        }

        $event->touch();

        $redirectUrl = urldecode($request->redirect_url);

        if ($redirectUrl == '') {
            $redirectUrl = route('events.ajax.table-view');
        }

        return Reply::successWithData(__('messages.recordSaved'), ['redirectUrl' => $redirectUrl, 'eventId' => $event->id]);
    }

    private function addRepeatEvent($parentEvent, $request, $startDate, $dueDate)
    {
        $event = new Event();
        $event->parent_id = $parentEvent->id;
        $event->event_name = $request->event_name;
        $event->where = $request->where;
        $event->description = trim_editor($request->description);
        $event->start_date_time = $startDate->format('Y-m-d') . '' . Carbon::parse($request->start_time)->format('H:i:s');
        $event->end_date_time = $dueDate->format('Y-m-d') . ' ' . Carbon::parse($request->end_time)->format('H:i:s');
        $event->host = $request->host;

        if ($request->repeat) {
            $event->repeat = $request->repeat;
        } else {
            $event->repeat = 'no';
        }

        if ($request->send_reminder) {
            $event->send_reminder = $request->send_reminder;
        } else {
            $event->send_reminder = 'no';
        }

        $event->repeat_every = $request->repeat_count;
        $event->repeat_cycles = $request->repeat_cycles;
        $event->repeat_type = $request->repeat_type;

        $event->remind_time = $request->remind_time;
        $event->remind_type = $request->remind_type;

        $event->label_color = $request->label_color;
        $event->save();

        if ($request->all_employees) {
            $attendees = User::allEmployees(null, false);

            // Prepare bulk insert data
            $attendeeData = $attendees->map(function ($attendee) use ($event) {
                return [
                    'user_id' => $attendee->id,
                    'event_id' => $event->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            })->toArray();

            // Bulk insert
            EventAttendee::insert($attendeeData);

            event(new EventInviteEvent($event, $attendees));
        }

        if ($request->user_id) {
            foreach ($request->user_id as $userId) {
                EventAttendee::firstOrCreate(['user_id' => $userId, 'event_id' => $event->id]);
            }
        }
    }

    public function edit($id)
    {
        $this->event = Event::with('attendee', 'attendee.user', 'files')->findOrFail($id)->withCustomFields();
        $this->editPermission = user()->permission('edit_events');
        $this->viewClientPermission = user()->permission('view_clients');
        $this->viewEmployeePermission = user()->permission('view_employees');
        $attendeesIds = $this->event->attendee->pluck('user_id')->toArray();
        $previousUrl = route('recurring-event.show', [$this->event->parent_id ?: $id, 'tab' => 'event']);
        $this->redirectUrl = request()->type == 'table-view' ? route('events.table_view') : (request()->type == 'recurring-view' ? $previousUrl : route('events.index'));

        $userId = UserService::getUserId();

        abort_403(!(
            $this->editPermission == 'all'
            || ($this->editPermission == 'added' && ($this->event->added_by == $userId || $this->event->host == $userId))
            || ($this->editPermission == 'owned' && (in_array($userId, $attendeesIds) || $this->event->host == $userId))
            || ($this->editPermission == 'both' && (in_array($userId, $attendeesIds) || $this->event->added_by == $userId || $this->event->host == $userId))
        ));

        $this->pageTitle = __('app.menu.editEvents');

        $getCustomFieldGroupsWithFields = $this->event->getCustomFieldGroupsWithFields();

        if ($getCustomFieldGroupsWithFields) {
            $this->fields = $getCustomFieldGroupsWithFields->fields;
        }

        $this->employees = User::allEmployees();
        $this->clients = User::allClients();
        $this->teams = Team::all();
        $userData = [];

        $this->clientIds = $this->event->attendee
            ->filter(function ($item) {
                return in_array('client', $item->user->roles->pluck('name')->toArray());
            });

        $this->userIds = $this->event->attendee
            ->filter(function ($item) {
                return in_array('employee', $item->user->roles->pluck('name')->toArray());
            });

        $usersData = $this->employees;

        foreach ($usersData as $user) {

            $url = route('employees.show', [$user->id]);

            $userData[] = ['id' => $user->id, 'value' => $user->name, 'image' => $user->image_url, 'link' => $url];
        }

        $this->userData = $userData;

        $attendeeArray = [];

        foreach ($this->event->attendee as $key => $item) {
            $attendeeArray[] = $item->user_id;
        }

        $this->attendeeArray = $attendeeArray;
        $this->departments = json_decode($this->event->departments, true);

        $this->view = 'event-calendar.ajax.edit';

        if (request()->ajax()) {
            return $this->returnAjax($this->view);
        }

        return view('notices.create', $this->data);
    }

    public function update(UpdateEvent $request, $id)
    {
        $this->editPermission = user()->permission('edit_events');
        $event = Event::findOrFail($id);
        $attendeesIds = $event->attendee->pluck('user_id')->toArray();

        $userId = UserService::getUserId();

        abort_403(!(
            $this->editPermission == 'all'
            || ($this->editPermission == 'added' && ($event->added_by == $userId || $event->host == $userId))
            || ($this->editPermission == 'owned' && (in_array($userId, $attendeesIds) || $event->host == $userId))
            || ($this->editPermission == 'both' && (in_array($userId, $attendeesIds) || $event->added_by == $userId || $event->host == $userId))
        ));

        $event->event_name = $request->event_name;
        $event->where = $request->where;
        $event->departments = json_encode($request->team_id);
        $event->description = trim_editor($request->description);
        $event->start_date_time = companyToYmd($request->start_date) . ' ' . Carbon::createFromFormat($this->company->time_format, $request->start_time)->format('H:i:s');
        $event->end_date_time = companyToYmd($request->end_date) . ' ' . Carbon::createFromFormat($this->company->time_format, $request->end_time)->format('H:i:s');

        if ($request->send_reminder) {
            $event->send_reminder = $request->send_reminder;
        } else {
            $event->send_reminder = 'no';
        }

        if ($request->recurring_event !== 'yes') {
            $event->repeat = $request->repeat;
            $event->repeat_every = $request->repeat_count;
            $event->repeat_cycles = $request->repeat_cycles;
            $event->repeat_type = $request->repeat_type;
        }

        $event->remind_time = $request->remind_time;
        $event->remind_type = $request->remind_type;

        $event->label_color = $request->label_color;
        $event->event_link = $request->event_link;

        $event->host = $request->host;
        $event->status = $request->status;
        $event->save();

        // To add custom fields data
        if ($request->custom_fields_data) {
            $event->updateCustomFieldData($request->custom_fields_data);
        }

        if ($request->has('repeat') && $request->repeat == 'yes') {

            Event::where('parent_id', $id)->delete();

            $repeatCount = $request->repeat_count;
            $repeatType = $request->repeat_type;
            $repeatCycles = $request->repeat_cycles;
            $startDate = Carbon::createFromFormat($this->company->date_format, $request->start_date);
            $dueDate = Carbon::createFromFormat($this->company->date_format, $request->end_date);

            if ($repeatType == 'monthly-on-same-day') {

                $startDateOriginal = $startDate->copy();
                $dueDateDiff = $dueDate->diffInDays($startDate);
                $weekOfMonth = $startDateOriginal->weekOfMonth;
                $weekDay = $startDateOriginal->dayOfWeek;
                $startDateOriginal->startOfMonth();

                for ($i = 1; $i < $repeatCycles; $i++) {
                    $eventStartDate = $startDateOriginal->addMonths($repeatCount)->copy();

                    if ($weekOfMonth == 1) {
                        $eventStartDate->startOfMonth();
                        $eventStartDateCopy = $eventStartDate->copy();
                        $eventStartDate->addWeeks($weekOfMonth - 1);
                        $eventStartDate->startOfWeek();
                        $eventStartDate->addDays($weekDay - 1);

                        if ($eventStartDateCopy->month != $eventStartDate->month) {
                            $eventStartDate->addWeek();
                        }
                    } elseif ($weekOfMonth == 5) {
                        $eventStartDate->endOfMonth();
                        $eventStartDate->startOfWeek();
                        $eventStartDateCopy = $eventStartDate->copy();
                        $eventStartDate->addDays($weekDay - 1);

                        if ($eventStartDateCopy->month != $eventStartDate->month) {
                            $eventStartDate->subWeek();
                        }

                        if ($eventStartDate->copy()->addWeek()->month == $eventStartDate->month) {
                            $eventStartDate->addWeek();
                        }
                    } else {
                        $eventStartDate->startOfMonth();
                        $eventStartDate->addWeeks($weekOfMonth - 1);
                        $eventStartDate->startOfWeek();
                        $eventStartDate->addDays($weekDay - 1);

                        if ($eventStartDate->weekOfMonth != $weekOfMonth && $eventStartDate->copy()->addWeek()->month == $eventStartDate->month) {
                            $eventStartDate->addWeek();
                        }
                    }

                    $eventDueDate = $eventStartDate->copy()->addDays($dueDateDiff);

                    $this->addRepeatEvent($event, $request, $eventStartDate, $eventDueDate);
                }
            } else {
                for ($i = 1; $i < $repeatCycles; $i++) {
                    $startDate = $startDate->add($repeatCount, str_plural($repeatType));
                    $dueDate = $dueDate->add($repeatCount, str_plural($repeatType));

                    $this->addRepeatEvent($event, $request, $startDate, $dueDate);
                }
            }
        }

        if ($request->all_employees) {
            $attendees = User::allEmployees();

            foreach ($attendees as $attendee) {
                $checkExists = EventAttendee::where('user_id', $attendee->id)->where('event_id', $event->id)->first();

                if (!$checkExists) {
                    EventAttendee::create(['user_id' => $attendee->id, 'event_id' => $event->id]);

                    // Send notification to user
                    $notifyUser = User::withoutGlobalScope(ActiveScope::class)->findOrFail($attendee->id);
                    event(new EventInviteEvent($event, $notifyUser));
                }
            }
        }

        if ($request->user_id) {

            $existEventUser = EventAttendee::where('event_id', $event->id)->pluck('user_id')->toArray();
            $users = $request->user_id;
            $value = array_diff($existEventUser, $users);

            EventAttendee::whereIn('user_id', $value)->where('event_id', $event->id)->delete();

            foreach ($request->user_id as $userId) {

                $checkExists = EventAttendee::where('user_id', $userId)->where('event_id', $event->id)->first();

                if (!$checkExists) {
                    EventAttendee::create(['user_id' => $userId, 'event_id' => $event->id]);

                    // Send notification to user
                    $notifyUser = User::withoutGlobalScope(ActiveScope::class)->findOrFail($userId);
                    event(new EventInviteEvent($event, $notifyUser));
                }
            }
        }

        $mentionedUser = MentionUser::where('event_id', $event->id)->pluck('user_id');
        $requestMentionIds = explode(',', request()->mention_user_ids);
        $newMention = [];
        $event->mentionUser()->sync(request()->mention_user_ids);

        if ($requestMentionIds != null) {
            foreach ($requestMentionIds as  $value) {

                if (($mentionedUser) != null) {

                    if (!in_array($value, json_decode($mentionedUser))) {

                        $newMention[] = $value;
                    }
                } else {

                    $newMention[] = $value;
                }
            }

            $newMentionMembers = User::whereIn('id', $newMention)->get();

            if (!empty($newMention)) {

                event(new EventInviteMentionEvent($event, $newMentionMembers));
            }
        }

        $redirectUrl = urldecode($request->redirect_url);

        if ($redirectUrl == '') {
            $redirectUrl = route('events.index');
        }

        return Reply::successWithData(__('messages.recordSaved'), ['redirectUrl' => $redirectUrl]);
    }

    public function show($id)
    {

        $this->viewPermission = user()->permission('view_events');
        $this->event = Event::with('attendee', 'attendee.user', 'user')->findOrFail($id)->withCustomFields();
        $attendeesIds = $this->event->attendee->pluck('user_id')->toArray();
        $mentionUser = $this->event->mentionEvent->pluck('user_id')->toArray();

        $userId = UserService::getUserId();

        abort_403(!(
            $this->viewPermission == 'all'
            || ($this->viewPermission == 'added' && $this->event->added_by == $userId)
            || ($this->viewPermission == 'owned' && in_array($userId, $attendeesIds) || $this->event->host == $userId)
            || ($this->viewPermission == 'both' && (in_array($userId, $attendeesIds) || $this->event->added_by == $userId) || (!is_null(($this->event->mentionEvent))) && in_array($userId, $mentionUser) || $this->event->host == $userId)
        ));

        $getCustomFieldGroupsWithFields = $this->event->getCustomFieldGroupsWithFields();

        if ($getCustomFieldGroupsWithFields) {
            $this->fields = $getCustomFieldGroupsWithFields->fields;
        }

        $this->pageTitle = __('app.menu.event') . ' ' . __('app.details');
        $this->view = 'event-calendar.ajax.show';

        if (request()->ajax()) {
            return $this->returnAjax($this->view);
        }

        return view('event-calendar.create', $this->data);
    }

    public function destroy($id)
    {
        $this->deletePermission = user()->permission('delete_events');
        $event = Event::with('attendee', 'attendee.user')->findOrFail($id);
        $attendeesIds = $event->attendee->pluck('user_id')->toArray();

        $userId = UserService::getUserId();

        abort_403(!($this->deletePermission == 'all'
            || ($this->deletePermission == 'added' && $event->added_by == $userId)
            || ($this->deletePermission == 'owned' && in_array($userId, $attendeesIds))
            || ($this->deletePermission == 'both' && (in_array($userId, $attendeesIds) || $event->added_by == $userId))
        ));

        if ($event->parent_id && request()->delete == 'all') {
            $id = $event->parent_id;
        }

        Event::destroy($id);
        return Reply::successWithData(__('messages.deleteSuccess'), ['redirectUrl' => route('events.index')]);
    }

    public function monthlyOn(Request $request)
    {
        $date = Carbon::createFromFormat($this->company->date_format, $request->date);

        $week = __('app.eventDay.' . $date->weekOfMonth);
        $day = $date->translatedFormat('l');

        return Reply::dataOnly(['message' => __('app.eventMonthlyOn', ['week' => $week, 'day' => $day])]);
    }

    public function updateStatus(StoreEventNote $request, $id)
    {
        $event = Event::findOrFail($id);
        $attendees = $event->attendee->pluck('user');

        $event->status = $request->status;
        $event->note = $request->note;
        $event->update();

        if ($request->status == 'cancelled') {
            event(new EventStatusNoteEvent($event, $attendees));
        } elseif ($request->status == 'completed') {
            event(new EventCompletedEvent($event, $attendees));
        }

        return Reply::success(__('messages.updateSuccess'));
    }

    public function eventStatusNote(Request $request, $id)
    {
        $this->event = Event::findOrFail($id);
        $this->status = $request->status;

        return view('event-calendar.event-status-note', $this->data);
    }
}
