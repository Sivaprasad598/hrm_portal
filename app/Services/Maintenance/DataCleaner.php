<?php

namespace App\Services\Maintenance;

use App\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

/**
 * Settings -> Data Clean: empty the application for a fresh test, table by
 * table (grouped for picking), keeping the Super Admin login and every setting.
 * Only what is picked is removed; removing employee accounts also removes the
 * rows that belong to them, and the preview lists those first.
 *
 * Always kept: Super Admin (users with the admin role), company and module
 * settings, payroll settings (Payslip Calculation), leave types and policy,
 * notice periods, the default shift (General Shift) and Day Off,
 * biometric devices and connection settings, the system roles (Super Admin,
 * Employee, Client) and their permissions.
 *
 * Links the database does not hold itself are put right as well (fixRelated):
 * a cleaned payslip reopens the encashment it paid, a removed person's asset is
 * available again, a hired candidate keeps its history.
 *
 * A full database backup is taken first; nothing is removed if it fails.
 */
class DataCleaner
{

    public const CONFIRM_PHRASE = 'DELETE ALL DATA';

    /** group => [label, description, tables emptied completely] */
    public const GROUPS = [
        'employees' => ['Employees', 'Employee accounts and the records kept about people: documents, emergency contacts, experience, passports and visas, rejoins, promotions, notifications, chats, sessions. Each can be picked on its own.', [
            // employee_details and employment_periods go per person below (the Super Admin keeps theirs)
            'employee_docs', 'employee_document_expiries', 'emergency_contacts', 'employee_relations', 'employee_skills',
            'employee_teams', 'employee_experiences', 'employee_previous_emp_details', 'emp_final_declaration', 'emp_final_declarations',
            'emp_joining_checklists', 'emp_passport_details', 'passport_details', 'visa_details', 'employee_rejoins', 'file_storage',
            'promotions', 'appreciations', 'employee_activity', 'user_activities', 'user_invitations', 'user_leadboard_settings',
            'user_taskboard_settings', 'socials', 'sticky_notes', 'push_subscriptions', 'manual_sessions', 'device_user', 'track_devices',
            'universal_search', 'notifications', 'users_chat', 'users_chat_files', 'conversation', 'conversation_reply', 'removal_requests',
            'probation_replies', 'personal_access_tokens',
        ]],
        'organisation' => ['Divisions, designations, branches', 'The import creates them again from the sheet.', [
            'teams', 'designations', 'branches',
        ]],
        'roles' => ['Custom roles', 'Roles other than Super Admin, Admin, Employee and Client.', []],
        'attendance' => ['Attendance', 'Attendance, daily reviews, regularisations with their approvals, day overrides, break logs, daily runs, timesheets.', [
            'attendances', 'attendance_reviews', 'attendance_regularisations', 'attendance_regularisation_approvals', 'attendance_break_logs',
            'attendance_day_overrides', 'attendance_daily_runs', 'weekly_timesheets', 'weekly_timesheet_entries',
        ]],
        'leave' => ['Leave', 'Leave applications with their files and approval trail, leave balances and their history, encashments, year-end runs. Leave types and policy stay.', [
            'leaves', 'leave_files', 'leave_approvals', 'employee_leave_quotas', 'employee_leave_quota_events', 'employee_leave_quota_histories',
            'employee_fixed_leave_balance', 'employee_monthly_balance', 'leave_encashments', 'leave_year_end_items', 'leave_year_end_runs',
        ]],
        'holidays' => ['Holidays', 'The holiday list (paid days off for everyone).', [
            'holidays',
        ]],
        'shifts' => ['Shifts & rosters', 'Custom shifts, rosters, assignments, rotations and change requests. General Shift and Day Off stay.', [
            'employee_shift_schedules', 'employee_shift_assignments', 'employee_shift_change_requests', 'employee_shift_rotations',
            'shift_rotation_sequences', 'automate_shifts', 'rotation_automate_log',
        ]],
        'exits' => ['Resignations, exits & settlements', 'Resignations and replies, exits with checklists, interviews and history, terminations, final settlements.', [
            'resignations', 'resignation_replies', 'employee_exits', 'employee_exit_checklists', 'employee_exit_histories',
            'employee_exit_interviews', 'terminations', 'settlements',
        ]],
        'payroll' => ['Payroll', 'Payslips and their lines, advances and loans, recurring pay rules and groups, salary revisions, tax declarations, OT bank, daily and past OT. Payroll settings, pay element types and tax slabs stay.', [
            'payslips', 'payslip_components', 'payroll_arrangements', 'pay_element_rules', 'pay_element_rule_applications',
            'pay_element_groups', 'pay_element_group_members', 'salary_revisions', 'salary_revision_logs', 'salary_revision_batches',
            'tax_declarations', 'payslip_mock_calculation', 'ot_ledger', 'ot_daily', 'ot_past',
        ]],
        'recruitment' => ['Recruitment', 'Candidates with their files, applications, interviews and feedback, offers, job positions with their rounds, the recruitment log and inbound applications. Employees hired from a candidate stay. Recruitment settings stay.', [
            'recruit_interview_feedback', 'recruit_interviews', 'job_offers', 'job_applications', 'candidate_files', 'recruit_logs',
            'recruit_inbound', 'candidates', 'job_positions',
        ]],
        'biometric' => ['Biometric data', 'Punches, device days, the device employee register and links, sync logs, backfills, exceptions. Devices and connection settings stay.', [
            'iapp_device_transactions', 'iapp_attendance_records', 'iapp_attendance_links', 'iapp_attendance_sync_runs', 'iapp_backfill_runs',
            'iapp_day_syncs', 'iapp_employees', 'iapp_sync_logs', 'biometric_exceptions',
        ]],
        'modules' => ['Projects, tasks, CRM & other modules', 'Projects, tasks, time logs, leads, deals, clients, contracts, estimates, invoices, payments, expenses, products, orders, notices, events, tickets, knowledge base. Their settings, categories and templates stay.', [
            'accept_estimates', 'bank_transactions', 'client_contacts', 'client_details', 'client_docs', 'client_notes', 'client_user_notes',
            'contract_discussions', 'contract_files', 'contract_renews', 'contract_signs', 'contracts', 'credit_note_item_images',
            'credit_note_items', 'credit_notes', 'deal_files', 'deal_histories', 'deal_notes', 'deals', 'discussion_files',
            'discussion_replies', 'discussions', 'estimate_item_images', 'estimate_items', 'estimate_requests', 'estimates',
            'event_attendees', 'event_files', 'events', 'expenses', 'expenses_recurring', 'gantt_links', 'invoice_files',
            'invoice_item_images', 'invoice_items', 'invoice_payment_details', 'invoice_recurring', 'invoice_recurring_item_images',
            'invoice_recurring_items', 'invoice_reminder_histories', 'invoices', 'issues', 'knowledge_base_files', 'knowledge_bases',
            'lead_agents', 'lead_follow_up', 'lead_notes', 'lead_products', 'lead_user_notes', 'leads', 'mention_users',
            'notice_board_users', 'notice_files', 'notice_views', 'notices', 'order_carts', 'order_item_images', 'order_items', 'orders',
            'payments', 'pinned', 'product_files', 'products', 'project_activity', 'project_departments', 'project_files',
            'project_labels', 'project_members', 'project_milestones', 'project_notes', 'project_ratings', 'project_time_log_breaks',
            'project_time_logs', 'project_user_notes', 'projects', 'proposal_item_images', 'proposal_items', 'proposal_signs', 'proposals',
            'purpose_consent_leads', 'purpose_consent_users', 'quotation_items', 'quotations', 'recurring_invoice_logs',
            'removal_requests_lead', 'sub_task_files', 'sub_tasks', 'task_blockers', 'task_comment_emoji', 'task_comments', 'task_files',
            'task_history', 'task_labels', 'task_notes', 'task_status_logs', 'task_users', 'tasks', 'ticket_activities', 'ticket_files',
            'ticket_replies', 'ticket_reply_users', 'ticket_tags', 'tickets', 'subscriptions', 'subscription_items',
        ]],
        'assets' => ['Assets', 'The asset register, every hand-over and return, services, repairs and renewals, and the asset history, photos and bills. Asset types stay.', [
            'asset_reminders', 'asset_services', 'asset_renewals', 'asset_histories', 'asset_assignments', 'assets',
        ]],
    ];

