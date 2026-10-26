<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leave approval in steps: reporting manager -> department head -> HR, each
 * step switched on or off in Leave Settings (App\Services\Leaves\LeaveApprovals).
 *
 *   leave_settings.approval_levels       ["manager","department_head","hr"]
 *   leave_settings.escalate_after_days   no decision in this many days: on to the next step (0: wait)
 *   teams.head_user_id                   the department head
 *   leaves.approval_step                 the step a pending leave waits at (done once decided)
 *   leaves.approval_step_at              since when (for moving it on)
 *   leave_approvals                      who did what at which step
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::table('leave_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_settings', 'approval_levels')) {
                $table->json('approval_levels')->nullable();
                $table->unsignedTinyInteger('escalate_after_days')->default(2);
            }
        });

        Schema::table('teams', function (Blueprint $table) {
            if (!Schema::hasColumn('teams', 'head_user_id')) {
                $table->unsignedInteger('head_user_id')->nullable()->after('parent_id');
            }
        });

        Schema::table('leaves', function (Blueprint $table) {
            if (!Schema::hasColumn('leaves', 'approval_step')) {
                $table->string('approval_step', 20)->nullable();
                $table->dateTime('approval_step_at')->nullable();
            }
        });

        if (!Schema::hasTable('leave_approvals')) {
            Schema::create('leave_approvals', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('leave_id');
                $table->string('unique_id', 32)->nullable()->index();
                $table->string('step', 20);
                $table->string('action', 20);   // submitted | approved | override | rejected | escalated | skipped
                $table->unsignedInteger('user_id')->nullable();
                $table->string('note', 500)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index('leave_id');
            });
        }

        // All three steps on; a step with nobody for it (no manager, no head) is passed over.
        DB::table('leave_settings')->whereNull('approval_levels')->update(['approval_levels' => json_encode(['manager', 'department_head', 'hr'])]);

        // Leave already waiting goes on as before: with HR.
        DB::table('leaves')->where('status', 'pending')->whereNull('approval_step')->update(['approval_step' => 'hr', 'approval_step_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_approvals');
        Schema::table('leaves', fn (Blueprint $t) => $t->dropColumn(['approval_step', 'approval_step_at']));
        Schema::table('teams', fn (Blueprint $t) => $t->dropColumn('head_user_id'));
        Schema::table('leave_settings', fn (Blueprint $t) => $t->dropColumn(['approval_levels', 'escalate_after_days']));
    }

};
