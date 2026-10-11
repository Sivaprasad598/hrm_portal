<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Scopes\ActiveScope;
use Carbon\Exceptions\InvalidFormatException;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Traits\UniversalSearchTrait;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\ProjectActivity;
use App\Traits\ExcelImportable;

class ImportProjectJob implements ShouldQueue
{

    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels, UniversalSearchTrait;
    use ExcelImportable;

    private $row;
    private $columns;
    private $company;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($row, $columns, $company = null)
    {
        $this->row = $row;
        $this->columns = $columns;
        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->isColumnExists('project_name') && $this->isColumnExists('start_date')) {
            $client = null;

            if (!empty($this->isColumnExists('client_email'))) {
                // user that have client role
                $client = User::where('email', $this->getColumnValue('client_email'))->whereHas('roles', function ($q) {
                    $q->where('name', 'client');
                })->first();
            }

            DB::beginTransaction();
            try {
                $project = new Project();
                $project->company_id = $this->company?->id;
                $project->project_name = $this->getColumnValue('project_name');

                $project->project_summary = $this->isColumnExists('project_summary') ? $this->getColumnValue('project_summary') : null;

                $project->start_date = Carbon::createFromFormat('Y-m-d', $this->getColumnValue('start_date'));
                $project->deadline = $this->isColumnExists('deadline') ? (!empty(trim($this->getColumnValue('deadline'))) ? Carbon::createFromFormat('Y-m-d', $this->getColumnValue('deadline')) : null) : null;

                if ($this->isColumnExists('notes')) {
                    $project->notes = $this->getColumnValue('notes');
                }

                $project->client_id = $client ? $client->id : null;

                $project->project_budget = $this->isColumnExists('project_budget') ? $this->getColumnValue('project_budget') : null;

                $project->currency_id = $this->company?->currency_id;

                $project->status = $this->isColumnExists('status') ? strtolower(trim($this->getColumnValue('status'))) : 'not started';

                $project->save();

                // Process project members if column exists
                if ($this->isColumnExists('project_members')) {
                    $membersEmails = $this->getColumnValue('project_members');
                    if (!empty($membersEmails)) {
                        $this->syncProjectMembers($project, $membersEmails);
                    }
                }

                $this->logSearchEntry($project->id, $project->project_name, 'projects.show', 'project', $project->company_id);
                $this->logProjectActivity($project->id, 'messages.updateSuccess');
                DB::commit();
            } catch (InvalidFormatException $e) {
                DB::rollBack();
                $this->failJob(__('messages.invalidDate'));
            } catch (Exception $e) {
                DB::rollBack();
                $this->failJobWithMessage($e->getMessage());
            }

        }
        else {
            $this->failJob(__('messages.invalidData'));
        }
    }

    public function logProjectActivity($projectId, $text)
    {
        $activity = new ProjectActivity();
        $activity->project_id = $projectId;
        $activity->activity = $text;
        $activity->save();
    }

    /**
     * Sync project members from comma-separated emails
     *
     * @param Project $project
     * @param string $emailsString
     * @return void
     */
    private function syncProjectMembers(Project $project, string $emailsString)
    {
        // Parse comma-separated emails
        $emails = array_map('trim', explode(',', $emailsString));
        $emails = array_filter($emails, function($email) {
            return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
        });

        if (empty($emails)) {
            return;
        }

        $userIds = [];

        foreach ($emails as $email) {
            // Find user by email, check if exists and is active
            $user = User::withoutGlobalScope(ActiveScope::class)
                ->where('email', $email)
                ->where('company_id', $this->company?->id)
                ->first();

            // Only add if user exists and is active
            if ($user && $user->status === 'active') {
                $userIds[] = $user->id;
            }
        }

        // Sync users as project members (without detaching existing members)
        if (!empty($userIds)) {
            $project->projectMembers()->syncWithoutDetaching($userIds);
        }
    }

}