    /** Special items: not a table emptied completely. */
    public const SPECIAL = [
        '@users' => ['Employee accounts', 'Every user except the Super Admin. Every row that belongs to them in any table goes with them.'],
        '@roles' => ['Custom roles', 'Every role except Super Admin, Admin, Employee and Client, with its permissions and menu. People who had one keep the base Employee role.'],
        '@shifts' => ['Custom shifts', 'Every shift except General Shift and Day Off.'],
    ];

    /** How an item reads on the page (the table name shows beside it). */
    public const LABELS = [
        'attendances' => 'Attendance (clock in / out)', 'attendance_reviews' => 'Daily reviews', 'attendance_regularisations' => 'Attendance corrections',
        'attendance_regularisation_approvals' => 'Correction approval trail', 'attendance_break_logs' => 'Break logs',
        'attendance_day_overrides' => 'Day overrides', 'attendance_daily_runs' => 'Nightly run history', 'weekly_timesheets' => 'Weekly timesheets',
        'weekly_timesheet_entries' => 'Weekly timesheet entries',
        'leaves' => 'Leave applications', 'leave_files' => 'Leave attachments', 'leave_approvals' => 'Leave approval trail',
        'employee_leave_quotas' => 'Leave balances', 'employee_leave_quota_events' => 'Leave balance events', 'employee_leave_quota_histories' => 'Leave balance history',
        'employee_fixed_leave_balance' => 'Fixed leave balances', 'employee_monthly_balance' => 'Monthly leave balances', 'leave_encashments' => 'Leave encashments',
        'leave_year_end_items' => 'Leave year-end lines', 'leave_year_end_runs' => 'Leave year-end runs', 'holidays' => 'Holidays',
        'payslips' => 'Payslips', 'payslip_components' => 'Payslip extra lines (OT, bonus, recoveries)', 'payroll_arrangements' => 'Advances & loans',
        'pay_element_rules' => 'Recurring pay rules', 'pay_element_rule_applications' => 'Recurring rule history', 'pay_element_groups' => 'Pay element groups',
        'pay_element_group_members' => 'Pay element group members', 'salary_revisions' => 'Salary revisions', 'salary_revision_logs' => 'Salary revision trail',
        'salary_revision_batches' => 'Salary revision batches', 'tax_declarations' => 'Tax declarations', 'payslip_mock_calculation' => 'Calculator scratch rows',
        'ot_ledger' => 'OT bank used (LOP cover, encashed)', 'ot_daily' => 'Daily overtime', 'ot_past' => 'Past OT (imported)',
        'recruit_interview_feedback' => 'Interview feedback', 'recruit_interviews' => 'Interviews', 'job_offers' => 'Offers', 'job_applications' => 'Applications',
        'candidate_files' => 'Candidate files', 'recruit_logs' => 'Recruitment log', 'recruit_inbound' => 'Inbound applications (mail, careers page)',
        'candidates' => 'Candidates', 'job_positions' => 'Job positions (and their interview rounds)',
        'assets' => 'Asset register', 'asset_assignments' => 'Hand-overs & returns', 'asset_histories' => 'Asset history', 'asset_services' => 'Services & repairs',
        'asset_renewals' => 'Renewals', 'asset_reminders' => 'Asset reminders',
        'settlements' => 'Final settlements', 'resignations' => 'Resignations', 'employee_exits' => 'Exits', 'terminations' => 'Terminations',
        'teams' => 'Divisions', 'designations' => 'Designations', 'branches' => 'Branches',
    ];

