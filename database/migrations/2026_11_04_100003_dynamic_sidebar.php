<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The whole sidebar from Settings: every role reads Assign Menus (there was a
 * fixed sidebar for employees, team leads and managers that ignored it).
 *
 *  - My Space (own leave, attendance, payslips, assets, OT bank) and My Team
 *    (team leave, team attendance, attendance requests) become menu entries.
 *  - Pages that had no entry get one, assigned to the Super Admin to enable later.
 *  - Which pages show together as tabs (and the tab text) is kept on the entries
 *    (tab_group, tab_label), editable in Settings > Add Menu, not in code.
 *  - Roles keep what they saw: employees and team leads get My Space, Tasks and
 *    (with a team) My Team; HR / Admin / CEO get My Space and My Team on top.
 *  - Attendance Dashboard (everyone's attendance) leaves the staff roles.
 */
return new class extends Migration
{
    /** group => [sidebar label, [slug => tab label]] - was PageGroups::GROUPS */
    private const TAB_GROUPS = [
        ['Employees', ['employees-index' => 'Employees', 'departments-index' => 'Divisions', 'designations-index' => 'Designations',
            'probation-requests-index' => 'Probation', 'identity-index' => 'ID Cards', 'payslip-entry-index' => 'Offer Letters']],
        ['Attendance Register', ['attendances-index' => 'Attendance Register', 'shifts-index' => 'Shift Roster', 'holidays-index' => 'Holidays']],
        ['Exits', ['resignation-management-index' => 'Resignations', 'employee-exit-index' => 'Exit',
            'employee-termination-index' => 'Termination', 'resignations-index' => 'My Resignation']],
        ['Monthly Payroll', ['payroll-run-index' => 'Run Payroll', 'payslips-generated' => 'Issued Payslips',
            'payslips-index' => 'Payslip Calculator', 'payroll-register-index' => 'Salary Statement']],
        ['Pay Elements & OT', ['pay-elements-index' => 'Pay Elements', 'ot-hours' => 'OT Hours']],
        ['Tax & Returns', ['income-tax-index' => 'Income Tax', 'statutory-returns-index' => 'Statutory Returns']],
    ];

    private const STAFF = ['employee', 'team_lead', 'manager', 'gis_engineer', 'developer'];
    private const MANAGING = ['admin', 'hr', 'manager_admin', 'ceo', 'cfo'];

    public function up(): void
    {
        Schema::table('sidebar_menus', function (Blueprint $table) {
            if (!Schema::hasColumn('sidebar_menus', 'tab_group')) {
                $table->string('tab_group', 100)->nullable()->after('module');
                $table->string('tab_label', 100)->nullable()->after('tab_group');
            }
        });

        foreach (DB::table('sidebar_menus')->whereNull('parent_id')->where('slug', 'dashboard')->pluck('company_id') as $companyId) {
            $this->forCompany((int) $companyId);
        }

        \App\Models\User::forgetAllMenuCaches();
    }

    private function forCompany(int $companyId): void
    {
        $menus = fn () => DB::table('sidebar_menus')->where('company_id', $companyId);
        $id = fn (string $slug) => $menus()->where('slug', $slug)->value('id');

        // ---- tabs, now data
        foreach (self::TAB_GROUPS as [$group, $pages]) {
            foreach ($pages as $slug => $label) {
                $menus()->where('slug', $slug)->update(['tab_group' => $group, 'tab_label' => $label]);
            }
        }
        // Tabs follow menu order: the group's own positions, handed out in tab order
        // (the first tab is what the sidebar entry opens - Employees, not Divisions)
        foreach (self::TAB_GROUPS as [, $pages]) {
            $rows = $menus()->whereIn('slug', array_keys($pages))->get(['slug', 'sort_order']);
            $sorts = $rows->pluck('sort_order')->sort()->values()->all();
            foreach (array_values(array_filter(array_keys($pages), fn ($s) => $rows->firstWhere('slug', $s))) as $i => $slug) {
                $menus()->where('slug', $slug)->update(['sort_order' => $sorts[$i]]);
            }
        }

        // plainer names for pages that stand alone (was PageGroups::RENAMED)
        $menus()->where('slug', 'settlements-index')->update(['name' => 'Final Settlement']);
        $menus()->where('slug', 'payroll-dashboard-index')->update(['name' => 'Payroll Dashboard']);

        // ---- helpers
        $add = function (string $slug, string $name, ?int $parent, int $sort, array $extra = []) use ($menus, $companyId, $id) {
            if ($existing = $id($slug)) {
                return $existing;
            }

            return $menus()->insertGetId(array_merge([
                'company_id' => $companyId, 'parent_id' => $parent, 'name' => $name, 'slug' => $slug, 'route' => null,
                'icon' => null, 'level' => $parent ? 1 : 0, 'sort_order' => $sort, 'is_active' => 1,
                'permission_key' => null, 'permission_rule' => null, 'role_rule' => null, 'module' => null,
                'created_at' => now(), 'updated_at' => now(),
            ], $extra));
        };
        $roleIds = fn (array $names) => DB::table('roles')->where('company_id', $companyId)->whereIn('name', $names)->pluck('id')->all();
        $grant = function (array $roles, array $menuIds) {
            foreach ($roles as $roleId) {
                foreach (array_filter($menuIds) as $menuId) {
                    DB::table('role_menus')->updateOrInsert(['role_id' => $roleId, 'menu_id' => $menuId], ['updated_at' => now(), 'created_at' => now()]);
                }
            }
        };

        // ---- My Space and My Team, right under Dashboard
        $dashSort = (int) $menus()->where('slug', 'dashboard')->value('sort_order');
        if (!$id('my-space')) {
            $menus()->whereNull('parent_id')->where('sort_order', '>', $dashSort)->increment('sort_order', 2);
        }
        $space = $add('my-space', 'My Space', null, $dashSort + 1, ['icon' => 'bi bi-person-circle']);
        $team = $add('my-team', 'My Team', null, $dashSort + 2, ['icon' => 'bi bi-people']);

        $spaceItems = [
            $add('my-leaves', 'My Leaves', $space, 1, ['route' => 'leaves.personal', 'module' => 'leaves', 'permission_key' => 'view_leave', 'permission_rule' => 'any']),
            $add('my-attendance', 'My Attendance', $space, 2, ['route' => 'attendances.by_member?user_id={me}', 'module' => 'attendance', 'permission_key' => 'view_attendance', 'permission_rule' => 'any']),
            $id('my-payslips-index'),
            $add('my-assets', 'My Assets', $space, 4, ['route' => 'employees.show|{me}?tab=assets', 'role_rule' => 'has_employee_record']),
            $add('my-ot-bank', 'My OT Bank', $space, 5, ['route' => 'employees.show|{me}?tab=ot-bank', 'role_rule' => 'ot_bank']),
        ];
        // My Payslips moves from Payroll into My Space
        $menus()->where('slug', 'my-payslips-index')->update(['parent_id' => $space, 'level' => 1, 'sort_order' => 3, 'name' => 'My Payslips']);

        $teamItems = [
            $add('team-leaves', 'Team Leaves', $team, 1, ['route' => 'leaves.index', 'module' => 'leaves', 'role_rule' => 'has_team']),
            $add('team-attendance', 'Team Attendance', $team, 2, ['route' => 'my-team.attendance', 'module' => 'attendance', 'role_rule' => 'has_team']),
            $add('attendance-requests', 'Attendance Requests', $team, 3, ['route' => 'attendances.by_request', 'module' => 'attendance',
                'permission_key' => 'view_attendance', 'permission_rule' => 'any', 'role_rule' => 'has_team']),
        ];

        // ---- pages that had no entry: the Super Admin's, to enable for roles later
        $parent = fn (string $slug) => $id($slug);
        $next = fn (?int $p) => (int) $menus()->where('parent_id', $p)->max('sort_order') + 1;
        $orphans = [
            ['access-settings', 'Page & Action Access', 'settings', 'access-settings.index', null],
            ['ai-settings', 'AI Settings', 'settings', 'ai-settings.index', null],
            ['data-clean', 'Data Clean', 'settings', 'data-clean.index', null],
            ['smtp-settings', 'Mail (SMTP) Settings', 'settings', 'smtp-settings.index', null],
            ['employee-import', 'Employee Import', 'hr', 'employee-import.index', 'employees'],
            ['leave-summary-report', 'Leave Summary Report', 'reports', 'leave-report.index', 'leaves'],
            ['weekly-timesheets', 'Weekly Timesheets', 'work', 'weekly-timesheets.index', 'timelogs'],
            ['taskboards', 'Task Board', 'work', 'taskboards.index', 'tasks'],
            ['products-index', 'Products', 'finance', 'products.index', 'products'],
            ['orders-index', 'Orders', 'finance', 'orders.index', 'orders'],
            ['knowledgebase-index', 'Knowledge Base', null, 'knowledgebase.index', 'knowledgebase'],
        ];
        $orphanIds = [];
        foreach ($orphans as [$slug, $name, $under, $route, $module]) {
            if (!\Illuminate\Support\Facades\Route::has($route)) {
                continue;
            }
            $p = $under ? $parent($under) : null;
            if ($under && !$p) {
                continue;
            }
            $orphanIds[] = $add($slug, $name, $p, $next($p), ['route' => $route, 'module' => $module, 'icon' => $p ? null : 'bi bi-book']);
        }

        // ---- who gets what
        $staff = $roleIds(self::STAFF);
        $managing = $roleIds(self::MANAGING);
        $grant($staff, array_merge([$id('dashboard'), $id('dashboard-1'), $space, $team, $id('work'), $id('tasks-index')], $spaceItems, $teamItems));
        $grant($managing, array_merge([$space, $team], $spaceItems, $teamItems));
        $grant($roleIds(['admin']), $orphanIds);

        // Staff no longer see the company-wide attendance pages or the leave list as such
        // (their own leave is My Leaves; a team's is Team Leaves)
        DB::table('role_menus')->whereIn('role_id', $staff)
            ->whereIn('menu_id', array_filter([$id('attendances-index'), $id('attendance-dashboard'), $id('leaves-index')]))->delete();
    }

    public function down(): void
    {
        $slugs = ['my-space', 'my-team', 'my-leaves', 'my-attendance', 'my-assets', 'my-ot-bank', 'team-leaves', 'team-attendance',
            'attendance-requests', 'access-settings', 'ai-settings', 'data-clean', 'smtp-settings', 'employee-import', 'promotions-index',
            'leave-summary-report', 'weekly-timesheets', 'taskboards', 'products-index', 'orders-index', 'knowledgebase-index'];
        $payroll = DB::table('sidebar_menus')->where('slug', 'payroll')->value('id');
        DB::table('sidebar_menus')->where('slug', 'my-payslips-index')->update(['parent_id' => $payroll]);
        $ids = DB::table('sidebar_menus')->whereIn('slug', $slugs)->pluck('id');
        DB::table('role_menus')->whereIn('menu_id', $ids)->delete();
        DB::table('sidebar_menus')->whereIn('id', $ids)->delete();
        Schema::table('sidebar_menus', fn (Blueprint $t) => $t->dropColumn(['tab_group', 'tab_label']));
    }
};
