<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Leave;
use App\Models\Project;
use App\Models\SubTask;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskMeetingUpdate;
use App\Services\TaskStatusTracker;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Task meetings: request a meeting or discussion about a task, collect accept / decline,
 * record the outcome, turn follow-up actions into sub-tasks, and log meeting time for
 * the attendees who accepted. Meetings are Events, so they show in My Calendar and use
 * the existing event reminders.
 */
class TaskMeetingController extends AccountBaseController
{

    public const DURATIONS = [15, 30, 45, 60, 90, 120];

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'modules.tasks.meetings';
        $this->middleware(function ($request, $next) {
            abort_403(!in_array('tasks', $this->user->modules));

            return $next($request);
        });
    }

    /**
     * Request-meeting form (modal). With follow_up_of, it is prefilled from that meeting.
     */
    public function create(Request $request)
    {
        $this->task = Task::with('users')->findOrFail($request->task_id);
        abort_403(!$this->canOrganise($this->task));

        $this->followUpOf = $request->follow_up_of ? Event::with('attendee')->where('task_id', $this->task->id)->findOrFail($request->follow_up_of) : null;
        $this->meeting = null;
        $this->employees = User::allEmployees(null, true);
        $this->durations = self::DURATIONS;

        if ($this->followUpOf) {
            $this->selectedAttendees = $this->followUpOf->attendee->pluck('user_id')->all();
            $this->defaultTitle = __('modules.tasks.meetingFollowUpTitle', ['meeting' => $this->followUpOf->event_name]);
            $this->defaultAgenda = $this->followUpOf->meeting_outcome ?? '';
        }
        else {
            // The people working on the task, who created it, and the project admin
            $projectAdmin = $this->task->project_id ? Project::withTrashed()->where('id', $this->task->project_id)->value('project_admin') : null;
            $this->selectedAttendees = $this->task->users->pluck('id')->push($this->task->added_by)->push($projectAdmin)->filter()->unique()->values()->all();
            $this->defaultTitle = __('modules.tasks.meetingTitleDefault', ['task' => $this->task->heading]);
            $this->defaultAgenda = '';
        }

        $this->selectedAttendees = array_values(array_diff($this->selectedAttendees, [user()->id]));
        $this->defaultStart = now(company()->timezone)->addHour()->startOfHour();
        $this->defaultDuration = 30;

        return view('task-meetings.form', $this->data);
    }

    public function store(Request $request)
    {
        $task = Task::with('users')->findOrFail($request->task_id);
        abort_403(!$this->canOrganise($task));

        [$start, $end] = $this->validated($request);

        $followUpOf = $request->follow_up_of ? Event::where('task_id', $task->id)->find($request->follow_up_of) : null;
        $attendeeIds = collect($request->attendee_ids)->map(fn ($id) => (int) $id)->reject(fn ($id) => $id == user()->id)->unique()->values();

        $meeting = DB::transaction(function () use ($request, $task, $start, $end, $followUpOf, $attendeeIds) {
            $meeting = new Event();
            $meeting->task_id = $task->id;
            $meeting->follow_up_of = $followUpOf?->id;
            $this->fill($meeting, $request, $start, $end);
            $meeting->host = user()->id;
            $meeting->status = 'pending';
            $meeting->repeat = 'no';
            $meeting->repeat_type = 'day';
            $meeting->note = '';
            $meeting->label_color = '#1d82f5';
            $meeting->save();

            // The organiser attends too, so it shows in their calendar
            EventAttendee::create(['event_id' => $meeting->id, 'user_id' => user()->id, 'response' => 'accepted', 'responded_at' => now()]);

            foreach ($attendeeIds as $userId) {
                EventAttendee::firstOrCreate(['event_id' => $meeting->id, 'user_id' => $userId]);
            }

            $this->logTaskActivity($task->id, user()->id, 'meetingScheduledActivity');

            return $meeting;
        });

        $attendees = User::whereIn('id', $attendeeIds)->get();
        Notification::send($attendees, new TaskMeetingUpdate($meeting, 'invited', user()));

        return Reply::success(__('messages.meetingScheduled', ['count' => $attendees->count()]) . $this->leaveWarning($attendees, $start));
    }

    /**
     * Reschedule form (modal).
     */
    public function edit($id)
    {
        $this->meeting = Event::with('attendee', 'task.users')->whereNotNull('task_id')->findOrFail($id);
        abort_403(!$this->canManage($this->meeting));

        $this->task = $this->meeting->task;
        $this->followUpOf = null;
        $this->employees = User::allEmployees(null, true);
        $this->durations = self::DURATIONS;
        $this->selectedAttendees = $this->meeting->attendee->pluck('user_id')->reject(fn ($id) => $id == $this->meeting->host)->values()->all();
        $this->defaultTitle = $this->meeting->event_name;
        $this->defaultAgenda = $this->meeting->description;
        $this->defaultStart = $this->meeting->start_date_time->copy()->tz(company()->timezone);
        $this->defaultDuration = (int) abs($this->meeting->end_date_time->diffInMinutes($this->meeting->start_date_time));

        return view('task-meetings.form', $this->data);
    }

    /**
     * Reschedule: new time / place / attendees. Everyone is asked to respond again.
     */
    public function update(Request $request, $id)
    {
        $meeting = Event::with('attendee', 'task')->whereNotNull('task_id')->findOrFail($id);
        abort_403(!$this->canManage($meeting));

        if ($meeting->status != 'pending') {
            return Reply::error(__('messages.meetingClosed', ['status' => __('modules.tasks.meeting' . ucfirst($meeting->status))]));
        }

        [$start, $end] = $this->validated($request);
        $attendeeIds = collect($request->attendee_ids)->map(fn ($v) => (int) $v)->reject(fn ($v) => $v == $meeting->host)->unique()->values();

        DB::transaction(function () use ($request, $meeting, $start, $end, $attendeeIds) {
            $this->fill($meeting, $request, $start, $end);
            $meeting->save();

            EventAttendee::where('event_id', $meeting->id)
                ->where('user_id', '!=', $meeting->host)
                ->whereNotIn('user_id', $attendeeIds)
                ->delete();

            foreach ($attendeeIds as $userId) {
                EventAttendee::updateOrCreate(
                    ['event_id' => $meeting->id, 'user_id' => $userId],
                    ['response' => 'pending', 'responded_at' => null]
                );
            }
        });

        $attendees = User::whereIn('id', $attendeeIds)->get();
        Notification::send($attendees, new TaskMeetingUpdate($meeting->fresh(), 'rescheduled', user()));

        return Reply::success(__('messages.meetingRescheduled') . $this->leaveWarning($attendees, $start));
    }

    /**
     * Attendee accepts or declines.
     */
    public function respond(Request $request, $id)
    {
        $meeting = Event::whereNotNull('task_id')->findOrFail($id);
        $this->validate($request, ['response' => 'required|in:accepted,declined']);

        $attendee = EventAttendee::where('event_id', $meeting->id)->where('user_id', user()->id)->first();
        abort_403(!$attendee);

        $attendee->response = $request->response;
        $attendee->responded_at = now();
        $attendee->save();

        $host = $meeting->host ? User::find($meeting->host) : null;

        if ($request->response == 'declined' && $host && $host->id != user()->id) {
            $host->notify(new TaskMeetingUpdate($meeting, 'declined', user()));
        }

        return Reply::success(__('messages.meetingResponseSaved'));
    }

    public function cancel($id)
    {
        $meeting = Event::with('attendee')->whereNotNull('task_id')->findOrFail($id);
        abort_403(!$this->canManage($meeting));

        if ($meeting->status != 'pending') {
            return Reply::error(__('messages.meetingClosed', ['status' => __('modules.tasks.meeting' . ucfirst($meeting->status))]));
        }

        $meeting->status = 'cancelled';
        $meeting->send_reminder = 'no';
        $meeting->save();

        $this->logTaskActivity($meeting->task_id, user()->id, 'meetingCancelledActivity');

        $attendees = User::whereIn('id', $meeting->attendee->pluck('user_id'))->where('id', '!=', user()->id)->get();
        Notification::send($attendees, new TaskMeetingUpdate($meeting, 'cancelled', user()));

        return Reply::success(__('messages.meetingCancelled'));
    }

    /**
     * Minutes form (modal): outcome + follow-up actions.
     */
    public function minutes($id)
    {
        $this->meeting = Event::with('attendee.user', 'task.users')->whereNotNull('task_id')->findOrFail($id);
        abort_403(!$this->canManage($this->meeting));

        // Owners for follow-up actions: attendees and the task's assignees
        $this->owners = $this->meeting->attendee->pluck('user')->filter()
            ->merge($this->meeting->task->users)
            ->unique('id')
            ->where('status', 'active')
            ->values();

        return view('task-meetings.minutes', $this->data);
    }

    /**
     * Save minutes: meeting completed, follow-up actions become sub-tasks, and the meeting
     * time is logged for every attendee who accepted.
     */
    public function storeMinutes(Request $request, $id)
    {
        $meeting = Event::with('attendee.user', 'task')->whereNotNull('task_id')->findOrFail($id);
        abort_403(!$this->canManage($meeting));

        if ($meeting->status != 'pending') {
            return Reply::error(__('messages.meetingClosed', ['status' => __('modules.tasks.meeting' . ucfirst($meeting->status))]));
        }

        $this->validate($request, [
            'meeting_outcome' => 'required|string|max:5000',
            'actions' => 'nullable|array',
            'actions.*.title' => 'nullable|string|max:255',
            'actions.*.assigned_to' => 'nullable|integer',
            'actions.*.due_date' => 'nullable|date_format:' . company()->date_format,
        ], [], [
            'meeting_outcome' => __('modules.tasks.meetingOutcome'),
        ]);

        $actions = collect($request->actions)->filter(fn ($action) => trim($action['title'] ?? '') !== '');

        [$actionCount, $loggedCount] = DB::transaction(function () use ($meeting, $request, $actions) {
            $meeting->meeting_outcome = $request->meeting_outcome;
            $meeting->completed_at = now();
            $meeting->status = 'completed';
            $meeting->send_reminder = 'no';
            $meeting->save();

            foreach ($actions as $action) {
                $subTask = new SubTask();
                $subTask->task_id = $meeting->task_id;
                $subTask->title = $action['title'];
                $subTask->description = __('modules.tasks.meetingTimeLogMemo', ['meeting' => $meeting->event_name]);
                $subTask->assigned_to = !empty($action['assigned_to']) ? $action['assigned_to'] : null;
                $subTask->start_date = now(company()->timezone)->toDateString();
                $subTask->due_date = !empty($action['due_date']) ? Carbon::createFromFormat(company()->date_format, $action['due_date'])->toDateString() : null;
                $subTask->status = 'incomplete';
                $subTask->save();
            }

            // Meeting time goes to the timesheet of everyone who accepted
            $tracker = new TaskStatusTracker();
            $memo = __('modules.tasks.meetingTimeLogMemo', ['meeting' => $meeting->event_name]);
            $logged = 0;

            foreach ($meeting->attendee->where('response', 'accepted') as $attendee) {
                if ($attendee->user) {
                    $tracker->logFinishedTime($meeting->task, $attendee->user, $meeting->start_date_time, $meeting->end_date_time, $memo);
                    $logged++;
                }
            }

            $this->logTaskActivity($meeting->task_id, user()->id, 'meetingCompletedActivity');

            return [$actions->count(), $logged];
        });

        $attendees = User::whereIn('id', $meeting->attendee->pluck('user_id'))->where('id', '!=', user()->id)->get();
        Notification::send($attendees, new TaskMeetingUpdate($meeting, 'minutes', user()));

        return Reply::success(__('messages.meetingMinutesSaved', ['actions' => $actionCount, 'people' => $loggedCount]));
    }

    /**
     * @return Carbon[] [start, end] in UTC
     */
    private function validated(Request $request): array
    {
        $this->validate($request, [
            'event_name' => 'required|string|max:191',
            'description' => 'nullable|string|max:5000',
            'meeting_date' => 'required|date_format:' . company()->date_format,
            'start_time' => 'required',
            'duration' => 'required|integer|in:' . implode(',', self::DURATIONS),
            'attendee_ids' => 'required|array|min:1',
            'event_link' => 'nullable|url|max:191',
            'where' => 'nullable|string|max:191',
        ], [], [
            'event_name' => __('modules.tasks.meetingTitle'),
            'meeting_date' => __('modules.tasks.meetingDate'),
            'start_time' => __('modules.tasks.meetingStartTime'),
            'attendee_ids' => __('modules.tasks.meetingAttendees'),
            'event_link' => __('modules.tasks.meetingLink'),
        ]);

        $start = Carbon::createFromFormat(
            company()->date_format . ' ' . company()->time_format,
            $request->meeting_date . ' ' . $request->start_time,
            company()->timezone
        )->setTimezone('UTC');

        return [$start, $start->copy()->addMinutes((int) $request->duration)];
    }

    private function fill(Event $meeting, Request $request, Carbon $start, Carbon $end): void
    {
        $meeting->event_name = $request->event_name;
        $meeting->description = $request->description ?? '';
        $meeting->start_date_time = $start;
        $meeting->end_date_time = $end;
        $meeting->event_link = $request->event_link;
        $meeting->where = $request->where ?: ($request->event_link ? __('modules.tasks.meetingOnline') : '--');
        $meeting->send_reminder = $request->send_reminder ? 'yes' : 'no';
        $meeting->remind_time = 15;
        $meeting->remind_type = 'minute';
    }

    private function leaveWarning($attendees, Carbon $start): string
    {
        $date = $start->copy()->tz(company()->timezone)->toDateString();

        $onLeave = Leave::whereIn('user_id', $attendees->pluck('id'))
            ->whereIn('status', ['approved', 'pending'])
            ->whereDate('leave_date', $date)
            ->pluck('user_id');

        if ($onLeave->isEmpty()) {
            return '';
        }

        return ' ' . __('messages.meetingAttendeesOnLeave', ['names' => $attendees->whereIn('id', $onLeave)->pluck('name')->implode(', ')]);
    }

    /**
     * Anyone working on the task can ask for a meeting about it.
     */
    private function canOrganise(Task $task): bool
    {
        return in_array('admin', user_roles())
            || $task->users->contains('id', user()->id)
            || $task->added_by == user()->id
            || $task->isProjectAdmin()
            || $task->canChangeStatus();
    }

    /**
     * The organiser, admins and the project admin can reschedule, cancel or record minutes.
     */
    private function canManage(Event $meeting): bool
    {
        return $meeting->host == user()->id
            || in_array('admin', user_roles())
            || ($meeting->task && $meeting->task->isProjectAdmin());
    }

}
