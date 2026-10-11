<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-day record of what the last pull for that date found.
 *
 * The punch and attendance tables cannot answer this on their own: a date with
 * no rows might never have been pulled, or might have been pulled and found
 * genuinely empty — a Sunday, a holiday, a shut site. The calendar has to tell
 * those apart, so every sync path writes its outcome here.
 */
return new class extends Migration
{

    public function up(): void
    {
        if (Schema::hasTable('iapp_day_syncs')) {
            return;
        }

        Schema::create('iapp_day_syncs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();

            $table->date('sync_date');

            $table->timestamp('last_synced_at')->nullable();

            // 'ok' found punches, 'empty' pulled but the day held none,
            // 'failed' errored. A date absent from this table is "never".
            $table->string('status', 20)->default('ok');

            $table->unsignedInteger('punch_count')->default(0);
            $table->unsignedInteger('employee_count')->default(0);
            $table->unsignedInteger('attendance_days')->default(0);
            $table->unsignedInteger('sync_count')->default(0);
            $table->unsignedBigInteger('duration_ms')->default(0);

            $table->unsignedBigInteger('last_run_id')->nullable();
            $table->unsignedBigInteger('synced_by')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            // One row per company per date; every write is an upsert on it.
            $table->unique(['company_id', 'sync_date'], 'iapp_day_syncs_company_date_unique');
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iapp_day_syncs');
    }

};
