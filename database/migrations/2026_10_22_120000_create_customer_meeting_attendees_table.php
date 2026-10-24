<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Who a lead meeting invites: ticked on the lead's "Lead Team" tab or in the meeting form's Invite list.
     * One row per person: the lead contact itself (is_lead, no user), the lead owner or a team member (user_id).
     * Name and email are kept as they were when invited.
     */
    public function up(): void
    {
        if (!Schema::hasTable('customer_meeting_attendees')) {
            Schema::create('customer_meeting_attendees', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_meeting_id');
                $table->unsignedInteger('user_id')->nullable();
                $table->boolean('is_lead')->default(false);
                $table->string('role', 60)->nullable();
                $table->string('name', 191);
                $table->string('email', 191)->nullable();
                $table->timestamps();

                $table->index('customer_meeting_id', 'cm_attendees_meeting_idx');
                $table->foreign('customer_meeting_id', 'cm_attendees_meeting_fk')->references('id')->on('customer_meetings')->cascadeOnDelete();
                $table->foreign('user_id', 'cm_attendees_user_fk')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_meeting_attendees');
    }

};
