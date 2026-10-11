<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_time_logs', function (Blueprint $table) {
            $table->boolean('rejected')->default(false);
            $table->unsignedInteger('rejected_by')->nullable()->index('project_time_logs_rejected_by_foreign');
            $table->foreign(['rejected_by'])->references(['id'])->on('users')->onUpdate('CASCADE')->onDelete('SET NULL');
            $table->dateTime('rejected_at')->nullable();
            $table->text('reject_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_time_logs', function (Blueprint $table) {
            $table->dropColumn('rejected');
            $table->dropColumn('rejected_by');
            $table->dropColumn('rejected_at');
            $table->dropColumn('reject_reason');
        });
    }
};
