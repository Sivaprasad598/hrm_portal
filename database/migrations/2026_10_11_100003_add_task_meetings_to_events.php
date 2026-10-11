<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Task meetings reuse the Events module (calendar, reminders, Google Calendar):
     * - events.task_id links a meeting to the task it discusses
     * - events.meeting_outcome / completed_at hold the minutes once it is done
     * - events.follow_up_of links a follow-up meeting to the earlier one
     * - event_attendees.response tracks accept / decline
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'task_id')) {
                $table->unsignedInteger('task_id')->nullable()->after('company_id');
                $table->foreign('task_id')->references('id')->on('tasks')->nullOnDelete()->cascadeOnUpdate();
            }

            if (!Schema::hasColumn('events', 'meeting_outcome')) {
                $table->text('meeting_outcome')->nullable()->after('note');
                $table->timestamp('completed_at')->nullable()->after('meeting_outcome');
            }

            if (!Schema::hasColumn('events', 'follow_up_of')) {
                $table->unsignedInteger('follow_up_of')->nullable()->after('task_id');
                $table->foreign('follow_up_of')->references('id')->on('events')->nullOnDelete()->cascadeOnUpdate();
            }
        });

        Schema::table('event_attendees', function (Blueprint $table) {
            if (!Schema::hasColumn('event_attendees', 'response')) {
                $table->enum('response', ['pending', 'accepted', 'declined'])->default('pending')->after('event_id');
                $table->timestamp('responded_at')->nullable()->after('response');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_attendees', function (Blueprint $table) {
            $table->dropColumn(['response', 'responded_at']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['task_id']);
            $table->dropForeign(['follow_up_of']);
            $table->dropColumn(['task_id', 'follow_up_of', 'meeting_outcome', 'completed_at']);
        });
    }

};
