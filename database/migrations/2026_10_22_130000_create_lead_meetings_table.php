<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Lead Team Meeting notes: project qualification of a Lead Team meeting, from its transcript
     * (Validate Fit, Service Requirements, Buying Entity, Timing, Decision Makers). One row per meeting.
     */
    public function up(): void
    {
        if (Schema::hasTable('lead_meetings')) {
            return;
        }

        Schema::create('lead_meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('customer_meeting_id')->unique();
            $table->unsignedInteger('lead_id')->index();
            $table->string('validate_fit', 10)->nullable(); // high | medium | low
            $table->text('fit_reason')->nullable();
            $table->json('service_requirements')->nullable();
            $table->text('buying_entity')->nullable();
            $table->text('timing')->nullable();
            $table->json('decision_makers')->nullable();
            $table->json('team_capabilities')->nullable(); // departments/designations the fit was judged against
            $table->string('source', 10)->nullable(); // ai | manual
            $table->timestamp('analysed_at')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('customer_meeting_id')->references('id')->on('customer_meetings')->cascadeOnDelete();
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_meetings');
    }

};
