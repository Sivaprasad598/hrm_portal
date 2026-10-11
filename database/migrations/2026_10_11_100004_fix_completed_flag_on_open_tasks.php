<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{

    /**
     * Data fix: before the status-change fixes, moving a task out of Completed on the
     * Kanban board (and some other paths) left `status = completed` and `completed_on`
     * set, so the task list showed "Completed On: Today" for open tasks. Clear both on
     * every task that is not in a Completed column.
     */
    public function up(): void
    {
        $completedColumnIds = DB::table('taskboard_columns')->where('slug', 'completed')->pluck('id');

        DB::table('tasks')
            ->whereNotIn('board_column_id', $completedColumnIds)
            ->where(function ($query) {
                $query->whereNotNull('completed_on')->orWhere('status', 'completed');
            })
            ->update(['status' => 'incomplete', 'completed_on' => null]);
    }

    public function down(): void
    {
        // The old values were wrong; nothing to restore.
    }

};
