<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookkeeping for pushing device punches into the ERP's `attendances` table.
 *
 * `attendances` itself is left exactly as it is — no new column, no new flag.
 * It cannot record which rows came from the device anyway: the Excel importer
 * already stamps `clock_in_ip` as 127.0.0.1, so a device-written row would be
 * indistinguishable from an imported one. The link is kept here instead.
 *
 * That matters for two reasons. It is how the sync knows which rows are its own
 * and may be replaced, so it can never delete attendance somebody entered by
 * hand without being told to. And when an override does replace existing rows,
 * their full contents are kept here first, so the decision is reversible.
 */
return new class extends Migration
{

    public function up(): void
    {
        if (!Schema::hasTable('iapp_attendance_sync_runs')) {
            Schema::create('iapp_attendance_sync_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();

                $table->date('from_date');
                $table->date('to_date');

                // 'skip' leaves a conflicting day alone; 'override' replaces it,
                // keeping a copy of what was there.
                $table->string('conflict_mode', 20)->default('skip');

                $table->string('status', 20)->default('done')->index();

                $table->unsignedInteger('employees')->default(0);
                $table->unsignedInteger('days')->default(0);
                $table->unsignedInteger('punches_sent')->default(0);
                $table->unsignedInteger('rows_created')->default(0);
                $table->unsignedInteger('rows_replaced')->default(0);
                $table->unsignedInteger('days_skipped')->default(0);

                $table->json('summary')->nullable();
                $table->text('message')->nullable();

                $table->unsignedBigInteger('started_by')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('iapp_attendance_links')) {
            Schema::create('iapp_attendance_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('run_id')->nullable()->index();

                // Null once the referenced attendance row is gone: the link is
                // audit history, so it outlives the row it describes.
                $table->unsignedBigInteger('attendance_id')->nullable()->index();

                $table->unsignedBigInteger('user_id')->index();
                $table->date('attendance_date');
                $table->string('emp_code', 100)->nullable();

                // 'created' — written by this module and safe to replace.
                // 'replaced' — existed before, removed by an override; `payload`
                // holds the whole row so it can be put back.
                $table->string('link_type', 20)->default('created');
                $table->json('payload')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'user_id', 'attendance_date'], 'iapp_att_links_lookup');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('iapp_attendance_links');
        Schema::dropIfExists('iapp_attendance_sync_runs');
    }

};
