<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Lead Team Meetings get their own record with its own ID (LTM-0001), made when the meeting is created from a
     * lead's Lead Team tab: lead_id links it to the lead, customer_meeting_id to the scheduled meeting (date, link,
     * invites, recording). The project qualification notes (lead_meetings) are columns of this record.
     */
    public function up(): void
    {
        if (Schema::hasTable('lead_meetings') && !Schema::hasTable('lead_team_meetings')) {
            Schema::rename('lead_meetings', 'lead_team_meetings');
        }

        if (!Schema::hasColumn('lead_team_meetings', 'created_by')) {
            Schema::table('lead_team_meetings', function (Blueprint $table) {
                $table->unsignedInteger('created_by')->nullable()->after('lead_id');
            });
        }

        // Lead Team meetings made before this record existed: lead meetings with an Invite list
        $existing = DB::table('lead_team_meetings')->pluck('customer_meeting_id');

        DB::table('customer_meetings')
            ->whereNotNull('lead_id')->whereNull('client_id')
            ->whereIn('id', DB::table('customer_meeting_attendees')->select('customer_meeting_id'))
            ->whereNotIn('id', $existing)
            ->orderBy('id')
            ->get(['id', 'lead_id', 'added_by', 'created_at'])
            ->each(fn ($m) => DB::table('lead_team_meetings')->insert([
                'company_id' => DB::table('leads')->where('id', $m->lead_id)->value('company_id'),
                'customer_meeting_id' => $m->id,
                'lead_id' => $m->lead_id,
                'created_by' => $m->added_by,
                'created_at' => $m->created_at,
                'updated_at' => now(),
            ]));
    }

    public function down(): void
    {
        if (Schema::hasColumn('lead_team_meetings', 'created_by')) {
            Schema::table('lead_team_meetings', fn (Blueprint $table) => $table->dropColumn('created_by'));
        }

        if (Schema::hasTable('lead_team_meetings') && !Schema::hasTable('lead_meetings')) {
            Schema::rename('lead_team_meetings', 'lead_meetings');
        }
    }

};
