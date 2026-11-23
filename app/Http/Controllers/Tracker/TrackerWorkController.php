<?php

namespace App\Http\Controllers\Tracker;

use App\Helper\Reply;
use App\Models\Project;
use App\Models\ProjectTimeLog;
use App\Models\ProjectTypeStage;
use App\Models\Task;
use App\Models\TaskboardColumn;
use App\Services\Tracker\Capacity;
use App\Services\Tracker\ProgressLog;
use App\Services\Tracker\ProjectTracker;
use App\Services\Tracker\TrackerAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * My Space -> My Work: an employee's open work, due first, and one update for an item - the hours
 * spent (a timesheet entry), the units done and the step it is at - instead of three screens.
 * A blocker is raised with the app's Roadblock form (TaskBlockerService). Works without access to
 * the Projects module: only the items they are on.
 */
class TrackerWorkController extends TrackerBaseController
{

    public function index(Request $request, Capacity $capacity)
    {
        $this->pageTitle = 'My Work';
        $me = user()->id;
        $today = now()->startOfDay();
        $completed = TaskboardColumn::completeColumn()?->id;

        $tasks = Task::with(['project:id,project_name,project_type_id', 'project.projectType.stages', 'trackerStage', 'trackerParent:id,heading',
                'boardColumn:id,column_name,slug,label_color'])
            ->whereHas('users', fn ($q) => $q->where('users.id', $me))
            ->where(fn ($q) => $q->whereNull('work_type')->orWhere('work_type', '!=', 'epic'))
            ->when($completed, fn ($q) => $q->where('board_column_id', '!=', $completed))
            ->when($request->project, fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->search, fn ($q, $s) => $q->where('heading', 'like', "%$s%"))
            ->orderByRaw('due_date is null, due_date')->limit(150)->get();

        $ids = $tasks->pluck('id');
        $this->logged = DB::table('project_time_logs')->whereIn('task_id', $ids)->groupBy('task_id')->selectRaw('task_id, sum(total_minutes) / 60 as h')->pluck('h', 'task_id');
        $this->mine = DB::table('project_time_logs')->whereIn('task_id', $ids)->where('user_id', $me)->groupBy('task_id')->selectRaw('task_id, sum(total_minutes) / 60 as h')->pluck('h', 'task_id');
        $this->blockers = DB::table('task_blockers as b')->leftJoin('users as u', 'u.id', '=', 'b.blocking_user_id')->whereIn('b.task_id', $ids)->where('b.status', 'open')
            ->get(['b.task_id', 'b.need', 'b.created_at', 'u.name'])->groupBy('task_id');
        $this->sentBack = DB::table('tracker_progress_logs')->whereIn('task_id', $ids)->where('rework', 1)->where('logged_at', '>=', now()->subDays(14))
            ->orderByDesc('logged_at')->get()->unique('task_id')->keyBy('task_id');

        // grouped: held up, late, this week, later, no date
        $endOfWeek = $today->copy()->endOfWeek(Carbon::SUNDAY);
        $this->groups = collect([
            'blocked' => ['Held up', 'fa-hand-paper', 'red'],
            'late' => ['Late', 'fa-exclamation-circle', 'red'],
            'today' => ['Due today', 'fa-bolt', 'amber'],
            'week' => ['This week', 'fa-calendar-week', 'blue'],
            'later' => ['Later', 'fa-calendar', 'grey'],
            'none' => ['No due date', 'fa-inbox', 'grey'],
        ])->map(fn ($g, $key) => ['label' => $g[0], 'icon' => $g[1], 'tone' => $g[2], 'items' => collect()]);

        foreach ($tasks as $task) {
            $due = $task->due_date?->copy()->startOfDay();
            $key = $this->blockers->has($task->id) || $task->boardColumn?->slug === 'on_hold' ? 'blocked'
                : (!$due ? 'none' : ($due->lt($today) ? 'late' : ($due->eq($today) ? 'today' : ($due->lte($endOfWeek) ? 'week' : 'later'))));
            $this->groups[$key]['items']->push($task);
        }

        // the day and the week
        $from = $today->copy()->startOfWeek(Carbon::MONDAY);
        $week = $capacity->window([$me], $from, $endOfWeek->copy()->startOfDay())[$me] ?? ['capacity' => 0, 'booked' => 0];
        $this->today = [
            'logged' => round(DB::table('project_time_logs')->where('user_id', $me)->whereBetween('start_time', [$today, $today->copy()->endOfDay()])->sum('total_minutes') / 60, 1),
            'day' => $capacity->hoursPerDay(),
            'week_logged' => round(DB::table('project_time_logs')->where('user_id', $me)->whereBetween('start_time', [$from, $endOfWeek])->sum('total_minutes') / 60, 1),
            'week_capacity' => round($week['capacity'] ?? 0, 1),
            'open' => $tasks->count(),
            'late' => $this->groups['late']['items']->count(),
            'blocked' => $this->groups['blocked']['items']->count(),
        ];
        $this->projectsList = Project::whereIn('id', Task::whereHas('users', fn ($q) => $q->where('users.id', $me))->select('project_id'))
            ->orderBy('project_name')->get(['id', 'project_name']);

        return view('project-tracker.work.index', $this->data);
    }

