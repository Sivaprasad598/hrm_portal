<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_details', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_details', 'probation_review_status')) {
                // null = not yet due for review. 'pending' = completion date has
                // arrived and is awaiting an admin decision. 'approved' / 'rejected'
                // = decided (mirrors resignations.status).
                $table->string('probation_review_status', 20)->nullable()->after('probation_notified_at');
            }

            if (!Schema::hasColumn('employee_details', 'probation_admin_remarks')) {
                $table->text('probation_admin_remarks')->nullable()->after('probation_review_status');
            }

            if (!Schema::hasColumn('employee_details', 'probation_reviewed_by')) {
                $table->unsignedInteger('probation_reviewed_by')->nullable()->after('probation_admin_remarks');
                $table->foreign('probation_reviewed_by')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('SET NULL');
            }

            if (!Schema::hasColumn('employee_details', 'probation_reviewed_at')) {
                $table->timestamp('probation_reviewed_at')->nullable()->after('probation_reviewed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_details', function (Blueprint $table) {
            if (Schema::hasColumn('employee_details', 'probation_reviewed_by')) {
                $table->dropForeign(['probation_reviewed_by']);
            }

            $table->dropColumn(array_filter([
                Schema::hasColumn('employee_details', 'probation_review_status') ? 'probation_review_status' : null,
                Schema::hasColumn('employee_details', 'probation_admin_remarks') ? 'probation_admin_remarks' : null,
                Schema::hasColumn('employee_details', 'probation_reviewed_by') ? 'probation_reviewed_by' : null,
                Schema::hasColumn('employee_details', 'probation_reviewed_at') ? 'probation_reviewed_at' : null,
            ]));
        });
    }
};
