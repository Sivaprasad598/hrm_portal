<?php

namespace App\Services\Access;

use App\Models\SidebarMenu;
use Illuminate\Support\Facades\DB;

/**
 * What Settings -> Access shows: the sidebar's areas and pages, each page with
 * the actions that really belong to it, in plain words.
 *
 * A page row is a sidebar entry (sidebar_menus): switching it on puts the page
 * in the role's sidebar (role_menus). Its actions are permissions
 * (permission_role); a permission used by several pages - "view employees"
 * opens Employees, Probation and Exits - is the same switch wherever it shows.
 */
class AccessCatalog
{
    /** Areas, in sidebar order: top-level menu slug => label. Anything else is "Other modules". */
    const AREAS = [
        'dashboard' => 'Dashboard',
        'hr' => 'HR',
        'leave-management' => 'Leave',
        'payroll' => 'Payroll',
        'assets' => 'Assets',
        'biometric' => 'Biometric',
        'settings' => 'Settings',
    ];

    /** Page => [permission => what it lets them do]. */
    const ACTIONS = [
        'dashboard-advanced' => ['view_overview_dashboard' => 'Overview', 'view_hr_dashboard' => 'HR figures'],
        'employees-index' => ['view_employees' => 'See employees', 'add_employees' => 'Add', 'edit_employees' => 'Edit', 'delete_employees' => 'Delete',
            'view_documents' => 'See documents', 'add_documents' => 'Upload documents', 'manage_emergency_contact' => 'Emergency contacts'],
        'departments-index' => ['view_department' => 'See', 'add_department' => 'Add', 'edit_department' => 'Edit', 'delete_department' => 'Delete'],
        'designations-index' => ['view_designation' => 'See', 'add_designation' => 'Add', 'edit_designation' => 'Edit', 'delete_designation' => 'Delete'],
        'probation-requests-index' => ['view_employees' => 'See employees'],
        'identity-index' => ['edit_employees' => 'Edit employees'],
        'payslip-entry-index' => ['payroll_prepare' => 'Payroll: prepare'],
        'attendances-index' => ['view_attendance' => 'See', 'add_attendance' => 'Mark', 'edit_attendance' => 'Edit', 'delete_attendance' => 'Delete'],
        'shifts-index' => ['view_shift_roster' => 'See roster', 'manage_employee_shifts' => 'Manage shifts'],
        'holidays-index' => ['view_holiday' => 'See', 'add_holiday' => 'Add', 'edit_holiday' => 'Edit', 'delete_holiday' => 'Delete'],
        'resignation-management-index' => ['view_employees' => 'See employees'],
        'employee-exit-index' => ['view_employees' => 'See employees'],
        'employee-termination-index' => ['view_employees' => 'See employees'],
        'salary-revisions' => ['payroll_settings' => 'Prepare revisions (payroll settings)'],
        'leaves-index' => ['view_leave' => 'See', 'add_leave' => 'Apply', 'edit_leave' => 'Edit', 'delete_leave' => 'Delete',
            'approve_or_reject_leaves' => 'Approve / reject', 'add_leave_for_others' => 'Apply for others'],
        'leave-management-index' => ['view_leave' => 'See leave'],
        'leave-year-end-index' => ['manage_leave_setting' => 'Close the leave year'],
        'payroll-run-index' => ['payroll_prepare' => 'Prepare: run payroll, generate payslips'],
        'payslips-generated' => ['payroll_approve' => 'Approve, mark paid, send', 'payroll_revoke' => 'Revoke, delete'],
        'payslips-index' => ['payroll_prepare' => 'Prepare: calculator, one payslip'],
        'pay-elements-index' => ['payroll_settings' => 'Settings: pay elements'],
        'ot-hours' => ['payroll_settings' => 'Settings: OT hours'],
        'income-tax-index' => ['payroll_settings' => 'Settings: income tax'],
        'statutory-returns-index' => ['payroll_settings' => 'Settings: statutory returns'],
        'settlements-index' => ['payroll_settings' => 'Settings: settlements'],
        'assets-index' => ['manage_assets' => 'Manage assets (add, edit, assign, return, delete)'],
        'assets-assigned' => ['manage_assets' => 'Manage assets'],
        'asset-types-index' => ['manage_assets' => 'Manage assets'],
        'biometric-overview' => ['view_biometric' => 'See biometric', 'manage_biometric_exceptions' => 'Resolve exceptions',
            'manage_biometric_mapping' => 'Map employees', 'manage_biometric_system' => 'Technical setup'],
        'attendance-settings-index' => ['manage_attendance_setting' => 'Change'],
        'employee-shifts-index' => ['manage_attendance_setting' => 'Change'],
        'leaves-settings-index' => ['manage_leave_setting' => 'Change'],
        'company-settings-index' => ['manage_company_setting' => 'Change'],
        'role-permissions-index' => ['manage_role_permission_setting' => 'Change access (this page)'],
        'assign-menus-index' => ['manage_role_permission_setting' => 'Change access (this page)'],
    ];

