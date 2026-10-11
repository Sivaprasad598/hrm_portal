<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A resumable history pull.
 *
 * The punch feed costs roughly two minutes per day of history, so pulling a
 * month — let alone the twenty months the devices retain — cannot happen inside
 * one request, and this installation runs the `sync` queue driver, so a queued
 * job would run inline and time out just the same.
 *
 * A run is therefore state, not a process: it records where it has reached, and
 * short time-boxed steps move the cursor forward. Closing the browser pauses it;
 * reopening resumes from the same second.
 */
return new class extends Migration
{

    public function up(): void
    {
        if (Schema::hasTable('iapp_backfill_runs')) {
            return;
        }

        Schema::create('iapp_backfill_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();

            $table->date('from_date');
            $table->date('to_date');

            // How far the pull has got. Everything up to and including this
            // instant is stored; the next step resumes strictly after it.
            $table->timestamp('cursor_at')->nullable();

            $table->string('status', 20)->default('running')->index();

            $table->unsignedBigInteger('punches_fetched')->default(0);
            $table->unsignedBigInteger('punches_created')->default(0);
            $table->unsignedInteger('days_built')->default(0);
            $table->unsignedInteger('steps')->default(0);
            $table->unsignedBigInteger('elapsed_ms')->default(0);

            $table->text('message')->nullable();
            $table->text('last_error')->nullable();

            $table->unsignedBigInteger('started_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            // One active run per company: two pulls writing the same punches
            // would only duplicate effort.
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iapp_backfill_runs');
    }

};
