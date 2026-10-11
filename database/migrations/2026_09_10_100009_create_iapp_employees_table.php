<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biometric-side employee register, and the link to the ERP employee.
     *
     * The biometric platform is the source of truth for who exists on the
     * devices; nothing in this module ever writes an employee back to iApp.
     * This table mirrors what the devices report and holds the mapping to a
     * `users` row alongside it.
     *
     * The relation is many-to-one on purpose: the live feed contains codes
     * like "2301694" and "02301694", which are separate device enrolments that
     * may well be the same person. Several biometric codes can therefore point
     * at one ERP user, and each keeps its own identity and punch history.
     */
    public function up(): void
    {
        if (Schema::hasTable('iapp_employees')) {
            return;
        }

        Schema::create('iapp_employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->nullable();

            // The biometric reference. This is the key everything else joins
            // on, and it is what the device reports as empCode.
            $table->string('emp_code', 100);

            $table->string('employee_name')->nullable();
            $table->string('card_id', 100)->nullable();
            $table->string('location_code', 20)->nullable();
            $table->string('department')->nullable();
            $table->string('designation')->nullable();

            // The ERP side of the mapping. Null means unmapped, which is a
            // normal state, not an error — it is what the review screen lists.
            $table->unsignedInteger('user_id')->nullable();

            // How the link was made, so an automatic guess can be told apart
            // from a decision somebody made.
            $table->string('map_status', 20)->default('unmapped');
            $table->string('map_method', 30)->nullable();
            $table->string('matched_on', 40)->nullable();
            $table->unsignedInteger('mapped_by')->nullable();
            $table->timestamp('mapped_at')->nullable();
            $table->text('map_note')->nullable();

            // Activity, derived from the punch feed. Lets the review screen
            // put the codes that actually punch at the top.
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('punch_count')->default(0);

            $table->boolean('is_active')->default(1);
            $table->string('source', 20)->default('punch_feed');
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'emp_code'], 'iapp_employee_natural_key');
            $table->index('user_id', 'iapp_employee_user_idx');
            $table->index(['company_id', 'map_status'], 'iapp_employee_status_idx');
            $table->index('last_seen_at', 'iapp_employee_seen_idx');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iapp_employees');
    }
};
