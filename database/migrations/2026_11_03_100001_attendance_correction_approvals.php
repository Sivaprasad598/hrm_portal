<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance corrections (attendance_regularisations) in two steps, as leave:
 * the employee's reporting manager, then HR (App\Services\Attendance\CorrectionApprovals).
 * approval_step is where a pending request waits; the log says who did what.
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasColumn('attendance_regularisations', 'approval_step')) {
            Schema::table('attendance_regularisations', function (Blueprint $table) {
                $table->string('approval_step', 20)->nullable()->after('status')->index();
                $table->dateTime('approval_step_at')->nullable()->after('approval_step');
            });
        }

        if (!Schema::hasTable('attendance_regularisation_approvals')) {
            Schema::create('attendance_regularisation_approvals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('regularisation_id')->index();
                $table->string('step', 20);
                $table->string('action', 20);              // submitted | approved | override | rejected
                $table->unsignedInteger('user_id')->nullable();
                $table->string('note', 500)->nullable();
                $table->dateTime('created_at')->nullable();
            });
        }

        // Requests already waiting go to HR, as before
        DB::table('attendance_regularisations')->where('status', 'pending')->whereNull('approval_step')
            ->update(['approval_step' => 'hr', 'approval_step_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_regularisation_approvals');

        if (Schema::hasColumn('attendance_regularisations', 'approval_step')) {
            Schema::table('attendance_regularisations', function (Blueprint $table) {
                $table->dropColumn(['approval_step', 'approval_step_at']);
            });
        }
    }
};
