<?php

namespace App\Services\Tracker;

use App\Models\Project;
use App\Models\ProjectTypeStage;
use App\Models\Task;
use App\Models\TaskboardColumn;
use Illuminate\Support\Collection;

/**
 * A project as the tracker sees it: its type and steps, the Epic -> Work item -> Task tree
 * (from tasks.work_type / parent_id), each item's step and units done, and the totals.
 *
 * One query for the tasks (with assignees and hours logged); the tree and the counts are
 * built in memory, so the tab stays fast with hundreds of tasks.
 */
class ProjectTracker
{

    public const LEVELS = [
        'epic' => ['Epic', 'fa-layer-group', '#7a5af8'],
        'item' => ['Work item', 'fa-clipboard-list', '#0ba5ec'],
        'task' => ['Task', 'fa-check-square', '#667085'],
    ];

    /**
     * @return array{type: ?\App\Models\ProjectType, stages: Collection, tasks: Collection, epics: Collection, loose: Collection,
     *               byStage: Collection, totals: array, unit: string}
     */
    public function forProject(Project $project): array
    {
        $project->loadMissing('projectType.stages');
        $type = $project->projectType;
        $stages = $type ? $type->stages->values() : collect();

        $tasks = Task::where('project_id', $project->id)
            ->with(['users', 'boardColumn'])
            ->withSum('timeLogged as minutes_logged', 'total_minutes')
            ->orderBy('start_date')->orderBy('id')
            ->get();

        $children = $tasks->groupBy(fn ($t) => (int) $t->parent_id);
        $epics = $tasks->where('work_type', 'epic')->values();
        // under no epic: items and tasks whose parent is not in this project's tree
        $ids = $tasks->pluck('id')->all();
        $loose = $tasks->filter(fn ($t) => $t->work_type !== 'epic' && (!$t->parent_id || !in_array($t->parent_id, $ids, true)))->values();

        $doneStageIds = $stages->where('is_done', true)->pluck('id')->all();
        $completedColumn = TaskboardColumn::completeColumn()?->id;
        $isDone = fn (Task $t) => ($t->project_stage_id && in_array($t->project_stage_id, $doneStageIds, true)) || ($completedColumn && (int) $t->board_column_id === (int) $completedColumn);

        // the work lives in items and tasks (an epic is a group); the quantity lives in the Work items
        // (a Task is a step of one - its units are the same masts again, so they are not added)
        $work = $tasks->where('work_type', '!=', 'epic');
        $items = $tasks->where('work_type', 'item')->filter(fn ($t) => (float) $t->planned_units > 0);
        $planned = (float) $items->sum('planned_units');
        $doneUnits = (float) $items->sum(fn ($t) => $isDone($t) ? (float) $t->planned_units : min((float) $t->done_units, (float) $t->planned_units));
        $estimate = $work->sum(fn ($t) => (int) $t->estimate_hours * 60 + (int) $t->estimate_minutes) / 60;
        $logged = $work->sum('minutes_logged') / 60;
        $overdue = $work->filter(fn ($t) => !$isDone($t) && $t->due_date && $t->due_date->endOfDay()->isPast())->count();

        $byStage = $stages->mapWithKeys(fn (ProjectTypeStage $s) => [$s->id => $work->where('project_stage_id', $s->id)->values()]);
        $unstaged = $work->filter(fn ($t) => !$t->project_stage_id || !$byStage->has($t->project_stage_id))->values();

        return [
            'type' => $type,
            'stages' => $stages,
            'tasks' => $tasks,
            'children' => $children,
            'epics' => $epics,
            'loose' => $loose,
            'byStage' => $byStage,
            'unstaged' => $unstaged,
            'isDone' => $isDone,
            'unit' => $type ? $type->unitText() : 'units',
            'totals' => [
                'items' => $work->count(),
                'done' => $work->filter($isDone)->count(),
                'scope_units' => $project->planned_units !== null ? (float) $project->planned_units : null,   // the job as agreed
                'planned_units' => $planned,                                                                     // in the Work items
                'done_units' => $doneUnits,
                'estimate_hours' => round($estimate, 1),
                'logged_hours' => round($logged, 1),
                'overdue' => $overdue,
                'blocked' => \Illuminate\Support\Facades\Schema::hasTable('task_blockers')
                    ? \Illuminate\Support\Facades\DB::table('task_blockers')->whereIn('task_id', $work->pluck('id'))->where('status', 'open')->count() : 0,
            ],
        ];
    }

    /** The step, and the board status that goes with it (the last step = completed; leaving it reopens). Not saved. */
    public static function moveTo(\App\Models\Task $task, \App\Models\ProjectTypeStage $stage): void
    {
        $task->project_stage_id = $stage->id;
        $completed = \App\Models\TaskboardColumn::completeColumn();

        if ($stage->is_done && $completed) {
            $task->board_column_id = $completed->id;
            $task->completed_on = $task->completed_on ?: now();

            if ($task->planned_units) {
                $task->done_units = $task->planned_units;
            }
        } elseif ($completed && (int) $task->board_column_id === (int) $completed->id) {
            $task->board_column_id = \App\Models\TaskboardColumn::where('slug', 'doing')->value('id') ?? $task->board_column_id;
            $task->completed_on = null;
        }
    }

    /** % of the Work items' units done (when the type counts units), else % of the work done. */
    public static function percent(array $tracker): int
    {
        $t = $tracker['totals'];

        if ($tracker['type'] && $tracker['type']->countsUnits() && $t['planned_units'] > 0) {
            return (int) min(100, round($t['done_units'] / $t['planned_units'] * 100));
        }

        return $t['items'] ? (int) round($t['done'] / $t['items'] * 100) : 0;
    }

}
