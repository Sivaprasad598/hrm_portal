<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Project;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // get the ids of the projects where calculate_task_progress is true
        $projectstrue = Project::where('calculate_task_progress', 'true')->get(); 
        $projectsfalse = Project::where('calculate_task_progress', 'false')->get();

        // Use raw SQL to modify the enum column
        DB::statement("ALTER TABLE projects MODIFY COLUMN calculate_task_progress ENUM('manual', 'task_completion', 'project_total_time', 'project_deadline') DEFAULT 'manual'");

        foreach ($projectstrue as $project) {
            $project->calculate_task_progress = 'task_completion';
            $project->save();
        }

        foreach ($projectsfalse as $project) {
            $project->calculate_task_progress = 'manual';
            $project->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to the original enum values
        DB::statement("ALTER TABLE projects MODIFY COLUMN calculate_task_progress ENUM('true', 'false') DEFAULT 'true'");
    }
};