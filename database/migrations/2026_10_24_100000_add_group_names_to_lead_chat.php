<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Team Chat group names, stored:
     *   lead_chat_conversations.name   the chat's name
     *       group  -> lead's project name + " Team"   (e.g. "Jayabheri constructions Team")
     *       lead   -> lead's name + " (Lead)"         (e.g. "Ai Adhyapak (Lead)")
     *       direct -> "Private: <name> & <name>"
     *   lead_chat_messages.group_name  the name of the chat each message is in (next to lead_id and conversation_id)
     * Kept in step with renamed projects / leads by LeadChatConversation::syncName(). Existing rows are filled in here.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('lead_chat_conversations', 'name')) {
            Schema::table('lead_chat_conversations', function (Blueprint $table) {
                $table->string('name', 191)->nullable()->after('type');
            });
        }

        if (!Schema::hasColumn('lead_chat_messages', 'group_name')) {
            Schema::table('lead_chat_messages', function (Blueprint $table) {
                $table->string('group_name', 191)->nullable()->after('lead_id');
            });
        }

        foreach (DB::table('lead_chat_conversations')->get() as $conversation) {
            $lead = DB::table('leads')->where('id', $conversation->lead_id)->first(['client_name', 'lead_project_id']);
            $project = $lead?->lead_project_id ? DB::table('lead_projects')->where('id', $lead->lead_project_id)->value('name') : null;
            $leadName = $lead?->client_name ?: 'Lead';

            $name = match ($conversation->type) {
                'group' => trim(($project ?: $leadName) . ' Team'),
                'lead' => trim($leadName . ' (Lead)'),
                default => 'Private: ' . DB::table('users')->whereIn('id', [$conversation->user_one, $conversation->user_two])
                    ->orderBy('name')->pluck('name')->implode(' & '),
            };

            DB::table('lead_chat_conversations')->where('id', $conversation->id)->update(['name' => $name]);
            DB::table('lead_chat_messages')->where('conversation_id', $conversation->id)->update(['group_name' => $name]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('lead_chat_messages', 'group_name')) {
            Schema::table('lead_chat_messages', function (Blueprint $table) {
                $table->dropColumn('group_name');
            });
        }

        if (Schema::hasColumn('lead_chat_conversations', 'name')) {
            Schema::table('lead_chat_conversations', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }
    }

};
