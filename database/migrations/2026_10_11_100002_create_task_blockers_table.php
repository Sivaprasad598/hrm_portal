<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Roadblocks: a task is On Hold because it is waiting on a teammate for a resource.
     * One row per person the task is waiting on.
     */
    public function up(): void
    {
        if (Schema::hasTable('task_blockers')) {
            return;
        }

        Schema::create('task_blockers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->nullable()->index();
            $table->unsignedInteger('task_id');
            $table->unsignedInteger('raised_by')->nullable();
            $table->unsignedInteger('blocking_user_id');
            $table->unsignedInteger('linked_task_id')->nullable();
            $table->text('need');
            $table->date('needed_by')->nullable();
            $table->enum('urgency', ['normal', 'urgent'])->default('normal');
            $table->enum('status', ['open', 'resolved', 'cancelled'])->default('open');
            $table->text('resolution_note')->nullable();
            $table->unsignedInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('previous_board_column_id')->nullable();
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'status']);
            $table->index(['blocking_user_id', 'status']);
            $table->index(['linked_task_id', 'status']);

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('raised_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('blocking_user_id')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('linked_task_id')->references('id')->on('tasks')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('previous_board_column_id')->references('id')->on('taskboard_columns')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_blockers');
    }

};
