<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An employee's own shift, from a date.
 *
 *  - employee_shift_assignments: employee -> shift, effective_from .. effective_to
 *    (null = still in force). History is kept, so a past day is judged on the
 *    shift that applied then.
 *  - employee_shift_schedules.assignment_id: the roster rows written from an
 *    assignment (one per day, a rolling 60 days ahead). Every existing screen
 *    already reads the roster - clock-in, late marks, leave, dashboards - so
 *    they all follow the employee's shift. Rows HR rostered by hand have no
 *    assignment_id and are never touched by an assignment.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('employee_shift_assignments')) {
            Schema::create('employee_shift_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->unsignedInteger('user_id');
                $table->unsignedBigInteger('employee_shift_id');
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->unsignedInteger('assigned_by')->nullable();
                $table->string('note', 255)->nullable();
                $table->timestamps();

                $table->index(['user_id', 'effective_from']);
                $table->index('employee_shift_id');
            });
        }

        Schema::table('employee_shift_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_shift_schedules', 'assignment_id')) {
                $table->unsignedBigInteger('assignment_id')->nullable()->after('employee_shift_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_shift_schedules', function (Blueprint $table) {
            $table->dropColumn('assignment_id');
        });

        Schema::dropIfExists('employee_shift_assignments');
    }
};