    /** The update panel for one item. */
    public function edit($taskId)
    {
        $this->task = $this->myTask($taskId);
        $this->stages = $this->task->project?->projectType?->stages ?? collect();
        $this->unit = $this->task->project?->projectType?->unitText();
        $this->history = ProgressLog::history($this->task);
        $this->loggedToday = round(DB::table('project_time_logs')->where('user_id', user()->id)
            ->whereBetween('start_time', [now()->startOfDay(), now()->endOfDay()])->sum('total_minutes') / 60, 2);
        $this->spent = round(DB::table('project_time_logs')->where('task_id', $this->task->id)->sum('total_minutes') / 60, 1);
        $this->blocker = DB::table('task_blockers as b')->leftJoin('users as u', 'u.id', '=', 'b.blocking_user_id')
            ->where('b.task_id', $this->task->id)->where('b.status', 'open')->get(['b.need', 'u.name']);

        return $this->panel('project-tracker.work.form', 'Update — ' . $this->task->heading);
    }

    /** One save: hours on a day, units done so far, the step. */
    public function update(Request $request, $taskId)
    {
        $task = $this->myTask($taskId);
        $format = company()->date_format;
        $request->validate([
            'date' => 'required|date_format:' . $format,
            'hours' => 'nullable|integer|min:0|max:16',
            'minutes' => 'nullable|integer|min:0|max:59',
            'memo' => 'nullable|string|max:1000',
            'done_units' => 'nullable|numeric|min:0',
            'stage_id' => 'nullable|integer',
            'reason' => 'nullable|string|max:1000',
        ], ['date.*' => 'Pick the day the work was done.', 'hours.*' => 'Hours: 0 to 16.', 'minutes.*' => 'Minutes: 0 to 59.']);

        $tz = company()->timezone;
        $day = Carbon::createFromFormat($format, $request->date, $tz)->startOfDay();
        if ($day->gt(now($tz)->startOfDay())) {
            $this->fail('Hours cannot be logged for a day that has not come yet.');
        }
        if ($day->lt(now($tz)->startOfDay()->subDays(7))) {
            $this->fail('Hours older than a week go through the timesheet.');
        }

        $minutes = (int) $request->hours * 60 + (int) $request->minutes;
        $stage = $request->stage_id ? ProjectTypeStage::where('project_type_id', $task->project?->project_type_id)->find($request->stage_id) : null;
        $units = $request->filled('done_units') && $task->planned_units !== null ? min((float) $request->done_units, (float) $task->planned_units) : null;
        $before = ProgressLog::snapshot($task);
        $back = $stage && ProgressLog::isBack($before['stage'], $stage->id);

        if (!$minutes && $units === null && (!$stage || $stage->id === $before['stage'])) {
            $this->fail('Nothing to save: enter the hours, the units done or a new step.');
        }
        if ($back && !trim((string) $request->reason)) {
            $this->fail('It goes back to ' . $stage->name . ': say what needs fixing.');
        }

        // the hours: a timesheet entry on that day, after what is already logged that day
        $log = null;
        if ($minutes) {
            $dayStartUtc = $day->copy()->setTimezone('UTC');
            $dayEndUtc = $day->copy()->endOfDay()->setTimezone('UTC');
            $already = (int) DB::table('project_time_logs')->where('user_id', user()->id)->whereBetween('start_time', [$dayStartUtc, $dayEndUtc])->sum('total_minutes');
            if ($already + $minutes > 16 * 60) {
                $this->fail('That makes more than 16 hours on ' . $day->format($format) . ' (' . round($already / 60, 1) . ' h already logged).');
            }
            if (DB::table('project_time_logs')->where('user_id', user()->id)->whereNull('end_time')->whereBetween('start_time', [$dayStartUtc, $dayEndUtc])->exists()) {
                $this->fail('A timer is running that day. Stop it first, then add the hours.');
            }
            $lastEnd = DB::table('project_time_logs')->where('user_id', user()->id)->whereBetween('start_time', [$dayStartUtc, $dayEndUtc])->max('end_time');
            $start = $lastEnd ? Carbon::parse($lastEnd, 'UTC') : $day->copy()->setTime(9, 30)->setTimezone('UTC');
            $start = $start->max($day->copy()->setTimezone('UTC'));
            $end = $start->copy()->addMinutes($minutes);
            if ($end->gt($dayEndUtc)) {   // late in the day: fit it before midnight
                $end = $dayEndUtc->copy()->startOfMinute();
                $start = $end->copy()->subMinutes($minutes);
            }

            $log = new ProjectTimeLog();
            $log->project_id = $task->project_id;
            $log->task_id = $task->id;
            $log->user_id = user()->id;
            $log->start_time = $start;
            $log->end_time = $end;
            $log->total_hours = intdiv($minutes, 60);
            $log->total_minutes = $minutes;
            $log->hourly_rate = 0;
            $log->memo = $request->memo ?: ($task->heading);
            $log->edited_by_user = user()->id;
        }

        DB::transaction(function () use ($task, $log, $units, $stage) {
            $log?->save();   // observer: rate, earnings, approval

            if ($units !== null) {
                $task->done_units = $units;
            }
            if ($stage && $stage->id !== $task->project_stage_id) {
                ProjectTracker::moveTo($task, $stage);
            }
            $task->save();
        });

        ProgressLog::record($task, $before, null, $back ? trim(mb_substr((string) $request->reason, 0, 1000)) : null);

        if ($log) {
            $this->tellApprover($log);
        }

        $done = array_filter([
            $minutes ? (intdiv($minutes, 60) ? intdiv($minutes, 60) . ' h ' : '') . ($minutes % 60 ? ($minutes % 60) . ' min ' : '') . 'logged' : null,
            $units !== null && abs($units - $before['units']) > 0.0001 ? rtrim(rtrim(number_format($units, 2), '0'), '.') . ' done' : null,
            $stage && $stage->id !== $before['stage'] ? ($back ? 'sent back to ' : 'moved to ') . $stage->name : null,
        ]);

        return Reply::success(ucfirst(implode(', ', $done)) . '.');
    }

    /** An item of theirs (or one they manage), still open. */
    private function myTask($taskId): Task
    {
        $task = Task::with('project.projectType.stages', 'users:id')->findOrFail($taskId);
        abort_403(!($task->users->contains('id', user()->id) || ($task->project && TrackerAccess::manage($task->project))));

        return $task;
    }

    /** Timesheets that need approval: the reporting manager hears of it, as with the timesheet form. */
    private function tellApprover(ProjectTimeLog $log): void
    {
        $setting = \App\Models\LogTimeFor::first();
        $managerId = user()->employeeDetail?->reporting_to;

        if ($setting?->approval_required && $managerId && ($manager = \App\Models\User::find($managerId))) {
            rescue(fn () => $manager->notify(new \App\Notifications\ProjectTimelogCreateNotification($log)), null, false);
        }
    }

}
