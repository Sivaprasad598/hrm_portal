<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * What kind of meeting a row is, set when it is created:
     *   lead              - a meeting with a lead (Meetings page, the lead's Meetings tab)
     *   client            - a meeting with a client
     *   lead_team_meeting - made from a lead's Lead Team tab; it also has its own lead_team_meetings record (LTM-0001)
     * Lead meetings and Lead Team meetings are listed separately by this role.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('customer_meetings', 'meeting_role')) {
            Schema::table('customer_meetings', function (Blueprint $table) {
                $table->string('meeting_role', 30)->default('lead')->after('client_id')->index('customer_meetings_role_idx');
            });
        }

        DB::table('customer_meetings')->whereNotNull('client_id')->update(['meeting_role' => 'client']);
        DB::table('customer_meetings')->whereNull('client_id')
            ->whereIn('id', DB::table('lead_team_meetings')->select('customer_meeting_id'))
            ->update(['meeting_role' => 'lead_team_meeting']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('customer_meetings', 'meeting_role')) {
            Schema::table('customer_meetings', function (Blueprint $table) {
                $table->dropIndex('customer_meetings_role_idx');
                $table->dropColumn('meeting_role');
            });
        }
    }

};
