<?php

namespace App\Http\Controllers\Tracker;

use App\Helper\Reply;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\ProjectTypeStage;
use App\Models\Task;
use App\Models\TaskboardColumn;
use App\Services\Tracker\ProgressLog;
use App\Services\Tracker\ProjectTracker;
use App\Services\Tracker\TrackerAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A project's Tracker tab: its setup (type, planned quantity, grace days), the Epic ->
 * Work item -> Task structure, moving work through the type's steps and recording the
 * units done. Items are ordinary tasks of the project (tasks.work_type / parent_id /
 * project_stage_id / planned_units / done_units), so Tasks, Timesheet and Gantt see them.
 */
class ProjectTrackerController extends TrackerBaseController
{

    public function setupForm($projectId)
    {
        $this->project = $this->project($projectId, true);
        $this->types = ProjectType::forCompany(null, true);

        if ($this->project->project_type_id && !$this->types->contains('id', $this->project->project_type_id)) {
            $this->types->push($this->project->projectType);
        }

        return $this->panel('project-tracker.project.setup', 'Tracker setup — ' . $this->project->project_name);
    }

    public function setup(Request $request, $projectId)
    {
        $project = $this->project($projectId, true);
        $request->validate([
            'project_type_id' => 'required|integer',
            'planned_units' => 'nullable|numeric|min:0',
            'grace_days' => 'nullable|integer|min:0|max:365',
        ], ['project_type_id.required' => 'Choose the project type.']);

        $type = ProjectType::where('company_id', company()->id)->with('stages')->findOrFail($request->project_type_id);
        $changed = (int) $project->project_type_id !== (int) $type->id;

        DB::transaction(function () use ($project, $type, $request, $changed) {
            $project->project_type_id = $type->id;
            $project->planned_units = $request->filled('planned_units') ? (float) $request->planned_units : null;
            $project->grace_days = $request->filled('grace_days') ? (int) $request->grace_days : null;
            $project->save();

            // a new type: the work starts at its first step (steps of another type mean nothing here)
            if ($changed) {
                Task::where('project_id', $project->id)->where('work_type', '!=', 'epic')
                    ->update(['project_stage_id' => $type->stages->first()?->id]);
            }
        });

        return Reply::successWithData('Tracker set up: ' . $type->name . '.', ['redirectUrl' => route('projects.show', $project->id) . '?tab=tracker']);
    }

    public function itemForm(Request $request, $projectId, $taskId = null)
    {
        $this->project = $this->project($projectId, true);
        $this->project->loadMissing('projectType.stages', 'members.user');
        $this->item = $taskId ? Task::where('project_id', $this->project->id)->with('users')->findOrFail($taskId)
            : new Task(['work_type' => in_array($request->level, ['epic', 'item', 'task'], true) ? $request->level : 'item',
                'parent_id' => $request->parent ?: null, 'start_date' => now(), 'due_date' => $this->project->deadline ?? now()->addDays(7),
                'project_stage_id' => $this->project->projectType?->stages->first()?->id]);
        $this->parents = Task::where('project_id', $this->project->id)->whereIn('work_type', ['epic', 'item'])
            ->when($this->item->exists, fn ($q) => $q->where('id', '!=', $this->item->id))->orderBy('work_type')->orderBy('heading')->get(['id', 'heading', 'work_type']);
        $this->members = $this->project->members->map(fn ($m) => $m->user)->filter()->sortBy('name')->values();
        $level = ProjectTracker::LEVELS[$this->item->work_type][0];

        return $this->panel('project-tracker.project.item-form', ($this->item->exists ? 'Edit ' : 'Add ') . $level);
    }

    public function storeItem(Request $request, $projectId)
    {
        $project = $this->project($projectId, true);
        $task = new Task(['project_id' => $project->id]);

        return $this->saveItem($request, $project, $task, 'added');
    }

    public function updateItem(Request $request, $projectId, $taskId)
    {
        $project = $this->project($projectId, true);
        $task = Task::where('project_id', $project->id)->findOrFail($taskId);

        return $this->saveItem($request, $project, $task, 'saved');
    }

    /** Drag on the board: the item moves to another step (the last step completes it). */
    public function stage(Request $request, $projectId, $taskId)
    {
        $project = $this->project($projectId, true);
        $task = Task::where('project_id', $project->id)->findOrFail($taskId);
        $stage = ProjectTypeStage::where('project_type_id', $project->project_type_id)->findOrFail($request->stage_id);
        $before = ProgressLog::snapshot($task);
        $back = ProgressLog::isBack($before['stage'], $stage->id);

        if ($back && !trim((string) $request->reason)) {
            $this->fail('Say why it goes back to ' . $stage->name . ' (what needs fixing).');
        }

        $this->moveTo($task, $stage);
        $task->save();
        ProgressLog::record($task, $before, null, $back ? trim(mb_substr((string) $request->reason, 0, 1000)) : null);

        return Reply::success($task->heading . ($back ? ' sent back to ' : ' moved to ') . $stage->name . '.');
    }

