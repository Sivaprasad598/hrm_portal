<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Lead Team uploads are only accepted when the file name names an approved category (LeadChatController::CATEGORIES):
     * Deliverables, Survey, Engineering Requirements, Capacity, Assumptions, Dependencies. The matched category is
     * saved with the file.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('lead_chat_attachments', 'category')) {
            Schema::table('lead_chat_attachments', function (Blueprint $table) {
                $table->string('category', 60)->nullable()->after('filename');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('lead_chat_attachments', 'category')) {
            Schema::table('lead_chat_attachments', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }

};
