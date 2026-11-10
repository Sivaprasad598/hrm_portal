<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment rounds, run by the system (RecruitAutomation):
 *
 *  - recruit_rounds: the round plan - a company default (job_position_id null)
 *    and, when set, a position's own. Each round is a test / exam (marks
 *    against a cut-off) or a face-to-face interview (the interviewers'
 *    scores, with an optional pass mark out of 5), with the employees who
 *    take it.
 *  - recruit_interviews: which round it is, the marks or the round score,
 *    the result (passed / failed / waived) and the reminders sent. Status
 *    "proposed" is a slot the system suggested, waiting for HR to confirm.
 *  - recruit_settings: slot and reminder timings.
 *  - My Space > My Interviews for the employees on a round.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('recruit_rounds')) {
            Schema::create('recruit_rounds', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('job_position_id')->nullable()->index();   // null = the company's default plan
                $table->unsignedSmallInteger('sort_order')->default(1);
                $table->string('name', 100);
                $table->string('type', 10)->default('interview');    // interview | test
                $table->string('mode', 20)->default('in_person');    // in_person | phone | video
                $table->unsignedSmallInteger('duration')->default(30);
                $table->text('interviewer_ids')->nullable();          // json: the employees who take / evaluate it
                $table->decimal('max_marks', 6, 2)->nullable();       // test: out of
                $table->decimal('cut_off', 6, 2)->nullable();         // test: marks; interview: average out of 5
                $table->text('questions')->nullable();                // interview: scorecard questions, one per line
                $table->timestamps();
            });
        }

        Schema::table('recruit_interviews', function (Blueprint $table) {
            if (!Schema::hasColumn('recruit_interviews', 'recruit_round_id')) {
                $table->unsignedBigInteger('recruit_round_id')->nullable()->after('job_application_id');
                $table->string('round_type', 10)->default('interview')->after('round');
                $table->decimal('max_marks', 6, 2)->nullable()->after('round_type');
                $table->decimal('cut_off', 6, 2)->nullable()->after('max_marks');
                $table->decimal('marks', 6, 2)->nullable()->after('cut_off');        // test: marks given
                $table->decimal('score', 6, 2)->nullable()->after('marks');          // the round's score: marks, or the interviewers' average
                $table->string('result', 10)->nullable()->after('score');           // passed | failed | waived (null = not decided)
                $table->text('result_note')->nullable()->after('result');
                $table->unsignedInteger('result_by')->nullable()->after('result_note');
                $table->timestamp('result_at')->nullable()->after('result_by');
                $table->timestamp('reminded_at')->nullable();
                $table->timestamp('escalated_at')->nullable();
            }
        });

        Schema::table('recruit_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('recruit_settings', 'slot_lead_hours')) {
                $table->unsignedSmallInteger('slot_lead_hours')->default(24);       // earliest a suggested slot can be
                $table->unsignedSmallInteger('slot_horizon_days')->default(14);     // how far ahead to look
                $table->unsignedSmallInteger('reminder_hours')->default(24);        // scorecard reminder after / between
                $table->unsignedSmallInteger('escalate_hours')->default(48);        // tell HR after
            }
        });

        foreach (DB::table('sidebar_menus')->whereNull('parent_id')->where('slug', 'my-space')->get(['id', 'company_id']) as $space) {
            $this->forCompany((int) $space->company_id, (int) $space->id);
        }

        \App\Models\User::forgetAllMenuCaches();
    }

    private function forCompany(int $companyId, int $spaceId): void
    {
        // The default plan: what the settings listed as rounds, as face-to-face interviews
        if (!DB::table('recruit_rounds')->where('company_id', $companyId)->whereNull('job_position_id')->exists()) {
            $listed = DB::table('recruit_settings')->where('company_id', $companyId)->value('interview_rounds')
                ?: \App\Models\RecruitSetting::DEFAULT_ROUNDS;
            $names = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $listed)))));

            foreach ($names as $i => $name) {
                DB::table('recruit_rounds')->insert([
                    'company_id' => $companyId, 'job_position_id' => null, 'sort_order' => $i + 1, 'name' => mb_substr($name, 0, 100),
                    'type' => 'interview', 'mode' => 'in_person', 'duration' => 30, 'interviewer_ids' => '[]',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // My Space > My Interviews, for whoever is on a round
        $menuId = DB::table('sidebar_menus')->where('company_id', $companyId)->where('slug', 'my-interviews')->value('id');

        if (!$menuId) {
            $menuId = DB::table('sidebar_menus')->insertGetId([
                'company_id' => $companyId, 'parent_id' => $spaceId, 'name' => 'My Interviews', 'slug' => 'my-interviews',
                'route' => 'my-interviews.index', 'icon' => null, 'level' => 1,
                'sort_order' => (int) DB::table('sidebar_menus')->where('parent_id', $spaceId)->max('sort_order') + 1,
                'is_active' => 1, 'permission_key' => null, 'permission_rule' => null, 'role_rule' => 'recruit_interviewer', 'module' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Everyone who has My Space gets the entry; it shows only to interviewers (role_rule)
        foreach (DB::table('role_menus')->where('menu_id', $spaceId)->pluck('role_id') as $roleId) {
            DB::table('role_menus')->updateOrInsert(['role_id' => $roleId, 'menu_id' => $menuId], ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('sidebar_menus')->where('slug', 'my-interviews')->pluck('id');
        DB::table('role_menus')->whereIn('menu_id', $ids)->delete();
        DB::table('sidebar_menus')->whereIn('id', $ids)->delete();

        Schema::table('recruit_settings', fn (Blueprint $t) => $t->dropColumn(['slot_lead_hours', 'slot_horizon_days', 'reminder_hours', 'escalate_hours']));
        Schema::table('recruit_interviews', fn (Blueprint $t) => $t->dropColumn(['recruit_round_id', 'round_type', 'max_marks', 'cut_off', 'marks',
            'score', 'result', 'result_note', 'result_by', 'result_at', 'reminded_at', 'escalated_at']));
        Schema::dropIfExists('recruit_rounds');

        \App\Models\User::forgetAllMenuCaches();
    }
};
