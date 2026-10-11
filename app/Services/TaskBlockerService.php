<?php

namespace App\Services;

use App\Http\Controllers\AccountBaseController;
use App\Models\Leave;
use App\Models\Task;
use App\Models\TaskBlocker;
use App\Models\TaskboardColumn;
use App\Notifications\TaskBlockerUpdate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Roadblocks: a task goes On Hold while it waits on one or more teammates, and
 * returns to its previous status once every roadblock is resolved or cancelled.
 */
class TaskBlockerService
{

    /**
     * Raise roadblocks on a task and put it On Hold.
     *
     * @param array $data need, user_ids[], needed_by (Y-m-d|null), urgency, linked_task_id
     * @return Collection<TaskBlocker>
     */
    public function raise(Task $task, array $data): Collection
    {
        $onHold = $this->column($task, TaskStatusTracker::ON_HOLD);

        // If the task is already On Hold, keep the status it had before the first roadblock
        $previousColumnId = $task->board_column_id == $onHold->id
            ? (TaskBlocker::where('task_id', $task->id)->open()->value('previous_board_column_id') ?? $this->column($task, TaskStatusTracker::STARTED)->id)
            : $task->board_column_id;

        $linkedTask = !empty($data['linked_task_id']) ? Task::with('users')->find($data['linked_task_id']) : null;

        $blockers = DB::transaction(function () use ($task, $data, $onHold, $previousColumnId, $linkedTask) {
            $blockers = collect();

            foreach (array_unique($data['user_ids']) as $userId) {
                $blocker = new TaskBlocker();
                $blocker->company_id = $task->company_id;
                $blocker->task_id = $task->id;
                $blocker->raised_by = user()->id;
                $blocker->blocking_user_id = $userId;
                $blocker->need = $data['need'];
                $blocker->needed_by = $data['needed_by'] ?? null;
                $blocker->urgency = $data['urgency'] ?? 'normal';
                $blocker->previous_board_column_id = $previousColumnId;

                // The linked task is only attached to the roadblock of the person who owns it
                if ($linkedTask && $linkedTask->users->contains('id', $userId)) {
                    $blocker->linked_task_id = $linkedTask->id;
                }

                $blocker->save();
                $blockers->push($blocker);
            }

            if ($task->board_column_id != $onHold->id) {
                $task->board_column_id = $onHold->id;
                $task->status = 'incomplete';
                $task->completed_on = null;
                $task->save(); // observer: status log, timers stopped, status notifications
            }

            (new AccountBaseController())->logTaskActivity($task->id, user()->id, 'blockerRaisedActivity', $onHold->id);

            return $blockers;
        });

        foreach ($blockers as $blocker) {
            $blocker->blockingUser->notify(new TaskBlockerUpdate($blocker, 'raised'));
        }

        return $blockers;
    }

    /**
     * @return string|null name of the status the task returned to, if it left On Hold
     */
    public function resolve(TaskBlocker $blocker, ?string $note): ?string
    {
        $this->close($blocker, 'resolved', $note);

        (new AccountBaseController())->logTaskActivity($blocker->task_id, user()->id, 'blockerResolvedActivity');

        // Tell the person who raised it and everyone working on the task
        $recipients = collect($blocker->task->users->all())
            ->push($blocker->raisedBy)
            ->filter()
            ->unique('id')
            ->reject(fn ($user) => $user->id == user()->id);

        foreach ($recipients as $recipient) {
            $recipient->notify(new TaskBlockerUpdate($blocker, 'resolved'));
        }

        return $this->resumeIfClear($blocker->task);
    }

    /**
     * @return string|null name of the status the task returned to, if it left On Hold
     */
    public function cancel(TaskBlocker $blocker, ?string $note = null): ?string
    {
        $this->close($blocker, 'cancelled', $note);

        (new AccountBaseController())->logTaskActivity($blocker->task_id, user()->id, 'blockerCancelledActivity');

        if ($blocker->blocking_user_id != user()->id) {
            $blocker->blockingUser->notify(new TaskBlockerUpdate($blocker, 'cancelled'));
        }

        return $this->resumeIfClear($blocker->task);
    }

    /**
     * The task left On Hold some other way (completed, moved, deleted): close what is still open.
     */
    public function closeOpenForTask(Task $task, string $reason): void
    {
        $blockers = TaskBlocker::withoutGlobalScopes()->with('task', 'blockingUser', 'raisedBy')
            ->where('task_id', $task->id)
            ->open()
            ->get();

        foreach ($blockers as $blocker) {
            $this->close($blocker, 'cancelled', __('messages.roadblockAutoClosed', ['status' => $reason]));
            $blocker->blockingUser?->notify(new TaskBlockerUpdate($blocker, 'cancelled'));
        }
    }

    /**
     * When nothing is blocking the task any more, put it back to the status it had before.
     */
    public function resumeIfClear(Task $task): ?string
    {
        $task->refresh();
        $onHold = $this->column($task, TaskStatusTracker::ON_HOLD);

        if ($task->board_column_id != $onHold->id || TaskBlocker::where('task_id', $task->id)->open()->exists()) {
            return null;
        }

        $previousColumnId = TaskBlocker::where('task_id', $task->id)
            ->whereNotNull('previous_board_column_id')
            ->latest('id')
            ->value('previous_board_column_id');

        $resumeColumn = TaskboardColumn::where('id', $previousColumnId)->where('slug', '!=', TaskStatusTracker::ON_HOLD)->first()
            ?? $this->column($task, TaskStatusTracker::STARTED);

        $task->board_column_id = $resumeColumn->id;

        if ($resumeColumn->slug == 'completed') {
            $task->status = 'completed';
            $task->completed_on = now()->format('Y-m-d');
        }

        $task->save();

        return $resumeColumn->column_name;
    }

    /**
     * Leave dates (approved or pending) of a person between today and the needed-by date.
     */
    public function leaveDates(int $userId, ?Carbon $neededBy): array
    {
        $from = now(company()->timezone)->startOfDay();
        $to = ($neededBy && $neededBy->gt($from)) ? $neededBy : $from;

        return Leave::where('user_id', $userId)
            ->whereIn('status', ['approved', 'pending'])
            ->whereBetween('leave_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('leave_date')
            ->pluck('leave_date')
            ->map(fn ($date) => Carbon::parse($date)->translatedFormat(company()->date_format))
            ->all();
    }

    private function close(TaskBlocker $blocker, string $status, ?string $note): void
    {
        $blocker->status = $status;
        $blocker->resolution_note = $note;
        $blocker->resolved_by = user() ? user()->id : null;
        $blocker->resolved_at = now();
        $blocker->save();
    }

    private function column(Task $task, string $slug): TaskboardColumn
    {
        return TaskboardColumn::withoutGlobalScopes()
            ->where('company_id', $task->company_id)
            ->where('slug', $slug)
            ->firstOrFail();
    }

}
