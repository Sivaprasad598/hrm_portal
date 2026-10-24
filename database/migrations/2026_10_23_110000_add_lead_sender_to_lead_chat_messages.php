<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Team Chat: a chat with the lead (conversation type "lead": the lead, the lead owner and the team), separate from
     * the internal team group. Messages written by the lead have no portal user (from_lead = 1, user_id NULL); the lead
     * will post once leads have a login.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('lead_chat_messages', 'from_lead')) {
            Schema::table('lead_chat_messages', function (Blueprint $table) {
                $table->boolean('from_lead')->default(false)->after('user_id');
            });
        }

        DB::statement('ALTER TABLE lead_chat_messages MODIFY user_id INT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::table('lead_chat_messages')->whereNull('user_id')->delete();
        DB::statement('ALTER TABLE lead_chat_messages MODIFY user_id INT UNSIGNED NOT NULL');

        if (Schema::hasColumn('lead_chat_messages', 'from_lead')) {
            Schema::table('lead_chat_messages', function (Blueprint $table) {
                $table->dropColumn('from_lead');
            });
        }
    }

};
