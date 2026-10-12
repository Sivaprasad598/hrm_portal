<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    // Database of the old HRM portal on the same MySQL server. The data steps below read from it at run time
    // (so no passwords, tokens or personal details are stored in this file) and are skipped when it is absent.
    private const SOURCE_DB = 'hrm_db';

    // Employee brought over from the old portal
    private const EMPLOYEE_EMAIL = 'chuchakondakumar@gmail.com'; // Duddu Kamesh

    /**
     * Customers → Meetings: customers, their meetings (Google Meet / Teams links,
     * results, follow-ups, auto notes), the purpose/status/result dropdown options,
     * the CRM Notes extension keys and the Microsoft Teams connection.
     * Each table is skipped when it already exists.
     *
     * Then, from the old portal's database: the employee above (user, employee details, role, permissions,
     * shift schedule, leave quotas) and the Google Calendar connection. Each is skipped when already present.
     */
    public function up(): void
    {
        $this->createTables();

        if ($this->sourceExists()) {
            $this->copyEmployee(self::EMPLOYEE_EMAIL);
            $this->copyGoogleCalendar();
        }
    }

    private function createTables(): void
    {
        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index('customers_company_idx');
                $table->string('customer_name');
                $table->string('company_name');
                $table->string('email')->nullable();
                $table->string('mobile', 50)->nullable();
                $table->string('lead_owner')->nullable();
                $table->unsignedBigInteger('added_by')->nullable()->index('customers_added_by_idx');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('customer_meetings')) {
            Schema::create('customer_meetings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->index('customer_meetings_customer_idx');
                $table->enum('meeting_type', ['online', 'offline'])->index('customer_meetings_type_idx');
                $table->string('purpose')->nullable();
                $table->string('status', 50)->nullable()->default('Planned');
                $table->string('lead_owner')->nullable();
                $table->string('contact_person')->nullable();
                $table->string('provider', 50)->nullable();
                $table->string('meeting_link', 500)->nullable();
                $table->string('google_event_id')->nullable();
                $table->string('teams_meeting_id', 500)->nullable();
                $table->boolean('auto_notes_enabled')->nullable();
                $table->longText('meet_notes')->nullable();
                $table->string('meet_notes_url', 500)->nullable();
                $table->string('meet_notes_source', 20)->nullable();
                $table->longText('meet_transcript')->nullable();
                $table->string('notes_recording_status', 20)->nullable();
                $table->unsignedInteger('notes_recorded_by')->nullable();
                $table->timestamp('notes_last_upload_at')->nullable();
                $table->timestamp('meet_notes_fetched_at')->nullable();
                $table->timestamp('meet_notes_checked_at')->nullable();
                $table->date('meeting_date')->nullable()->index('customer_meetings_date_idx');
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->string('location')->nullable();
                $table->text('description')->nullable();
                $table->string('result', 20)->nullable();
                $table->string('outcome', 100)->nullable();
                $table->text('outcome_reason')->nullable();
                $table->date('followup_date')->nullable();
                $table->string('followup_action', 100)->nullable();
                $table->text('followup_reason')->nullable();
                $table->unsignedBigInteger('followup_meeting_id')->nullable();
                $table->string('end_reason', 100)->nullable();
                $table->text('end_notes')->nullable();
                $table->timestamp('result_at')->nullable();
                $table->timestamp('reminder_sent_at')->nullable();
                $table->timestamp('ended_notified_at')->nullable();
                $table->unsignedBigInteger('added_by')->nullable();
                $table->timestamps();

                $table->foreign('customer_id', 'customer_meetings_customer_fk')->references('id')->on('customers')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('meeting_options')) {
            Schema::create('meeting_options', function (Blueprint $table) {
                $table->id();
                $table->string('type', 20); // purpose | status | result
                $table->string('name', 100);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['type', 'name']);
            });

            $defaults = [
                'purpose' => ['Project Discussion', 'Requirement Discussion', 'Price Discussion', 'Negotiation', 'Follow-up', 'Other'],
                'status' => ['Planned', 'Completed', 'Cancelled', 'Rescheduled'],
                'result' => ['Interested', 'Not Interested', 'Requirement Understood', 'Quotation Requested', 'Demo Requested', 'Deal Won', 'Need More Information', 'Other'],
            ];

            foreach ($defaults as $type => $names) {
                foreach ($names as $index => $name) {
                    DB::table('meeting_options')->insert([
                        'type' => $type,
                        'name' => $name,
                        'sort_order' => $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if (!Schema::hasTable('crm_notes_tokens')) {
            Schema::create('crm_notes_tokens', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id')->unique();
                $table->string('token_hash', 64)->unique();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('microsoft_teams_settings')) {
            Schema::create('microsoft_teams_settings', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id', 100)->nullable();
                $table->string('client_id', 100)->nullable();
                $table->text('client_secret')->nullable();
                $table->text('token')->nullable();
                $table->string('account_email')->nullable();
                $table->string('account_name')->nullable();
                $table->timestamp('connected_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Copies one employee from the old portal: user, employee details, role, permissions, shift schedule and
     * leave quotas. Skipped when a user with that email already exists. The user id is kept when it is free.
     * Rows pointing at a role, permission, shift or leave type this database does not have are left out.
     */
    private function copyEmployee(string $email): void
    {
        $source = DB::table(self::SOURCE_DB . '.users')->where('email', $email)->first();

        if (!$source || DB::table('users')->where('email', $email)->exists()) {
            return;
        }

        DB::transaction(function () use ($source) {
            $user = $this->sharedRow('users', (array) $source);

            if (DB::table('users')->where('id', $source->id)->exists()) {
                unset($user['id']);
            }

            $userId = DB::table('users')->insertGetId($user);

            $details = DB::table(self::SOURCE_DB . '.employee_details')->where('user_id', $source->id)->first();

            if ($details) {
                $row = $this->sharedRow('employee_details', (array) $details);
                $row['user_id'] = $userId;

                if (DB::table('employee_details')->where('id', $details->id)->exists()) {
                    unset($row['id']);
                }

                DB::table('employee_details')->insert($row);
            }

            $this->copyUserRows('role_user', $source->id, $userId, 'role_id', 'roles');
            $this->copyUserRows('user_permissions', $source->id, $userId, 'permission_id', 'permissions');
            $this->copyUserRows('employee_shift_schedules', $source->id, $userId, 'employee_shift_id', 'employee_shifts');
            $this->copyUserRows('employee_leave_quotas', $source->id, $userId, 'leave_type_id', 'leave_types');
        });
    }

    /** Copies a user's rows of $table (new ids), keeping only those whose $refColumn exists in $refTable here. */
    private function copyUserRows(string $table, int $fromUserId, int $toUserId, string $refColumn, string $refTable): void
    {
        if (!Schema::hasTable($table) || !$this->sourceHasTable($table)) {
            return;
        }

        $known = DB::table($refTable)->pluck('id')->flip();

        $rows = DB::table(self::SOURCE_DB . '.' . $table)->where('user_id', $fromUserId)->get()
            ->filter(fn ($row) => $row->{$refColumn} === null || $known->has($row->{$refColumn}))
            ->map(function ($row) use ($table, $toUserId) {
                $row = $this->sharedRow($table, (array) $row);
                unset($row['id']);
                $row['user_id'] = $toUserId;

                return $row;
            });

        foreach ($rows->chunk(200) as $chunk) {
            DB::table($table)->insert($chunk->values()->all());
        }
    }

    /**
     * Copies the Google Calendar app credentials and the connected Google account of company 1 from the old
     * portal. Skipped when this database already has a connected account, so a newer connection is never replaced.
     */
    private function copyGoogleCalendar(): void
    {
        $company = DB::table('companies')->where('id', 1)->first();
        $source = DB::table(self::SOURCE_DB . '.companies')->where('id', 1)->first();

        if (!$company || !$source || !empty($company->token) || empty($source->token)) {
            return;
        }

        DB::transaction(function () use ($source) {
            $global = DB::table(self::SOURCE_DB . '.global_settings')->first();

            if ($global) {
                DB::table('global_settings')->update([
                    'google_calendar_status' => $global->google_calendar_status,
                    'google_client_id' => $global->google_client_id,
                    'google_client_secret' => $global->google_client_secret,
                ]);
            }

            DB::table('companies')->where('id', 1)->update([
                'google_calendar_status' => $source->google_calendar_status,
                'google_calendar_verification_status' => $source->google_calendar_verification_status,
                'google_id' => $source->google_id,
                'name' => $source->name,
                'token' => $source->token,
            ]);
        });

        // Settings are cached per company
        cache()->flush();
    }

    private function sourceExists(): bool
    {
        return DB::table('information_schema.SCHEMATA')->where('SCHEMA_NAME', self::SOURCE_DB)->exists()
            && DB::getDatabaseName() !== self::SOURCE_DB;
    }

    private function sourceHasTable(string $table): bool
    {
        return DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', self::SOURCE_DB)
            ->where('TABLE_NAME', $table)
            ->exists();
    }

    /** Keeps only the columns this database's table also has (the two databases' tables can differ). */
    private function sharedRow(string $table, array $row): array
    {
        return array_intersect_key($row, array_flip(Schema::getColumnListing($table)));
    }

    public function down(): void
    {
        Schema::dropIfExists('microsoft_teams_settings');
        Schema::dropIfExists('crm_notes_tokens');
        Schema::dropIfExists('meeting_options');
        Schema::dropIfExists('customer_meetings');
        Schema::dropIfExists('customers');
    }

};
