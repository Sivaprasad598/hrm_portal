<?php

namespace App\Services;

use App\Http\Controllers\AccountBaseController;
use App\Models\EmployeeDetails;
use App\Models\LogTimeFor;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectTimeLog;
use App\Models\Task;
use App\Models\TaskboardColumn;
use App\Models\TaskStatusLog;
use App\Models\User;
use App\Traits\ProjectProgress;
use Carbon\Carbon;

/**
 * Records every task status change and keeps the timesheet in step with it:
 *
 * - Started (slug `doing`): starts a timer for the person who changed the status,
 *   if they are assigned to the task. Any other timer they have running is stopped
 *   first, since a person can only have one running timer.
 * - Any other status (On Hold, Completed, Waiting Approval, To Do, ...): stops every
 *   running timer on the task and saves those time logs.
 */
class TaskStatusTracker
{

    use ProjectProgress;

    public const STARTED = 'doing';
    public const ON_HOLD = 'on_hold';

    public function handle(Task $task, $fromColumnId): void
    {
        $this->logStatusChange($task, $fromColumnId);

        // Look the column up fresh: $task->boardColumn may still hold the old status
        $slug = TaskboardColumn::withoutGlobalScopes()->where('id', $task->board_column_id)->value('slug');

        if ($slug == self::STARTED) {
            $this->startTimerForCurrentUser($task);

            return;
        }

        $this->stopTaskTimers($task);
    }

    public function logStatusChange(Task $task, $fromColumnId): TaskStatusLog
    {
        $now = now();
        $previous = TaskStatusLog::withoutGlobalScopes()
            ->where('task_id', $task->id)
            ->latest('changed_at')
            ->latest('id')
            ->first();

        // For the first change of an older task, count from when the task was created
        $since = $previous ? $previous->changed_at : ($fromColumnId ? $task->created_at : null);

        $log = new TaskStatusLog();
        $log->company_id = $task->company_id;
        $log->task_id = $task->id;
        $log->user_id = user() ? user()->id : null;
        $log->from_board_column_id = $fromColumnId;
        $log->to_board_column_id = $task->board_column_id;
        $log->changed_at = $now;
        $log->seconds_in_previous = $since ? abs($now->diffInSeconds($since)) : null;
        $log->save();

        return $log;
    }

    public function startTimerForCurrentUser(Task $task): ?ProjectTimeLog
    {
        $user = user();

        // Only the assignee who changed the status gets a timer; an admin changing
        // someone else's task only records the status time
        if (!$user || !$task->users()->where('users.id', $user->id)->exists()) {
            return null;
        }

        $alreadyRunning = ProjectTimeLog::where('task_id', $task->id)
            ->where('user_id', $user->id)
            ->whereNull('end_time')
            ->exists();

        if ($alreadyRunning) {
            return null;
        }

        $project = $task->project_id ? Project::withTrashed()->find($task->project_id) : null;

        if ($project && $project->calculate_task_progress == 'project_total_time' && !$this->hasProjectRemainingTime($project->id)) {
            return null;
        }

        // One running timer per person: close whatever else they were timing
        ProjectTimeLog::with('activeBreak')
            ->where('user_id', $user->id)
            ->whereNull('end_time')
            ->get()
            ->each(fn ($timeLog) => $this->stopTimer($timeLog));

        $timeLog = new ProjectTimeLog();
        $timeLog->company_id = $task->company_id;
        $timeLog->project_id = $task->project_id;
        $timeLog->task_id = $task->id;
        $timeLog->user_id = $user->id;
        $timeLog->start_time = now();
        $timeLog->memo = __('modules.tasks.autoTimeLogMemo', ['task' => $task->heading]);
        $timeLog->hourly_rate = (int) $this->hourlyRate($user, $project);
        $timeLog->earnings = 0;
        $timeLog->added_by = $user->id;
        $timeLog->last_updated_by = $user->id;

        if (LogTimeFor::where('company_id', $task->company_id)->value('approval_required')) {
            $timeLog->approved = 0;
            $timeLog->rejected = 0;
        }

        // Saved quietly: the time log observer reads request('user_id'), which on the
        // task edit form is the list of assignees, not the timer owner
        $timeLog->saveQuietly();

        (new AccountBaseController())->logTaskActivity($task->id, $user->id, 'timerStartedBy');

        return $timeLog;
    }

    public function stopTaskTimers(Task $task): void
    {
        ProjectTimeLog::with('activeBreak')
            ->where('task_id', $task->id)
            ->whereNull('end_time')
            ->get()
            ->each(fn ($timeLog) => $this->stopTimer($timeLog));
    }

    public function stopTimer(ProjectTimeLog $timeLog): void
    {
        $end = now();

        if ($timeLog->activeBreak) {
            $activeBreak = $timeLog->activeBreak;
            $activeBreak->end_time = $end;
            $activeBreak->total_minutes = abs((int) $end->diffInMinutes($activeBreak->start_time));
            $activeBreak->total_hours = abs((int) $end->diffInHours($activeBreak->start_time));
            $activeBreak->save();
        }

        $breakMinutes = (int) $timeLog->breaks()->sum('total_minutes');

        $timeLog->end_time = $end;
        $timeLog->total_hours = abs((int) $end->diffInHours($timeLog->start_time));
        $timeLog->total_minutes = abs((int) $end->diffInMinutes($timeLog->start_time));
        $timeLog->total_break_minutes = $breakMinutes;
        $timeLog->earnings = round(max(0, $timeLog->total_minutes - $breakMinutes) * ($timeLog->hourly_rate / 60), 2);
        $timeLog->edited_by_user = user() ? user()->id : $timeLog->edited_by_user;
        $timeLog->saveQuietly();

        if ($timeLog->task_id && user()) {
            (new AccountBaseController())->logTaskActivity($timeLog->task_id, user()->id, 'timerStoppedBy');
        }
    }

    /**
     * Log a finished block of time on a task, e.g. a task meeting an attendee took part in.
     */
    public function logFinishedTime(Task $task, User $user, Carbon $start, Carbon $end, string $memo): ProjectTimeLog
    {
        $project = $task->project_id ? Project::withTrashed()->find($task->project_id) : null;
        $minutes = abs((int) $end->diffInMinutes($start));

        $timeLog = new ProjectTimeLog();
        $timeLog->company_id = $task->company_id;
        $timeLog->project_id = $task->project_id;
        $timeLog->task_id = $task->id;
        $timeLog->user_id = $user->id;
        $timeLog->start_time = $start;
        $timeLog->end_time = $end;
        $timeLog->total_minutes = $minutes;
        $timeLog->total_hours = intdiv($minutes, 60);
        $timeLog->memo = $memo;
        $timeLog->hourly_rate = (int) $this->hourlyRate($user, $project);
        $timeLog->earnings = round($minutes * ($timeLog->hourly_rate / 60), 2);
        $timeLog->added_by = user() ? user()->id : $user->id;
        $timeLog->last_updated_by = $timeLog->added_by;

        if (LogTimeFor::where('company_id', $task->company_id)->value('approval_required')) {
            $timeLog->approved = 0;
            $timeLog->rejected = 0;
        }

        $timeLog->saveQuietly();

        return $timeLog;
    }

    private function hourlyRate(User $user, ?Project $project): float
    {
        if ($project && !$project->public) {
            $rate = ProjectMember::where('project_id', $project->id)->where('user_id', $user->id)->value('hourly_rate');

            if (!is_null($rate)) {
                return (float) $rate;
            }
        }

        return (float) (EmployeeDetails::where('user_id', $user->id)->value('hourly_rate') ?? 0);
    }

}
