<?php

namespace App\Services\Access;

/**
 * The modules a person has actually been given: the ones behind the pages in
 * their own sidebar (Assign Menus, then their permissions).
 *
 * Module Settings switches a module on for a whole kind of login - every
 * "employee"-type role at once, HR and plain staff alike - so a dashboard that
 * asked only that showed project, task, ticket and lead counts to people who
 * cannot open any of those pages. The dashboard asks here instead.
 */
class AssignedModules
{
    /** Roles that manage others and get the full sidebar; everyone else is self-service. */
    const MANAGING_ROLES = ['admin', 'hr', 'manager_admin', 'ceo', 'cfo', 'client'];

    /** Pages whose menu row names no module, and the module they stand for. */
    const PAGE_MODULES = [
        'lead-contact-index' => 'leads',
        'deals-index' => 'leads',
        'appreciations-index' => 'appreciation',
        'awards-index' => 'appreciation',
        'assets-index' => 'assets',
        'assets-assigned' => 'assets',
        'asset-types-index' => 'assets',
        'payslips-index' => 'payroll',
        'payroll-run-index' => 'payroll',
        'payslips-generated' => 'payroll',
        'payroll-register-index' => 'payroll',
        'payroll-dashboard-index' => 'payroll',
        'biometric-overview' => 'biometric',
        'notices-index' => 'notices',
        'events-index' => 'events',
        'tickets-index' => 'tickets',
        'messages-index' => 'messages',
    ];

    /** Modules that are not Worksuite modules, so Module Settings has no say in them. */
    const OWN_MODULES = ['appreciation', 'assets', 'payroll', 'biometric'];

    private static array $cache = [];

    /** Whether this person gets the self-service sidebar: Dashboard, Leaves, My Payslips, My Assets. */
    public static function selfService(): bool
    {
        return !array_intersect(self::MANAGING_ROLES, user_roles());
    }

    /** @return string[] */
    public static function forUser(): array
    {
        $user = user();

        if (!$user) {
            return [];
        }

        return self::$cache[$user->id] ??= self::build();
    }

    private static function build(): array
    {
        $enabled = user_modules();

        if (self::selfService()) {
            // Exactly the four links they have (sections/menu).
            $leave = user()->permission('view_leave');
            $assigned = array_filter([
                in_array('leaves', $enabled) && $leave !== 'none' && $leave !== false ? 'leaves' : null,
                'assets',
            ]);

            return array_values($assigned);
        }

        $assigned = [];
        $walk = function ($menus) use (&$walk, &$assigned) {
            foreach ($menus as $menu) {
                if ($menu->module) {
                    $assigned[$menu->module] = true;
                }

                if (isset(self::PAGE_MODULES[$menu->slug])) {
                    $assigned[self::PAGE_MODULES[$menu->slug]] = true;
                }

                $walk($menu->children);
            }
        };

        $walk(\App\Models\SidebarMenu::forCompany(company()?->id)->where('is_active', 1)->exists() ? user()->visibleMenus() : []);

        return array_values(array_filter(array_keys($assigned),
            fn ($module) => in_array($module, self::OWN_MODULES) || in_array($module, $enabled)));
    }
}
