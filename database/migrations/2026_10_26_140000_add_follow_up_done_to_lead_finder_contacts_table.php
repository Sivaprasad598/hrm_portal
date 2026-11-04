<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /** Lead Finder → Follow-ups: a follow-up asked for in a contact detail can be marked done (and undone). */
    public function up(): void
    {
        Schema::table('lead_finder_contacts', function (Blueprint $table) {
            $table->boolean('follow_up_done')->default(false)->after('follow_up_date');
            $table->timestamp('follow_up_done_at')->nullable()->after('follow_up_done');
        });
    }

    public function down(): void
    {
        Schema::table('lead_finder_contacts', function (Blueprint $table) {
            $table->dropColumn(['follow_up_done', 'follow_up_done_at']);
        });
    }

};
