<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Team Chat: a message can be a reply to an earlier message in the same chat (shown as a quote above it).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('lead_chat_messages', 'reply_to_id')) {
            Schema::table('lead_chat_messages', function (Blueprint $table) {
                $table->unsignedBigInteger('reply_to_id')->nullable()->after('conversation_id');
                $table->foreign('reply_to_id')->references('id')->on('lead_chat_messages')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('lead_chat_messages', 'reply_to_id')) {
            Schema::table('lead_chat_messages', function (Blueprint $table) {
                $table->dropForeign(['reply_to_id']);
                $table->dropColumn('reply_to_id');
            });
        }
    }

};
