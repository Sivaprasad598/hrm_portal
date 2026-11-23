<?php

namespace App\Services\Tracker;

use App\Models\ProjectTypeStage;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * The history of the work: every change of a tracker item's units done or step, with who did it
 * and the people it is credited to (the item's assignees at that moment). The progress-over-time
 * charts and the employee KPIs are read from it.
 *
 *   $before = ProgressLog::snapshot($task);  ... change and save the task ...  ProgressLog::record($task, $before);
 */
class ProgressLog
{

    /** @return array{stage: ?int, units: float} */
    public static function snapshot(Task $task): array
    {
        return ['stage' => $task->project_stage_id ? (int) $task->project_stage_id : null, 'units' => (float) $task->done_units];
    }

    /**
     * Burn-up: the work done so far, week by week (month by month on long projects), against a straight
     * line from the start to the deadline. Units for unit types, finished work items for hour types.
     *
     * @return array{labels: array, done: array, plan: array, target: float, unit: string}|null
     */
    public static function burnUp(\App\Models\Project $project, array $tracker): ?array
    {
        $units = $tracker['type']?->countsUnits() && $tracker['totals']['planned_units'] > 0;
        $target = $units ? (float) $tracker['totals']['planned_units'] : (float) $tracker['totals']['items'];
        $logs = DB::table('tracker_progress_logs')->where('project_id', $project->id)->orderBy('logged_at')->get(['units_delta', 'rework', 'completed', 'logged_at']);

        if ($target <= 0 || ($logs->isEmpty() && !$project->start_date)) {
            return null;
        }

        $start = ($project->start_date ?? \Carbon\Carbon::parse($logs->first()->logged_at))->copy()->startOfDay();
        if ($logs->isNotEmpty()) {
            $start = $start->min(\Carbon\Carbon::parse($logs->first()->logged_at)->startOfDay());
        }
        $end = now()->startOfDay(); // up to today: the plan line shows where the work should be by now
        $monthly = $start->diffInWeeks($end) > 26;
        $start = $monthly ? $start->startOfMonth() : $start->startOfWeek();
        $planEnd = $project->deadline ? $project->deadline->copy()->endOfDay() : null;
        $span = $planEnd ? max(1, $start->diffInDays($planEnd)) : null;

        $labels = $done = $plan = [];
        $sum = 0;
        $i = 0;
        for ($at = $start->copy(); $at->lte($end); $monthly ? $at->addMonth() : $at->addWeek()) {
            $close = $monthly ? $at->copy()->endOfMonth() : $at->copy()->endOfWeek();
            while ($i < $logs->count() && \Carbon\Carbon::parse($logs[$i]->logged_at)->lte($close)) {
                $sum += $units ? (float) $logs[$i]->units_delta : (int) $logs[$i]->completed;
                $i++;
            }
            $labels[] = $at->format($monthly ? 'M Y' : 'd M');
            $done[] = round(max(0, $sum), 1);
            $plan[] = $span ? round(min(1, $start->diffInDays($close) / $span) * $target, 1) : 0;
        }

        if (count($labels) < 2) {
            return null; // not started yet, nothing to draw
        }

        return ['labels' => $labels, 'done' => $done, 'plan' => $span ? $plan : null, 'target' => $target,
            'unit' => $units ? $tracker['unit'] : 'work items'];
    }

    /** Is a move from one step to another a send-back (rework)? */
    public static function isBack(?int $fromStage, ?int $toStage): bool
    {
        if (!$fromStage || !$toStage || $fromStage === $toStage) {
            return false;
        }
        $sorts = ProjectTypeStage::whereIn('id', [$fromStage, $toStage])->pluck('sort', 'id');

        return isset($sorts[$fromStage], $sorts[$toStage]) && $sorts[$toStage] < $sorts[$fromStage];
    }

    /** Recent history of an item: who moved it where, units, rework and why. */
    public static function history(Task $task, int $limit = 8)
    {
        return DB::table('tracker_progress_logs as l')->leftJoin('users as u', 'u.id', '=', 'l.by_user_id')
            ->leftJoin('project_type_stages as f', 'f.id', '=', 'l.stage_from')->leftJoin('project_type_stages as t', 't.id', '=', 'l.stage_to')
            ->where('l.task_id', $task->id)->orderByDesc('l.logged_at')->orderByDesc('l.id')->limit($limit)
            ->get(['l.*', 'u.name as by_name', 'f.name as from_name', 't.name as to_name']);
    }

    public static function record(Task $task, array $before, ?string $at = null, ?string $note = null): void
    {
        $after = self::snapshot($task->fresh() ?? $task);

        if ($after['stage'] === $before['stage'] && abs($after['units'] - $before['units']) < 0.0001) {
            return;
        }

        $from = $before['stage'] ? ProjectTypeStage::find($before['stage']) : null;
        $to = $after['stage'] ? ProjectTypeStage::find($after['stage']) : null;

        DB::table('tracker_progress_logs')->insert([
            'company_id' => $task->company_id,
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'by_user_id' => user()?->id,
            'assignee_ids' => json_encode($task->users()->pluck('users.id')->map(fn ($id) => (int) $id)->values()->all()),
            'stage_from' => $before['stage'],
            'stage_to' => $after['stage'],
            'units_before' => $before['units'],
            'units_after' => $after['units'],
            'units_delta' => round($after['units'] - $before['units'], 2),
            'rework' => $from && $to && $to->sort < $from->sort,
            'completed' => $to && $to->is_done && (!$from || !$from->is_done),
            'logged_at' => $at ?? now(),
        ] + (\Illuminate\Support\Facades\Schema::hasColumn('tracker_progress_logs', 'note') ? ['note' => $note ?: null] : []));
    }

}
