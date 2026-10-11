<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectMilestone;
use App\Models\SubTask;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskHistory;
use App\Models\TaskLabelList;
use App\Models\TaskboardColumn;
use App\Models\User;
use App\Scopes\ActiveScope;
use App\Traits\ExcelImportable;
use App\Traits\ProjectProgress;
use App\Traits\UniversalSearchTrait;
use Carbon\Exceptions\InvalidFormatException;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ImportTaskJob implements ShouldQueue
{

    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels, UniversalSearchTrait, ProjectProgress;
    use ExcelImportable;

    private $row;
    private $columns;
    private $company;
    private $user;

    public function __construct($row, $columns, $company = null)
    {
        $this->row = $row;
        $this->columns = $columns;
        $this->company = $company;
        $this->user = user();
    }

    public function handle()
    {
        if (!$this->isColumnExists('heading') || !$this->isColumnExists('start_date') || !$this->isColumnExists('priority')) {
            $this->failJob(__('messages.invalidData'));

            return;
        }

        DB::beginTransaction();

        try {
            $project = null;

            if ($this->isColumnExists('project_name') && !empty(trim($this->getColumnValue('project_name')))) {
                $project = Project::where('project_name', trim($this->getColumnValue('project_name')))
                    ->where('company_id', $this->company?->id)
                    ->first();

                if (!$project) {
                    DB::rollBack();
                    $this->failJobWithMessage(__('messages.projectNotFound'));

                    return;
                }
            }

            $taskBoardColumn = $this->resolveBoardColumn();
            $dueDate = $this->resolveDueDate();

            $task = new Task();
            $task->company_id = $this->company?->id;
            $task->heading = $this->getColumnValue('heading');
            $task->description = $this->isColumnExists('description') ? trim($this->getColumnValue('description')) : null;
            $task->start_date = Carbon::createFromFormat('Y-m-d', $this->getColumnValue('start_date'));
            $task->due_date = $dueDate;
            $task->project_id = $project?->id;
            $task->priority = strtolower(trim($this->getColumnValue('priority')));
            $task->board_column_id = $taskBoardColumn->id;
            $task->is_private = $this->parseBoolean($this->isColumnExists('is_private') ? $this->getColumnValue('is_private') : null);
            $task->billable = $this->parseBoolean($this->isColumnExists('billable') ? $this->getColumnValue('billable') : null, true);
            $task->estimate_hours = $this->isColumnExists('estimate_hours') ? (int) $this->getColumnValue('estimate_hours') : 0;
            $task->estimate_minutes = $this->isColumnExists('estimate_minutes') ? (int) $this->getColumnValue('estimate_minutes') : 0;
            $task->repeat = 0;
            $task->added_by = $this->user?->id;
            $task->created_by = $this->user?->id;

            if ($this->isColumnExists('category') && !empty(trim($this->getColumnValue('category')))) {
                $category = TaskCategory::where('category_name', trim($this->getColumnValue('category')))
                    ->where('company_id', $this->company?->id)
                    ->first();

                if ($category) {
                    $task->task_category_id = $category->id;
                }
            }

            if ($project && $this->isColumnExists('milestone') && !empty(trim($this->getColumnValue('milestone')))) {
                $milestone = ProjectMilestone::where('milestone_title', trim($this->getColumnValue('milestone')))
                    ->where('project_id', $project->id)
                    ->first();

                if ($milestone) {
                    $task->milestone_id = $milestone->id;
                }
            }

            $waitingApprovalTaskBoardColumn = TaskboardColumn::waitingForApprovalColumn();

            if ($taskBoardColumn->id == $waitingApprovalTaskBoardColumn->id) {
                $task->approval_send = 1;
            }

            if ($project && $this->isColumnExists('dependent_task') && !empty(trim($this->getColumnValue('dependent_task')))) {
                $dependentTask = Task::where('heading', trim($this->getColumnValue('dependent_task')))
                    ->where('project_id', $project->id)
                    ->first();

                if ($dependentTask) {
                    if (!is_null($dependentTask->due_date) && !is_null($dueDate) && $dependentTask->due_date->greaterThan($dueDate)) {
                        DB::rollBack();
                        $this->failJobWithMessage(__('messages.taskDependentDate'));

                        return;
                    }

                    $task->dependent_task_id = $dependentTask->id;
                }
            }

            if ($project) {
                $projectLastTaskCount = Task::projectTaskCount($project->id);

                if (isset($project->project_short_code)) {
                    $task->task_short_code = $project->project_short_code . '-' . $this->getTaskShortCode($project->project_short_code, $projectLastTaskCount);
                }
                else {
                    $task->task_short_code = $projectLastTaskCount + 1;
                }
            }

            $task->save();

            $labelIds = $this->resolveLabelIds($project?->id);
            if (!empty($labelIds)) {
                $task->labels()->sync($labelIds);
            }

            $userIds = $this->resolveAssigneeIds();

            if (!empty($userIds)) {
                $task->users()->sync($userIds);
            }

            if ($this->isColumnExists('subtasks') && !empty(trim($this->getColumnValue('subtasks')))) {
                $subtaskTitles = array_filter(array_map('trim', explode(',', $this->getColumnValue('subtasks'))));

                foreach ($subtaskTitles as $title) {
                    $subTask = new SubTask();
                    $subTask->title = $title;
                    $subTask->task_id = $task->id;
                    $subTask->added_by = $this->user?->id;
                    $subTask->save();
                }
            }

            $this->logSearchEntry($task->id, $task->heading, 'tasks.edit', 'task', $this->company?->id);

            if ($this->user) {
                $activity = new TaskHistory();
                $activity->task_id = $task->id;
                $activity->user_id = $this->user->id;
                $activity->details = 'createActivity';
                $activity->board_column_id = $task->board_column_id;
                $activity->save();
            }

            if ($project) {
                $projectActivity = new ProjectActivity();
                $projectActivity->project_id = $project->id;
                $projectActivity->activity = 'messages.newTaskAddedToTheProject';
                $projectActivity->save();

                $this->calculateProjectProgress($project->id);
                $this->calculateProjectProgressByTime($project->id);
            }

            DB::commit();
        } catch (InvalidFormatException $e) {
            DB::rollBack();
            $this->failJob(__('messages.invalidDate'));
        } catch (Exception $e) {
            DB::rollBack();
            $this->failJobWithMessage($e->getMessage());
        }
    }

    private function resolveBoardColumn()
    {
        if ($this->isColumnExists('status') && !empty(trim($this->getColumnValue('status')))) {
            $status = strtolower(trim($this->getColumnValue('status')));

            $boardColumn = TaskboardColumn::where('slug', $status)
                ->where('company_id', $this->company?->id)
                ->first();

            if (!$boardColumn) {
                $boardColumn = TaskboardColumn::where('column_name', trim($this->getColumnValue('status')))
                    ->where('company_id', $this->company?->id)
                    ->first();
            }

            if ($boardColumn) {
                return $boardColumn;
            }
        }

        if (isset($this->company->default_task_status)) {
            $boardColumn = TaskboardColumn::find($this->company->default_task_status);

            if ($boardColumn) {
                return $boardColumn;
            }
        }

        return TaskboardColumn::where('slug', 'incomplete')
            ->where('company_id', $this->company?->id)
            ->first();
    }

    private function resolveDueDate()
    {
        if (!$this->isColumnExists('due_date') || empty(trim((string) $this->getColumnValue('due_date')))) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $this->getColumnValue('due_date'));
    }

    private function resolveLabelIds(?int $projectId): array
    {
        if (!$this->isColumnExists('labels') || empty(trim($this->getColumnValue('labels')))) {
            return [];
        }

        $labelNames = array_filter(array_map('trim', explode(',', $this->getColumnValue('labels'))));
        $labelIds = [];

        foreach ($labelNames as $labelName) {
            $label = TaskLabelList::where('label_name', $labelName)
                ->where('company_id', $this->company?->id)
                ->where(function ($query) use ($projectId) {
                    $query->whereNull('project_id');

                    if ($projectId) {
                        $query->orWhere('project_id', $projectId);
                    }
                })
                ->first();

            if ($label) {
                $labelIds[] = $label->id;
            }
        }

        return $labelIds;
    }

    private function resolveAssigneeIds(): array
    {
        if (!$this->isColumnExists('assignees') || empty(trim($this->getColumnValue('assignees')))) {
            return [];
        }

        $emails = array_filter(array_map('trim', explode(',', $this->getColumnValue('assignees'))), function ($email) {
            return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
        });

        $userIds = [];

        foreach ($emails as $email) {
            $user = User::withoutGlobalScope(ActiveScope::class)
                ->where('email', $email)
                ->where('company_id', $this->company?->id)
                ->first();

            if ($user && $user->status === 'active') {
                $userIds[] = $user->id;
            }
        }

        return $userIds;
    }

    private function parseBoolean($value, bool $default = false): int
    {
        if ($value === null || trim((string) $value) === '') {
            return $default ? 1 : 0;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'yes', 'true', 'y']) ? 1 : 0;
    }

    private function getTaskShortCode($projectShortCode, $lastProjectCount)
    {
        $exists = Task::where('task_short_code', $projectShortCode . '-' . $lastProjectCount)->exists();

        if ($exists) {
            return $this->getTaskShortCode($projectShortCode, $lastProjectCount + 1);
        }

        return $lastProjectCount;
    }

}
