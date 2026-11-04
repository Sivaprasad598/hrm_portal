<?php

use App\Services\Employee\RoleAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment: job positions, the candidates who come in (careers mailbox,
 * the website's intake API, or typed in by HR), their applications moving
 * through the hiring stages, interviews with scorecards, offers, and the
 * hand-over to Add Employee once a candidate joins.
 *
 *   recruit_settings        mailbox (IMAP), website API key, AI auto-fill, lists HR edits
 *   job_positions           the openings
 *   candidates              one row per person: the talent pool ("Resumes")
 *   candidate_files         resume, documents, signed offer
 *   job_applications        candidate x position, with the stage
 *   recruit_logs            the timeline: every stage change, note, email, interview, offer (append only)
 *   recruit_inbound         every email / API submission received - the proof, and what stops duplicates
 *   recruit_interviews      a round with a candidate; linked to an Event for the calendar
 *   recruit_interview_feedback  one scorecard per interviewer
 *   job_offers              the offer, its letter and the acceptance proof
 *
 * Three permissions (all / none): view_recruitment, manage_recruitment,
 * manage_recruitment_settings; and a "Recruitment" sidebar group after HR.
 */
return new class extends Migration {

    private const ALL = 4;
    private const NONE = 5;

    /** permission => [label, description, roles holding it]. */
    private const PERMISSIONS = [
        'view_recruitment' => ['View Recruitment', 'See positions, candidates, interviews and offers.', ['admin', 'hr', 'manager_admin', 'ceo']],
        'manage_recruitment' => ['Manage Recruitment', 'Add positions and candidates, move them through the stages, schedule interviews, make offers.', ['admin', 'hr', 'manager_admin']],
        'manage_recruitment_settings' => ['Recruitment Settings', 'The careers mailbox, the website API key and the recruitment lists.', ['admin', 'hr', 'manager_admin']],
    ];

    public function up(): void
    {
        $this->tables();
        $this->permissions();
        $this->sidebar();

        RoleAccess::applyAll();
        \App\Models\User::forgetAllMenuCaches();
    }

    private function tables(): void
    {
        if (!Schema::hasTable('recruit_settings')) {
            Schema::create('recruit_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->unique();
                // Careers mailbox
                $table->boolean('mailbox_enabled')->default(false);
                $table->string('imap_host', 191)->nullable();
                $table->unsignedSmallInteger('imap_port')->nullable();
                $table->string('imap_encryption', 10)->default('ssl');
                $table->string('imap_username', 191)->nullable();
                $table->text('imap_password')->nullable();          // encrypted
                $table->string('imap_folder', 191)->default('INBOX');
                $table->text('mail_keywords')->nullable();          // comma separated
                $table->boolean('mail_require_attachment')->default(false);
                $table->text('mail_ignore_senders')->nullable();    // comma separated
                $table->unsignedSmallInteger('mail_lookback_days')->default(7);
                $table->timestamp('mail_last_fetched_at')->nullable();
                $table->string('mail_last_result', 500)->nullable();
                // Website intake API
                $table->boolean('api_enabled')->default(false);
                $table->string('api_key_hash', 64)->nullable();
                $table->string('api_key_hint', 20)->nullable();
                $table->timestamp('api_key_created_at')->nullable();
                // Behaviour and lists
                $table->boolean('ai_autofill')->default(true);
                $table->text('rejection_reasons')->nullable();      // one per line
                $table->text('interview_rounds')->nullable();       // one per line
                $table->text('scorecard_questions')->nullable();    // one per line
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('job_positions')) {
            Schema::create('job_positions', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->string('code', 20);
                $table->string('title', 191);
                $table->unsignedInteger('department_id')->nullable();
                $table->unsignedBigInteger('designation_id')->nullable();
                $table->unsignedInteger('branch_id')->nullable();
                $table->unsignedSmallInteger('openings')->default(1);
                $table->string('employment_type', 20)->default('full_time');
                $table->decimal('experience_min', 4, 1)->nullable();
                $table->decimal('experience_max', 4, 1)->nullable();
                $table->decimal('salary_min', 12, 2)->nullable();
                $table->decimal('salary_max', 12, 2)->nullable();
                $table->string('location', 191)->nullable();
                $table->text('skills')->nullable();
                $table->longText('description')->nullable();
                $table->string('status', 20)->default('open')->index();
                $table->date('closing_date')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'code']);
            });
        }

        if (!Schema::hasTable('candidates')) {
            Schema::create('candidates', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->string('name', 191);
                $table->string('email', 191)->nullable()->index();
                $table->string('mobile', 20)->nullable()->index();
                $table->string('gender', 10)->nullable();
                $table->date('date_of_birth')->nullable();
                $table->text('address')->nullable();
                $table->string('current_location', 191)->nullable();
                $table->string('qualification', 191)->nullable();
                $table->decimal('total_experience', 4, 1)->nullable();
                $table->string('current_company', 191)->nullable();
                $table->string('current_designation', 191)->nullable();
                $table->decimal('current_ctc', 12, 2)->nullable();
                $table->decimal('expected_ctc', 12, 2)->nullable();
                $table->unsignedSmallInteger('notice_period_days')->nullable();
                $table->text('skills')->nullable();                  // json list
                $table->longText('work_experience')->nullable();     // json, the shape ResumeExtractor returns
                $table->string('source', 20)->default('manual');     // email | website | manual | referral | other
                $table->string('source_detail', 191)->nullable();
                $table->timestamp('ai_filled_at')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('user_id')->nullable()->index();   // the employee they became
                $table->timestamp('converted_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('candidate_files')) {
            Schema::create('candidate_files', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
                $table->string('category', 20)->default('document');  // resume | document | signed_offer
                $table->string('name', 191);
                $table->string('filename', 191);
                $table->string('hashname', 191);
                $table->unsignedInteger('size')->nullable();
                $table->string('mime', 100)->nullable();
                $table->unsignedInteger('added_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('job_applications')) {
            Schema::create('job_applications', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
                $table->foreignId('job_position_id')->nullable()->constrained('job_positions')->nullOnDelete();
                $table->string('stage', 20)->default('new')->index();
                $table->string('stage_reason', 191)->nullable();
                $table->timestamp('stage_changed_at')->nullable();
                $table->string('source', 20)->default('manual');
                $table->text('cover_note')->nullable();
                $table->unsignedTinyInteger('rating')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('recruit_logs')) {
            Schema::create('recruit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
                $table->unsignedBigInteger('job_application_id')->nullable()->index();
                $table->string('action', 30);
                $table->string('from_stage', 20)->nullable();
                $table->string('to_stage', 20)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('done_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('recruit_inbound')) {
            Schema::create('recruit_inbound', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->string('channel', 10);                       // email | api
                $table->string('external_id', 191);                  // Message-ID, or a hash of the API submission
                $table->string('from_email', 191)->nullable();
                $table->string('from_name', 191)->nullable();
                $table->string('subject', 500)->nullable();
                $table->longText('body')->nullable();
                $table->longText('payload')->nullable();             // json: the API fields, the email's attachment names
                $table->string('status', 20);                        // imported | updated | skipped | failed
                $table->string('reason', 500)->nullable();
                $table->unsignedBigInteger('candidate_id')->nullable()->index();
                $table->unsignedBigInteger('job_application_id')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'channel', 'external_id'], 'recruit_inbound_unique');
            });
        }

        if (!Schema::hasTable('recruit_interviews')) {
            Schema::create('recruit_interviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->foreignId('job_application_id')->constrained('job_applications')->cascadeOnDelete();
                $table->unsignedInteger('event_id')->nullable();
                $table->string('round', 100);
                $table->string('mode', 20)->default('in_person');    // in_person | phone | video
                $table->dateTime('starts_at')->index();              // UTC
                $table->unsignedSmallInteger('duration')->default(30);
                $table->string('location', 191)->nullable();
                $table->string('meeting_link', 191)->nullable();
                $table->text('interviewer_ids');                     // json list of user ids
                $table->string('status', 20)->default('scheduled')->index();   // scheduled | completed | no_show | cancelled
                $table->text('notes')->nullable();
                $table->text('status_note')->nullable();
                $table->boolean('candidate_notified')->default(false);
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('recruit_interview_feedback')) {
            Schema::create('recruit_interview_feedback', function (Blueprint $table) {
                $table->id();
                $table->foreignId('recruit_interview_id')->constrained('recruit_interviews')->cascadeOnDelete();
                $table->unsignedInteger('user_id');
                $table->text('ratings')->nullable();                 // json: question => 1..5
                $table->unsignedTinyInteger('overall')->nullable();
                $table->string('recommendation', 10);                // hire | maybe | no_hire
                $table->text('comments')->nullable();
                $table->timestamps();
                $table->unique(['recruit_interview_id', 'user_id'], 'recruit_feedback_unique');
            });
        }

        if (!Schema::hasTable('job_offers')) {
            Schema::create('job_offers', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->foreignId('job_application_id')->constrained('job_applications')->cascadeOnDelete();
                $table->string('reference_no', 50)->nullable();
                $table->unsignedBigInteger('designation_id')->nullable();
                $table->unsignedInteger('department_id')->nullable();
                $table->unsignedInteger('branch_id')->nullable();
                $table->decimal('annual_ctc', 12, 2);
                $table->date('offer_date');
                $table->date('joining_date');
                $table->date('valid_until')->nullable();
                $table->string('status', 20)->default('draft');      // draft | sent | accepted | declined | withdrawn
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->text('response_note')->nullable();
                $table->unsignedBigInteger('signed_file_id')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    private function permissions(): void
    {
        $moduleId = DB::table('modules')->where('module_name', 'employees')->value('id');
        $roles = DB::table('roles')->get(['id', 'name']);

        foreach (self::PERMISSIONS as $name => [$label, $description, $holders]) {
            if (DB::table('permissions')->where('name', $name)->exists()) {
                continue;
            }

            $permissionId = DB::table('permissions')->insertGetId([
                'name' => $name, 'display_name' => $label, 'description' => $description, 'module_id' => $moduleId,
                'is_custom' => 1, 'allowed_permissions' => '{"all":4, "none":5}', 'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach ($roles as $role) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $role->id, 'permission_id' => $permissionId,
                    'permission_type_id' => in_array($role->name, $holders, true) ? self::ALL : self::NONE,
                ]);
            }

            // user_permissions is what the application reads: fill it in for everybody on file.
            $holding = DB::table('role_user as ru')->join('roles as r', 'r.id', '=', 'ru.role_id')
                ->whereIn('r.name', $holders)->pluck('ru.user_id')->flip();

            $rows = DB::table('users')->pluck('id')->map(fn ($userId) => [
                'user_id' => $userId, 'permission_id' => $permissionId,
                'permission_type_id' => $holding->has($userId) ? self::ALL : self::NONE,
            ])->all();

            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('user_permissions')->insertOrIgnore($chunk);
            }
        }
    }

    private function sidebar(): void
    {
        if (DB::table('sidebar_menus')->where('slug', 'recruitment')->whereNull('parent_id')->exists()) {
            return;
        }

        $hr = DB::table('sidebar_menus')->whereNull('parent_id')->where('slug', 'hr')->first();
        $position = $hr ? $hr->sort_order + 1 : 3;
        $companyId = $hr->company_id ?? DB::table('sidebar_menus')->whereNull('parent_id')->value('company_id');

        DB::table('sidebar_menus')->whereNull('parent_id')->where('sort_order', '>=', $position)->increment('sort_order');

        $parentId = DB::table('sidebar_menus')->insertGetId([
            'company_id' => $companyId, 'parent_id' => null, 'name' => 'Recruitment', 'slug' => 'recruitment', 'route' => null,
            'icon' => 'bi bi-briefcase', 'level' => 0, 'sort_order' => $position, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $children = [
            ['Job Dashboard', 'recruitment-dashboard', 'recruitment.dashboard', 'view_recruitment'],
            ['Positions', 'job-positions-index', 'job-positions.index', 'view_recruitment'],
            ['Applicants', 'job-applications-index', 'job-applications.index', 'view_recruitment'],
            ['Resumes', 'candidates-index', 'candidates.index', 'view_recruitment'],
            ['Interviews', 'recruit-interviews-index', 'recruit-interviews.index', 'view_recruitment'],
            ['Recruitment Settings', 'recruitment-settings-index', 'recruitment-settings.index', 'manage_recruitment_settings'],
        ];

        foreach ($children as $i => [$name, $slug, $route, $permission]) {
            DB::table('sidebar_menus')->insert([
                'company_id' => $companyId, 'parent_id' => $parentId, 'name' => $name, 'slug' => $slug, 'route' => $route,
                'level' => 1, 'sort_order' => $i + 1, 'is_active' => 1,
                'permission_key' => $permission, 'permission_rule' => 'setting_all',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // The Super Admin sees everything; the admin role is given the menus like the other areas.
        if ($adminRole = DB::table('roles')->where('name', 'admin')->value('id')) {
            $ids = DB::table('sidebar_menus')->where('parent_id', $parentId)->pluck('id')->push($parentId);

            foreach ($ids as $menuId) {
                DB::table('role_menus')->insertOrIgnore(['role_id' => $adminRole, 'menu_id' => $menuId, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $menuIds = DB::table('sidebar_menus')->where('slug', 'recruitment')->whereNull('parent_id')->pluck('id');
        $childIds = DB::table('sidebar_menus')->whereIn('parent_id', $menuIds)->pluck('id');
        DB::table('role_menus')->whereIn('menu_id', $menuIds->merge($childIds))->delete();
        DB::table('sidebar_menus')->whereIn('id', $childIds->merge($menuIds))->delete();

        foreach (array_keys(self::PERMISSIONS) as $name) {
            if ($permissionId = DB::table('permissions')->where('name', $name)->value('id')) {
                DB::table('user_permissions')->where('permission_id', $permissionId)->delete();
                DB::table('permission_role')->where('permission_id', $permissionId)->delete();
                DB::table('permissions')->where('id', $permissionId)->delete();
            }
        }

        foreach (['job_offers', 'recruit_interview_feedback', 'recruit_interviews', 'recruit_inbound', 'recruit_logs',
            'job_applications', 'candidate_files', 'candidates', 'job_positions', 'recruit_settings'] as $table) {
            Schema::dropIfExists($table);
        }

        \App\Models\User::forgetAllMenuCaches();
    }

};
