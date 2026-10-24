<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Team Chat messages can be edited and deleted by their sender (administrators can delete any).
     * edited_at: shown as "edited". deleted_at: the message stays in the thread as "This message was deleted";
     * its text is cleared and its files are removed.
     */
    public function up(): void
    {
        Schema::table('lead_chat_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_chat_messages', 'edited_at')) {
                $table->timestamp('edited_at')->nullable()->after('body');
            }

            if (!Schema::hasColumn('lead_chat_messages', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('edited_at');
                $table->unsignedInteger('deleted_by')->nullable()->after('deleted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lead_chat_messages', function (Blueprint $table) {
            $table->dropColumn(['edited_at', 'deleted_at', 'deleted_by']);
        });
    }

};
