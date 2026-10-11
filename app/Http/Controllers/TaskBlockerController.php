<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\Task;
use App\Models\TaskBlocker;
use App\Models\TaskboardColumn;
use App\Models\User;
use App\Services\TaskBlockerService;
use App\Services\TaskStatusTracker;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TaskBlockerController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'modules.tasks.roadblocks';
        $this->middleware(function ($request, $next) {
            abort_403(!in_array('tasks', $this->user->modules));

            return $next($request);
        });
    }

    /**
     * Roadblock report: open roadblocks and how fast each person resolves them.
     */
    public function index(Request $request)
    {
        $isAdmin = in_array('admin', user_roles());

        $query = TaskBlocker::with('task:id,heading,task_short_code,project_id', 'raisedBy', 'blockingUser', 'linkedTask:id,heading')
            ->whereHas('task');

        // Admins see everything; others see roadblocks they raised, are blocking, or on projects they run
        if (!$isAdmin) {
            $userId = user()->id;
            $query->where(function ($q) use ($userId) {
                $q->where('raised_by', $userId)
                    ->orWhere('blocking_user_id', $userId)
                    ->orWhereHas('task.project', fn ($p) => $p->where('project_admin', $userId));
            });
        }

        $this->status = in_array($request->status, ['open', 'resolved', 'cancelled', 'all']) ? $request->status : 'open';
        $this->blockingUserId = $request->blocking_user_id;

        $summaryQuery = clone $query;

        if ($this->status != 'all') {
            $query->where('status', $this->status);
        }

        if ($this->blockingUserId) {
            $query->where('blocking_user_id', $this->blockingUserId);
        }

        $this->blockers = $query->orderByRaw("FIELD(urgency, 'urgent', 'normal')")->latest('id')->paginate(50)->withQueryString();

        // Per person: open count and average time from raised to resolved
        $this->summary = $summaryQuery->with('blockingUser')->get()
            ->groupBy('blocking_user_id')
            ->map(function ($blockers) {
                $resolved = $blockers->where('status', 'resolved')->filter(fn ($b) => $b->resolved_at);

                return [
                    'user' => $blockers->first()->blockingUser,
                    'open' => $blockers->where('status', 'open')->count(),
                    'overdue' => $blockers->filter(fn ($b) => $b->isOverdue())->count(),
                    'resolved' => $resolved->count(),
                    'avg_seconds' => $resolved->isEmpty() ? null : (int) $resolved->avg(fn ($b) => abs($b->resolved_at->diffInSeconds($b->created_at))),
                ];
            })
            ->sortByDesc('open')
            ->values();

        $this->employees = User::allEmployees(null, true);

        return view('task-blockers.index', $this->data);
    }

    /**
     * Raise-roadblock form (modal).
     */
    public function create(Request $request)
    {
        $this->task = Task::with('users')->findOrFail($request->task_id);
        abort_403(!$this->canRaise($this->task));

        $this->employees = User::allEmployees(user()->id, true);

        return view('task-blockers.create', $this->data);
    }

    public function store(Request $request)
    {
        $task = Task::with('users')->findOrFail($request->task_id);
        abort_403(!$this->canRaise($task));

        $this->validate($request, [
            'need' => 'required|string|max:2000',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer',
            'needed_by' => 'nullable|date_format:' . company()->date_format,
            'urgency' => 'required|in:normal,urgent',
            'linked_task_id' => 'nullable|integer',
        ], [], [
            'need' => __('modules.tasks.roadblockNeed'),
            'user_ids' => __('modules.tasks.roadblockWaitingOn'),
            'needed_by' => __('modules.tasks.roadblockNeededBy'),
        ]);

        $userIds = array_map('intval', $request->user_ids);

        if (in_array(user()->id, $userIds)) {
            return Reply::error(__('messages.roadblockSelf'));
        }

        $people = User::withoutGlobalScopes()->whereIn('id', $userIds)->get();

        foreach ($people as $person) {
            if ($person->status != 'active') {
                return Reply::error(__('messages.roadblockInactiveUser', ['name' => $person->name]));
            }
        }

        $neededBy = $request->needed_by ? Carbon::createFromFormat(company()->date_format, $request->needed_by)->startOfDay() : null;

        $service = new TaskBlockerService();
        $service->raise($task, [
            'need' => $request->need,
            'user_ids' => $people->pluck('id')->all(),
            'needed_by' => $neededBy?->toDateString(),
            'urgency' => $request->urgency,
            'linked_task_id' => $request->linked_task_id,
        ]);

        $message = __('messages.roadblockRaised', ['names' => $people->pluck('name')->implode(', ')]);

        // Warn if someone they are waiting on is on leave before the needed-by date
        foreach ($people as $person) {
            $leaveDates = $service->leaveDates($person->id, $neededBy);

            if (!empty($leaveDates)) {
                $message .= ' ' . __('messages.roadblockOnLeave', ['name' => $person->name, 'dates' => implode(', ', $leaveDates)]);
            }
        }

        return Reply::success($message);
    }

    /**
     * Open tasks of the selected people, for the optional "their task that produces it" field.
     */
    public function userTasks(Request $request)
    {
        $userIds = array_filter((array) $request->user_ids);
        $completed = TaskboardColumn::completeColumn();

        $tasks = empty($userIds) ? collect() : Task::with('users:id,name')
            ->whereHas('users', fn ($q) => $q->whereIn('users.id', $userIds))
            ->where('board_column_id', '!=', $completed?->id)
            ->where('id', '!=', $request->task_id)
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'heading', 'task_short_code']);

        $options = '<option value="">--</option>';

        foreach ($tasks as $item) {
            $owners = $item->users->whereIn('id', $userIds)->pluck('name')->implode(', ');
            $options .= '<option value="' . $item->id . '">' . e(($item->task_short_code ? $item->task_short_code . ' · ' : '') . $item->heading . ' (' . $owners . ')') . '</option>';
        }

        return Reply::dataOnly(['status' => 'success', 'data' => $options]);
    }

    /**
     * Resolve / cancel form (modal).
     */
    public function edit(Request $request, $id)
    {
        $this->blocker = TaskBlocker::with('task', 'raisedBy', 'blockingUser')->findOrFail($id);
        $this->action = $request->action == 'cancel' ? 'cancel' : 'resolve';

        abort_403(!($this->action == 'cancel' ? $this->blocker->canCancel() : $this->blocker->canResolve()));

        return view('task-blockers.close', $this->data);
    }

    /**
     * Resolve or cancel a roadblock.
     */
    public function update(Request $request, $id)
    {
        $blocker = TaskBlocker::with('task.users', 'raisedBy', 'blockingUser')->findOrFail($id);

        if ($blocker->status != 'open') {
            return Reply::error(__('messages.roadblockNotOpen'));
        }

        $this->validate($request, ['note' => 'nullable|string|max:2000']);

        $service = new TaskBlockerService();

        if ($request->action == 'cancel') {
            abort_403(!$blocker->canCancel());
            $resumedTo = $service->cancel($blocker, $request->note);
            $message = __('messages.roadblockCancelled');
        }
        else {
            abort_403(!$blocker->canResolve());
            $resumedTo = $service->resolve($blocker, $request->note);
            $message = __('messages.roadblockResolved');
        }

        if ($resumedTo) {
            $message = __('messages.roadblockResolvedTaskResumed', ['status' => $resumedTo]);
        }

        return Reply::success($message);
    }

    /**
     * Assignees can raise roadblocks on their own task even without the
     * "change status" permission; others need that permission.
     */
    private function canRaise(Task $task): bool
    {
        $completedSlugs = ['completed', 'waiting_approval'];
        $slug = TaskboardColumn::where('id', $task->board_column_id)->value('slug');

        return !in_array($slug, $completedSlugs)
            && ($task->users->contains('id', user()->id) || $task->canChangeStatus());
    }

}
