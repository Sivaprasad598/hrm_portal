<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Task status workflow:
     * - "Doing" is shown as "Started" (slug stays `doing` so existing code keeps working)
     * - new "On Hold – Waiting for Resources" status (slug `on_hold`) after Started
     * - task_status_logs records every status change and how long the task stayed in the previous status
     * - auto timer stop is switched on so automatic timers close at shift end
     */
    public function up(): void
    {
        if (!Schema::hasTable('task_status_logs')) {
            Schema::create('task_status_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->unsignedInteger('task_id');
                $table->unsignedInteger('user_id')->nullable();
                $table->unsignedInteger('from_board_column_id')->nullable();
                $table->unsignedInteger('to_board_column_id')->nullable();
                $table->timestamp('changed_at')->useCurrent();
                $table->unsignedBigInteger('seconds_in_previous')->nullable();
                $table->timestamps();

                $table->index(['task_id', 'changed_at']);
                $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('from_board_column_id')->references('id')->on('taskboard_columns')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('to_board_column_id')->references('id')->on('taskboard_columns')->nullOnDelete()->cascadeOnUpdate();
            });
        }

        DB::table('taskboard_columns')
            ->where('slug', 'doing')
            ->where('column_name', 'Doing')
            ->update(['column_name' => 'Started']);

        $companyIds = DB::table('taskboard_columns')->distinct()->pluck('company_id');

        foreach ($companyIds as $companyId) {
            $exists = DB::table('taskboard_columns')
                ->where('company_id', $companyId)
                ->where('slug', 'on_hold')
                ->exists();

            if ($exists) {
                continue;
            }

            $started = DB::table('taskboard_columns')
                ->where('company_id', $companyId)
                ->where('slug', 'doing')
                ->first();

            $priority = $started ? $started->priority + 1 : DB::table('taskboard_columns')->where('company_id', $companyId)->max('priority') + 1;

            DB::table('taskboard_columns')
                ->where('company_id', $companyId)
                ->where('priority', '>=', $priority)
                ->increment('priority');

            DB::table('taskboard_columns')->insert([
                'company_id' => $companyId,
                'column_name' => 'On Hold – Waiting for Resources',
                'slug' => 'on_hold',
                'label_color' => '#ff8c00',
                'priority' => $priority,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('log_time_for')) {
            DB::table('log_time_for')->update(['auto_timer_stop' => 'yes']);
        }
    }

    public function down(): void
    {
        $onHoldIds = DB::table('taskboard_columns')->where('slug', 'on_hold')->pluck('id');

        if ($onHoldIds->isNotEmpty()) {
            // Move tasks back to Started before the column is removed
            foreach (DB::table('taskboard_columns')->whereIn('id', $onHoldIds)->get() as $column) {
                $startedId = DB::table('taskboard_columns')
                    ->where('company_id', $column->company_id)
                    ->where('slug', 'doing')
                    ->value('id');

                DB::table('tasks')->where('board_column_id', $column->id)->update(['board_column_id' => $startedId]);

                DB::table('taskboard_columns')
                    ->where('company_id', $column->company_id)
                    ->where('priority', '>', $column->priority)
                    ->decrement('priority');
            }

            DB::table('taskboard_columns')->whereIn('id', $onHoldIds)->delete();
        }

        DB::table('taskboard_columns')
            ->where('slug', 'doing')
            ->where('column_name', 'Started')
            ->update(['column_name' => 'Doing']);

        Schema::dropIfExists('task_status_logs');
    }

};