    /** The words for a permission level (permission_types). */
    const LEVELS = [
        'all' => 'Everyone',
        'both' => 'Own + added by them',
        'added' => 'Added by them',
        'owned' => 'Only their own',
        'none' => 'No',
    ];

    /**
     * The areas with their pages and actions, for one role.
     *
     * @return array<int, array{label: string, other: bool, pages: array}>
     */
    public static function forRole(int $roleId): array
    {
        $menus = SidebarMenu::with('children.children')->forCompany(company()?->id)
            ->whereNull('parent_id')->where('is_active', 1)->orderBy('sort_order')->get();
        $assigned = array_flip(DB::table('role_menus')->where('role_id', $roleId)->pluck('menu_id')->all());
        $levels = DB::table('permission_role as pr')->join('permission_types as t', 't.id', '=', 'pr.permission_type_id')
            ->where('pr.role_id', $roleId)->pluck('t.name', 'pr.permission_id')->all();
        $permissions = DB::table('permissions')->get(['id', 'name', 'display_name', 'allowed_permissions'])->keyBy('name');
        $typeIds = DB::table('permission_types')->pluck('id', 'name')->all();

        $areas = [];

        foreach ($menus as $top) {
            $pages = $top->children->where('is_active', 1)->sortBy('sort_order')->values();

            if ($top->slug === 'settings') {
                // Only the settings pages this portal uses; the rest stay under "Other".
                $pages = $pages->filter(fn ($p) => isset(self::ACTIONS[$p->slug])
                    || in_array($p->slug, ['profile-settings-index', 'payslip-settings-index', 'notice-period-settings-index', 'menu-management-index'], true))->values();
            }

            if ($pages->isEmpty()) {
                $pages = collect([$top]);   // a top-level page of its own (Tickets, Events ...)
            }

            $rows = $pages->map(function ($page) use ($assigned, $levels, $permissions, $typeIds) {
                $actions = self::ACTIONS[$page->slug] ?? self::fallbackActions($page);

                return [
                    'id' => $page->id,
                    'slug' => $page->slug,
                    'name' => PageGroups::GROUPS[PageGroups::groupOf($page->slug) ?? '_'][1][$page->slug] ?? PageGroups::RENAMED[$page->slug] ?? $page->name,
                    'on' => isset($assigned[$page->id]),
                    'actions' => collect($actions)->map(function ($label, $name) use ($permissions, $levels, $typeIds) {
                        $permission = $permissions[$name] ?? null;

                        if (!$permission) {
                            return null;
                        }

                        $allowed = json_decode((string) $permission->allowed_permissions, true) ?: ['all' => 4, 'none' => 5];
                        $options = [];

                        foreach (self::LEVELS as $key => $words) {
                            if (isset($allowed[$key])) {
                                $options[$typeIds[$key] ?? $allowed[$key]] = $words;
                            }
                        }

                        return [
                            'id' => $permission->id, 'name' => $name, 'label' => $label,
                            'level' => $levels[$permission->id] ?? 'none',
                            'level_id' => $typeIds[$levels[$permission->id] ?? 'none'] ?? 5,
                            'options' => $options, 'switch' => count($options) === 2,
                        ];
                    })->filter()->values()->all(),
                ];
            })->all();

            $label = self::AREAS[$top->slug] ?? $top->name;
            $other = !isset(self::AREAS[$top->slug]);
            $areas[] = ['label' => $label, 'other' => $other, 'pages' => $rows];
        }

        // HRM areas first, in their order; the rest after.
        $order = array_flip(array_values(self::AREAS));
        usort($areas, fn ($a, $b) => [$a['other'], $order[$a['label']] ?? 99] <=> [$b['other'], $order[$b['label']] ?? 99]);

        return $areas;
    }

    /** A page not in ACTIONS: the one permission that opens it, if it has one. */
    protected static function fallbackActions($page): array
    {
        $key = trim((string) $page->permission_key);

        return $key !== '' && !str_contains($key, ',') ? [$key => 'Open'] : [];
    }

    public static function levelWords(?string $level): string
    {
        return self::LEVELS[$level] ?? (string) $level;
    }
}
