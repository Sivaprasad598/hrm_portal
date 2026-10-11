<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Traits\ProjectProgress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateProjectProgressByDeadline extends Command
{
    use ProjectProgress;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'projects-update-deadline-progress';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update project progress for projects using deadline-based calculation (use -v for per-project output)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting project progress update for deadline-based projects…');

        $baseQuery = Project::where('calculate_task_progress', 'project_deadline')
            ->whereNotNull('start_date')
            ->whereNotNull('deadline');

        $totalProjects = (clone $baseQuery)->count();

        if ($totalProjects === 0) {
            $this->info('No projects use deadline-based progress. Nothing to do.');

            return Command::SUCCESS;
        }

        $this->line("Projects to scan: {$totalProjects}");

        $updatedCount = 0;
        $errorCount = 0;
        $changedCount = 0;

        $baseQuery->chunkById(100, function ($projects) use (&$updatedCount, &$errorCount, &$changedCount) {
            foreach ($projects as $project) {
                try {
                    $oldProgress = $project->completion_percent;
                    $oldStatus = $project->status;

                    $newProgress = $this->calculateProjectProgressByDeadline($project->id, $project);

                    if ($newProgress !== false) {
                        $newStatus = $project->status;

                        if ($oldProgress != $newProgress || $oldStatus != $newStatus) {
                            $changedCount++;

                            Log::info('Project progress updated', [
                                'project_id' => $project->id,
                                'project_name' => $project->project_name,
                                'old_progress' => $oldProgress,
                                'new_progress' => $newProgress,
                                'old_status' => $oldStatus,
                                'new_status' => $newStatus,
                                'deadline' => $project->deadline?->format('Y-m-d'),
                            ]);

                            if ($this->output->isVerbose()) {
                                $this->line("Updated project: {$project->project_name} — {$oldProgress}% → {$newProgress}%");

                                if ($oldStatus != $newStatus) {
                                    $this->line("  Status: {$oldStatus} → {$newStatus}");
                                }
                            }
                        }

                        $updatedCount++;
                    } else {
                        $this->warn("Failed to calculate progress for project: {$project->project_name} (ID: {$project->id})");
                        $errorCount++;
                    }
                } catch (\Exception $e) {
                    Log::error('Error updating project progress', [
                        'project_id' => $project->id,
                        'project_name' => $project->project_name,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    $this->error("Error updating project {$project->project_name}: " . $e->getMessage());
                    $errorCount++;
                }
            }
        });

        $this->info('Project progress update completed.');
        $this->line("Processed: {$updatedCount} / {$totalProjects}");
        $this->line("With progress or status change: {$changedCount}");

        if ($errorCount > 0) {
            $this->warn("Errors encountered: {$errorCount}");
        }

        Log::info('Daily project progress update completed', [
            'total_projects' => $totalProjects,
            'processed' => $updatedCount,
            'changed' => $changedCount,
            'error_count' => $errorCount,
            'execution_time' => now()->toDateTimeString(),
        ]);

        return Command::SUCCESS;
    }
}
