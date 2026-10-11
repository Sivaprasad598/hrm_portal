<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\EmployeeDetails;
use App\Models\Project;
use App\Models\TaskBlocker;
use App\Models\User;
use App\Notifications\TaskBlockerUpdate;
use Illuminate\Console\Command;

/**
 * Daily roadblock follow-up:
 * - reminds the teammate of every open roadblock (once a day)
 * - escalates to the project admin (or the teammate's reporting manager, or the
 *   company admins) once the needed-by date has passed
 */
class SendTaskBlockerReminders extends Command
{

    protected $signature = 'send-task-blocker-reminders';

    protected $description = 'Remind teammates about open task roadblocks and escalate overdue ones';

    public function handle()
    {
        Company::active()->select(['id', 'timezone'])->chunk(50, function ($companies) {
            foreach ($companies as $company) {
                $today = now($company->timezone)->startOfDay();

                $blockers = TaskBlocker::withoutGlobalScopes()
                    ->with('task.company', 'raisedBy', 'blockingUser')
                    ->where('company_id', $company->id)
                    ->open()
                    ->whereHas('task')
                    ->get();

                foreach ($blockers as $blocker) {
                    if (!$blocker->blockingUser || $blocker->blockingUser->status != 'active') {
                        continue;
                    }

                    // Reminder: not on the day it was raised, and at most once a day
                    $raisedToday = $blocker->created_at->copy()->tz($company->timezone)->isSameDay($today);
                    $remindedToday = $blocker->last_reminded_at && $blocker->last_reminded_at->copy()->tz($company->timezone)->isSameDay($today);

                    if (!$raisedToday && !$remindedToday) {
                        $blocker->blockingUser->notify(new TaskBlockerUpdate($blocker, 'reminder'));
                        $blocker->last_reminded_at = now();
                        $blocker->saveQuietly();
                    }

                    // Escalation: once, the day after the needed-by date
                    if (is_null($blocker->escalated_at) && $blocker->needed_by && $blocker->needed_by->lt($today)) {
                        foreach ($this->escalationRecipients($blocker, $company->id) as $recipient) {
                            $recipient->notify(new TaskBlockerUpdate($blocker, 'escalated'));
                        }

                        $blocker->escalated_at = now();
                        $blocker->saveQuietly();
                    }
                }
            }
        });

        return Command::SUCCESS;
    }

    private function escalationRecipients(TaskBlocker $blocker, int $companyId)
    {
        $projectAdminId = $blocker->task->project_id
            ? Project::withoutGlobalScopes()->withTrashed()->where('id', $blocker->task->project_id)->value('project_admin')
            : null;

        $managerId = EmployeeDetails::withoutGlobalScopes()->where('user_id', $blocker->blocking_user_id)->value('reporting_to');

        $recipientIds = array_filter([$projectAdminId, $managerId]);

        $recipients = User::withoutGlobalScopes()->whereIn('id', $recipientIds)->where('status', 'active')->get();

        if ($recipients->isEmpty()) {
            $recipients = User::allAdmins($companyId);
        }

        // The two people directly involved already know
        return $recipients->reject(fn ($user) => in_array($user->id, [$blocker->blocking_user_id, $blocker->raised_by]));
    }

}
