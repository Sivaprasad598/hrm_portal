<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per sync run. Gives the dashboard a history and makes a failed
     * overnight run visible instead of only reaching the log file.
     */
    public function up(): void
    {
        if (Schema::hasTable('iapp_sync_logs')) {
            return;
        }

        Schema::create('iapp_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->nullable();

            $table->string('sync_type', 30);
            $table->string('trigger', 20)->default('manual');
            $table->unsignedInteger('triggered_by')->nullable();

            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();

            $table->string('status', 20)->default('running');

            $table->unsignedInteger('records_fetched')->default(0);
            $table->unsignedInteger('records_created')->default(0);
            $table->unsignedInteger('records_updated')->default(0);
            $table->unsignedInteger('records_skipped')->default(0);
            $table->unsignedInteger('records_promoted')->default(0);
            $table->unsignedInteger('records_unmatched')->default(0);

            // Distinct unmatched emp codes, so they can be fixed in one pass
            // rather than hunting through a log file.
            $table->json('unmatched_codes')->nullable();

            $table->text('message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'sync_type', 'created_at'], 'iapp_sync_log_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iapp_sync_logs');
    }
};