    /** Upload folders emptied with a table (under public/user-uploads). */
    protected const FOLDERS = [
        '@users' => ['import-files', 'temp'],
        'employee_docs' => ['employee-docs'],
        'leave_files' => ['leave-files'],
        'project_files' => ['project-files'],
        'task_files' => ['task-files'],
        'event_files' => ['events'],
        'notice_files' => ['notice-files'],
        'product_files' => ['products'],
        'assets' => ['asset-images', 'asset-files'],
        'candidate_files' => ['candidate-files'],
    ];

    /** Shifts that are never removed. */
    protected const KEPT_SHIFTS = ['General Shift', 'Day Off'];

    /** Tables never touched when people are removed. */
    protected const NOT_PER_PERSON = ['users', 'ticket_settings_for_agents', 'lead_setting', 'candidates'];

    /**
     * The accounts kept: the Super Admins, and whoever is running the clean (an
     * Admin may run it too, and must not remove their own login mid-way).
     *
     * @return array<int, int>
     */
    public function keptUsers(): array
    {
        return DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.name', 'admin')->pluck('role_user.user_id')
            ->push(auth()->id())->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** What can be picked in a group: its special item (if any) then its tables that exist. */
    public function items(string $group): array
    {
        $special = ['employees' => ['@users'], 'roles' => ['@roles'], 'shifts' => ['@shifts']][$group] ?? [];

        return array_merge($special, array_values(array_filter(self::GROUPS[$group][2], fn ($t) => Schema::hasTable($t))));
    }

    /**
     * Exactly what was picked: tables and special items. A group key picks the
     * whole group. Nothing else is ever added.
     */
    public function resolve(array $picked): array
    {
        $all = [];

        foreach (array_keys(self::GROUPS) as $group) {
            foreach ($this->items($group) as $item) {
                if (in_array($item, $picked, true) || in_array($group, $picked, true)) {
                    $all[] = $item;
                }
            }
        }

        return array_values(array_unique($all));
    }

    /** Rows each item would remove now. @return array<string, int> */
    public function counts(): array
    {
        $keep = $this->keptUsers() ?: [0];
        $out = [];

        foreach (array_keys(self::GROUPS) as $group) {
            foreach ($this->items($group) as $item) {
                $out[$item] = match ($item) {
                    '@users' => DB::table('users')->whereNotIn('id', $keep)->count(),
                    '@roles' => DB::table('roles')->whereNotIn('name', ['admin', 'manager_admin', 'employee', 'client'])->count(),
                    '@shifts' => DB::table('employee_shifts')->whereNotIn('shift_name', self::KEPT_SHIFTS)->count(),
                    default => DB::table($item)->count(),
                };
            }
        }

        return $out;
    }

    /**
     * What a clean of these items would remove, without removing anything: the
     * removal itself is run inside a transaction and rolled back, so the figures
     * are exact - the picked tables, the rows of people removed, and every row
     * the database links take with them (a project takes its tasks, invoices
     * and payments; a designation its promotion history).
     *
     * @return array{items: array<string, int>, linked: array<string, int>, cleared: array<string, int>, exact: bool}
     */
    public function preview(array $picked): array
    {
        $items = $this->resolve($picked);
        $counts = array_intersect_key($this->counts(), array_flip($items));

        // A table that cannot roll back would really be emptied by the dry run.
        $notTransactional = DB::table('information_schema.TABLES')->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_TYPE', 'BASE TABLE')->where('ENGINE', '<>', 'InnoDB')->exists();

        if ($notTransactional) {
            return ['items' => $counts, 'linked' => [], 'cleared' => [], 'exact' => false];
        }

        DB::beginTransaction();

        try {
            $removed = $this->removeRows($items, $this->keptUsers() ?: [0], true);
        } finally {
            DB::rollBack();
        }

        $linked = $cleared = [];

        foreach ($removed as $key => $n) {
            if (str_ends_with($key, ' (link cleared)')) {
                $cleared[substr($key, 0, -15)] = $n;
            } elseif (!in_array($key, $items, true) && !in_array($key, ['users', 'roles', 'employee_shifts'], true)) {
                $linked[$key] = $n;
            }
        }

        arsort($linked);
        arsort($cleared);

        return ['items' => $counts, 'linked' => $linked, 'cleared' => $cleared, 'exact' => true];
    }

    /** Tables with a user_id column. */
    protected function perPersonTables(): array
    {
        return collect(DB::select("SELECT DISTINCT TABLE_NAME t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'user_id'"))
            ->pluck('t')->reject(fn ($t) => in_array($t, self::NOT_PER_PERSON, true))->values()->all();
    }

    /**
     * Take a full database backup. @return array{ok: bool, path?: string, message?: string}
     */
    public function backup(): array
    {
        $binary = $this->dumpBinary();

        if (!$binary) {
            return ['ok' => false, 'message' => 'mysqldump was not found on this server, so no backup can be taken. Set DB_DUMP_BINARY in .env to its full path.'];
        }

        $c = config('database.connections.' . config('database.default'));
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $path = $dir . DIRECTORY_SEPARATOR . 'before-data-clean-' . now()->format('Ymd_His') . '.sql';

        $process = new Process([$binary, '--host=' . $c['host'], '--port=' . $c['port'], '--user=' . $c['username'],
            '--single-transaction', '--routines', '--triggers', '--default-character-set=utf8mb4', '--result-file=' . $path, $c['database']],
            null, ['MYSQL_PWD' => (string) ($c['password'] ?? '')], null, 1800);
        $process->run();

        $tail = is_file($path) ? (string) file_get_contents($path, false, null, max(0, filesize($path) - 200)) : '';

        if (!$process->isSuccessful() || !str_contains($tail, 'Dump completed')) {
            return ['ok' => false, 'message' => 'The backup failed, so nothing was removed: ' . trim($process->getErrorOutput() ?: 'incomplete dump')];
        }

        return ['ok' => true, 'path' => $path];
    }

    protected function dumpBinary(): ?string
    {
        $candidates = array_filter([config('database.dump_binary')]);

        foreach (array_merge(glob('C:/wamp64/bin/mysql/*/bin/mysqldump.exe') ?: [], glob('C:/xampp/mysql/bin/mysqldump.exe') ?: [],
            ['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/usr/local/mysql/bin/mysqldump']) as $path) {
            $candidates[] = $path;
        }

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        $which = new Process([PHP_OS_FAMILY === 'Windows' ? 'where' : 'which', 'mysqldump']);
        $which->run();
        $found = trim(strtok($which->getOutput(), "\r\n") ?: '');

        return $found !== '' && is_file($found) ? $found : null;
    }

    /**
     * Remove exactly the picked items (tables, @users, @roles, @shifts; a group
     * key picks its whole group).
     *
     * @return array{removed: array<string, int>, items: array<int, string>, backup: string}
     */
    public function clean(array $picked, string $backupPath, int $byUserId): array
    {
        $items = $this->resolve($picked);
        $keep = $this->keptUsers() ?: [$byUserId];
        $removed = $this->removeRows($items, $keep, false);

        // The Super Admin starts clean too.
        if (in_array('@users', $items, true)) {
            foreach ($keep as $userId) {
                DB::table('employee_details')->where('user_id', $userId)->update(['employment_status' => 'confirmed', 'last_date' => null,
                    'notice_period_start_date' => null, 'notice_period_end_date' => null, 'iapp_emp_code' => null]);
                DB::table('users')->where('id', $userId)->update(['status' => 'active', 'login' => 'enable', 'inactive_date' => null]);
                app(\App\Services\Employment\EmploymentPeriods::class)->ensure($userId);
            }
        }

        $this->emptyFolders($items, $keep);

        try {
            Artisan::call('cache:clear');
        } catch (\Throwable) {
            // The data is clean either way.
        }

        Log::channel('single')->warning('Data Clean', ['by' => $byUserId, 'items' => $items, 'backup' => $backupPath, 'removed' => array_filter($removed)]);

        return ['removed' => array_filter($removed), 'items' => $items, 'backup' => $backupPath];
    }

    /**
     * The rows themselves: the picked tables and special items, then whatever
     * the database links take with them. $dry: inside a preview's transaction,
     * so nothing outside the database (role caches) is touched.
     *
     * @return array<string, int> table => rows removed; "table.column (link cleared)" => rows kept with the link emptied
     */
    protected function removeRows(array $items, array $keep, bool $dry): array
    {
        $removed = [];
        $has = fn ($item) => in_array($item, $items, true);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($items as $table) {
                if ($table[0] !== '@' && Schema::hasTable($table)) {
                    $removed[$table] = DB::table($table)->count();
                    DB::table($table)->delete();
                }
            }

            if ($has('@shifts')) {
                $removed['employee_shifts'] = DB::table('employee_shifts')->whereNotIn('shift_name', self::KEPT_SHIFTS)->delete();
            }

            if ($has('@roles')) {
                $custom = DB::table('roles')->whereNotIn('name', ['admin', 'manager_admin', 'employee', 'client'])->pluck('id');
                $affected = DB::table('role_user')->whereIn('role_id', $custom)->pluck('user_id')->unique();
                DB::table('permission_role')->whereIn('role_id', $custom)->delete();
                DB::table('role_menus')->whereIn('role_id', $custom)->delete();
                DB::table('role_user')->whereIn('role_id', $custom)->delete();
                Schema::hasColumn('designations', 'role_id') && DB::table('designations')->whereIn('role_id', $custom)->update(['role_id' => null]);
                $removed['roles'] = DB::table('roles')->whereIn('id', $custom)->delete();

                // Whoever had one of them works with the base Employee role's permissions.
                $employeeRole = $dry ? null : Role::where('name', 'employee')->first();
                foreach ($affected as $userId) {
                    if ($employeeRole && ($user = \App\Models\User::withoutGlobalScopes()->find($userId))) {
                        $user->assignUserRolePermission($employeeRole->id);
                    }
                }
            }

            // Holidays limited to departments / designations that are gone would
            // apply to nobody: they apply to everyone again.
            if ($has('teams')) {
                DB::table('employee_details')->update(['department_id' => null]);
                DB::table('holidays')->update(['department_id_json' => null]);
            }

            if ($has('designations')) {
                DB::table('employee_details')->update(['designation_id' => null]);
                DB::table('holidays')->update(['designation_id_json' => null]);
            }

            if ($has('branches')) {
                DB::table('employee_details')->update(['branch_id' => null]);
            }

            if ($has('iapp_employees') && !$has('@users')) {
                DB::table('employee_details')->update(['iapp_emp_code' => null, 'iapp_linked_at' => null]);
            }

            if ($has('@users')) {
                // Anything else that points at a person goes with them.
                foreach ($this->perPersonTables() as $table) {
                    $n = DB::table($table)->whereNotNull('user_id')->whereNotIn('user_id', $keep)->delete();

                    if ($n) {
                        $removed[$table] = ($removed[$table] ?? 0) + $n;
                    }
                }

                $removed['users'] = DB::table('users')->whereNotIn('id', $keep)->delete();
                DB::table('sessions')->whereNotNull('user_id')->whereNotIn('user_id', $keep)->delete();
            }
            $this->fixRelated($items, $has, $keep, $removed);

            // With the checks off the database cascades nothing, so rows kept in
            // settings tables can still point at rows just removed (a project
            // label at a deleted project). A dump of the database then fails on
            // import at "ADD CONSTRAINT" (#1452). Do what each foreign key would
            // have done: delete the row on CASCADE, clear the column on SET NULL.
            foreach ($this->sweepOrphans() as $key => $n) {
                $removed[$key] = ($removed[$key] ?? 0) + $n;
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        return $removed;
    }

    /**
     * What the picked items leave pointing at nothing, put right gently - the
     * links the database itself does not hold (no foreign key), and the states
     * that would otherwise stay wrong. $removed gains "table (put right)" lines.
     */
    protected function fixRelated(array $items, \Closure $has, array $keep, array &$removed): void
    {
        $note = function (string $table, int $n, string $what) use (&$removed) {
            if ($n) {
                $removed[$table . '.' . $what . ' (link cleared)'] = ($removed[$table . '.' . $what . ' (link cleared)'] ?? 0) + $n;
            }
        };
        $gone = fn (string $table) => !Schema::hasTable($table) || DB::table($table)->doesntExist();

        // Payslips gone: what they paid out is open again - leave encashments go
        // back to pending (to be paid on the next payslip), OT bank movements and
        // extra pay lines are released from them.
        if ($has('payslips')) {
            if (Schema::hasTable('leave_encashments') && !$has('leave_encashments')) {
                $note('leave_encashments', DB::table('leave_encashments')->whereNotNull('payslip_id')
                    ->update(['payslip_id' => null, 'status' => 'pending', 'paid_at' => null]), 'payslip_id');
            }
            if (Schema::hasTable('ot_ledger') && !$has('ot_ledger')) {
                $note('ot_ledger', DB::table('ot_ledger')->whereNotNull('payslip_id')->update(['payslip_id' => null]), 'payslip_id');
            }
            if (Schema::hasTable('payslip_components') && !$has('payslip_components')) {
                $note('payslip_components', DB::table('payslip_components')->whereNotNull('payslip_id')->update(['payslip_id' => null]), 'payslip_id');
            }
        }

        // Advances / loans gone: their instalment lines go too.
        if ($has('payroll_arrangements') && Schema::hasTable('payslip_components') && !$has('payslip_components')) {
            $n = DB::table('payslip_components')->whereNotNull('arrangement_id')->delete();
            $n && $removed['payslip_components'] = ($removed['payslip_components'] ?? 0) + $n;
        }

        // Recurring rules gone: the lines they produced and their history go too,
        // so nothing is paid from a rule that no longer exists.
        if ($has('pay_element_rules')) {
            if (Schema::hasTable('payslip_components') && !$has('payslip_components')) {
                $n = DB::table('payslip_components')->whereNotNull('rule_id')->whereNull('payslip_id')->delete();
                $n && $removed['payslip_components'] = ($removed['payslip_components'] ?? 0) + $n;
                $note('payslip_components', DB::table('payslip_components')->whereNotNull('rule_id')->update(['rule_id' => null]), 'rule_id');
            }
            if (Schema::hasTable('pay_element_rule_applications') && !$has('pay_element_rule_applications')) {
                $n = DB::table('pay_element_rule_applications')->delete();
                $n && $removed['pay_element_rule_applications'] = ($removed['pay_element_rule_applications'] ?? 0) + $n;
            }
        }
        if ($has('pay_element_groups') && Schema::hasTable('pay_element_group_members') && !$has('pay_element_group_members')) {
            $n = DB::table('pay_element_group_members')->delete();
            $n && $removed['pay_element_group_members'] = ($removed['pay_element_group_members'] ?? 0) + $n;
        }
        if ($has('salary_revision_batches') && Schema::hasTable('salary_revision_logs') && !$has('salary_revision_logs')) {
            $n = DB::table('salary_revision_logs')->delete();
            $n && $removed['salary_revision_logs'] = ($removed['salary_revision_logs'] ?? 0) + $n;
        }

        // Approval trails follow what they approved.
        foreach ([['leaves', 'leave_approvals', 'leave_id'], ['attendance_regularisations', 'attendance_regularisation_approvals', 'regularisation_id']] as [$parent, $trail, $column]) {
            if (($has($parent) || $has('@users')) && Schema::hasTable($trail) && !$has($trail) && Schema::hasTable($parent)) {
                $n = DB::table($trail)->whereNotIn($column, fn ($q) => $q->from($parent)->select('id'))->delete();
                $n && $removed[$trail] = ($removed[$trail] ?? 0) + $n;
            }
        }

        // Job positions gone: their own interview rounds go with them (the
        // general rounds, not tied to a position, stay as settings).
        if ($has('job_positions') && Schema::hasTable('recruit_rounds')) {
            $n = DB::table('recruit_rounds')->whereNotNull('job_position_id')->delete();
            $n && $removed['recruit_rounds'] = ($removed['recruit_rounds'] ?? 0) + $n;
        }

        // Candidates gone while the inbound mail stays: it no longer points at them.
        if (($has('candidates') || $has('job_applications')) && Schema::hasTable('recruit_inbound') && !$has('recruit_inbound')) {
            $note('recruit_inbound', DB::table('recruit_inbound')->where(fn ($q) => $q->whereNotNull('candidate_id')->orWhereNotNull('job_application_id'))
                ->update(['candidate_id' => null, 'job_application_id' => null]), 'candidate_id');
        }

        // People gone: a candidate they were hired from keeps its history, only
        // the link to the employee goes; an asset they held is available again.
        if ($has('@users')) {
            if (Schema::hasTable('candidates') && !$has('candidates')) {
                $note('candidates', DB::table('candidates')->whereNotNull('user_id')->whereNotIn('user_id', $keep)->update(['user_id' => null]), 'user_id');
            }
        }

        if (($has('@users') || $has('asset_assignments')) && Schema::hasTable('assets') && !$has('assets')) {
            $held = Schema::hasTable('asset_assignments')
                ? DB::table('asset_assignments')->whereNull('returned_on')
                    ->when($has('@users'), fn ($q) => $q->whereIn('user_id', $keep))->pluck('asset_id')->all()
                : [];
            $note('assets', DB::table('assets')->where('status', 'assigned')->whereNotIn('id', $held ?: [0])->update(['status' => 'available']), 'status');
        }
    }

    /**
     * Rows whose parent is gone, put right as their foreign key says.
     * Repeated until nothing changes: a delete can orphan rows further down.
     *
     * @return array<string, int> table => rows deleted; "table.column (link cleared)" => rows kept
     */
    public function sweepOrphans(): array
    {
        $fixed = [];
        $fks = DB::select("SELECT k.TABLE_NAME t, k.COLUMN_NAME c, k.REFERENCED_TABLE_NAME rt, k.REFERENCED_COLUMN_NAME rc, r.DELETE_RULE d, col.IS_NULLABLE n
            FROM information_schema.KEY_COLUMN_USAGE k
            JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
            JOIN information_schema.COLUMNS col ON col.TABLE_SCHEMA = k.TABLE_SCHEMA AND col.TABLE_NAME = k.TABLE_NAME AND col.COLUMN_NAME = k.COLUMN_NAME
            WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL");

        for ($pass = 0; $pass < 5; $pass++) {
            $changed = 0;

            foreach ($fks as $fk) {
                // The parent ids through a derived table: MySQL refuses a subquery
                // on the table being changed (#1093) when a table points at itself.
                $orphans = DB::table($fk->t)->whereNotNull($fk->c)
                    ->whereNotIn($fk->c, fn ($q) => $q->fromSub(DB::table($fk->rt)->select($fk->rc . ' as parent_id'), 'parents')->select('parent_id'));

                $clear = $fk->d === 'SET NULL' && $fk->n === 'YES';
                $n = $clear ? $orphans->update([$fk->c => null]) : $orphans->delete();

                if ($n) {
                    $key = $clear ? $fk->t . '.' . $fk->c . ' (link cleared)' : $fk->t;
                    $fixed[$key] = ($fixed[$key] ?? 0) + $n;
                    $changed += $n;
                }
            }

            if (!$changed) {
                break;
            }
        }

        return $fixed;
    }

    protected function emptyFolders(array $items, array $keep): void
    {
        $base = public_path('user-uploads');

        foreach ($items as $item) {
            foreach (self::FOLDERS[$item] ?? [] as $folder) {
                if (is_dir($base . '/' . $folder)) {
                    File::cleanDirectory($base . '/' . $folder);
                }
            }
        }

        // Photos of people who are gone; the Super Admin's stays.
        if (in_array('@users', $items, true) && is_dir($base . '/avatar')) {
            $kept = DB::table('users')->whereIn('id', $keep)->pluck('image')->filter()->all();

            foreach (File::files($base . '/avatar') as $file) {
                if (!in_array($file->getFilename(), $kept, true)) {
                    File::delete($file->getPathname());
                }
            }
        }
    }

}