    /** Units done on an item (e.g. 18 of 26 masts digitised). */
    public function progress(Request $request, $projectId, $taskId)
    {
        $project = $this->project($projectId, true);
        $task = Task::where('project_id', $project->id)->findOrFail($taskId);
        $request->validate(['done_units' => 'required|numeric|min:0'], ['done_units.*' => 'Enter how many are done.']);

        $before = ProgressLog::snapshot($task);
        $task->done_units = min((float) $request->done_units, (float) ($task->planned_units ?: $request->done_units));
        $task->save();
        ProgressLog::record($task, $before);

        return Reply::success('Progress saved: ' . rtrim(rtrim(number_format($task->done_units, 2), '0'), '.')
            . ($task->planned_units ? ' of ' . rtrim(rtrim(number_format($task->planned_units, 2), '0'), '.') : '') . '.');
    }

    private function saveItem(Request $request, Project $project, Task $task, string $verb)
    {
        $format = company()->date_format;
        $request->validate([
            'heading' => 'required|string|max:255',
            'work_type' => 'required|in:epic,item,task',
            'parent_id' => 'nullable|integer',
            'project_stage_id' => 'nullable|integer',
            'planned_units' => 'nullable|numeric|min:0',
            'done_units' => 'nullable|numeric|min:0',
            'estimate_hours' => 'nullable|numeric|min:0|max:10000',
            'start_date' => 'required|date_format:' . $format,
            'due_date' => 'required|date_format:' . $format,
            'user_id' => 'nullable|array',
        ], ['heading.required' => 'Give it a name.']);

        $start = Carbon::createFromFormat($format, $request->start_date)->startOfDay();
        $due = Carbon::createFromFormat($format, $request->due_date)->startOfDay();

        if ($due->lt($start)) {
            $this->fail('The due date cannot be before the start date.');
        }

        // the parent: an Epic holds Work items, a Work item holds Tasks; an Epic sits at the top
        $parent = $request->parent_id ? Task::where('project_id', $project->id)->find($request->parent_id) : null;

        if ($request->work_type === 'epic') {
            $parent = null;
        } elseif ($parent && ($parent->id === $task->id || ($request->work_type === 'item' && $parent->work_type !== 'epic'))) {
            $this->fail($request->work_type === 'item' ? 'A Work item sits under an Epic.' : 'Choose an Epic or a Work item to put this under.');
        }

        $stage = $request->project_stage_id ? ProjectTypeStage::where('project_type_id', $project->project_type_id)->find($request->project_stage_id) : null;
        $memberIds = $project->members()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $users = array_values(array_intersect(array_map('intval', (array) $request->user_id), $memberIds));
        $hours = (float) $request->estimate_hours;

        $before = $task->exists ? ProgressLog::snapshot($task) : ['stage' => null, 'units' => 0.0];
        $back = $stage && $request->work_type !== 'epic' && ProgressLog::isBack($before['stage'], $stage->id);

        if ($back && !trim((string) $request->rework_reason)) {
            $this->fail('It goes back to ' . $stage->name . ': say what needs fixing.');
        }

        DB::transaction(function () use ($request, $project, $task, $parent, $stage, $users, $hours, $start, $due) {
            $task->fill([
                'heading' => trim($request->heading),
                'description' => $request->description ?: $task->description,
                'work_type' => $request->work_type,
                'parent_id' => $parent?->id,
                'planned_units' => $request->work_type === 'epic' ? null : ($request->filled('planned_units') ? (float) $request->planned_units : null),
                'estimate_hours' => (int) floor($hours),
                'estimate_minutes' => (int) round(($hours - floor($hours)) * 60),
                'start_date' => $start,
                'due_date' => $due,
                'priority' => $task->priority ?: 'medium',
            ]);

            if ($request->filled('done_units') && $request->work_type !== 'epic') {
                $task->done_units = min((float) $request->done_units, (float) ($task->planned_units ?: $request->done_units));
            }

            if (!$task->exists) {
                $task->project_id = $project->id;
                $task->company_id = $project->company_id;
                $task->board_column_id = TaskboardColumn::where('slug', 'incomplete')->value('id') ?? TaskboardColumn::value('id');
            }

            if ($request->work_type === 'epic') {
                $task->project_stage_id = null;
            } elseif ($stage) {
                $this->moveTo($task, $stage);
            }

            $task->save();
            $task->users()->sync($request->work_type === 'epic' ? [] : $users);
        });

        if ($task->work_type !== 'epic') {
            ProgressLog::record($task, $before, null, $back ? trim(mb_substr((string) $request->rework_reason, 0, 1000)) : null);
        }

        $level = ProjectTracker::LEVELS[$task->work_type][0];

        return Reply::successWithData($level . ' ' . $verb . '.', ['redirectUrl' => route('projects.show', $project->id) . '?tab=tracker']);
    }

    /** The step, and the board status that goes with it (the last step = completed). */
    private function moveTo(Task $task, ProjectTypeStage $stage): void
    {
        ProjectTracker::moveTo($task, $stage);
    }

    private function project($id, bool $manage = false): Project
    {
        $project = Project::with('projectType.stages')->findOrFail($id);

        if ($manage) {
            abort_403(!TrackerAccess::manage($project));
        }

        return $project;
    }

}
