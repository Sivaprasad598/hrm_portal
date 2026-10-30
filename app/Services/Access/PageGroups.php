<?php

namespace App\Services\Access;

use App\Models\SidebarMenu;
use Illuminate\Support\Collection;

/**
 * Related pages shown as one sidebar entry, with tabs on each page to move
 * between them.
 *
 * Nothing changes in sidebar_menus or role_menus: every page keeps its own
 * row, permission and role assignment. A group only decides how the pages a
 * person may already open are drawn - the sidebar shows the first of them
 * under the group's name, and the page shows the rest as tabs.
 */
class PageGroups
{
    /** group => [sidebar label, [menu slug => tab label]], in tab order. */
    const GROUPS = [
        'people' => ['Employees', [
            'employees-index' => 'Employees',
            'departments-index' => 'Divisions',
            'designations-index' => 'Designations',
            'probation-requests-index' => 'Probation',
            'identity-index' => 'ID Cards',
            'payslip-entry-index' => 'Offer Letters',
        ]],
        'attendance' => ['Attendance', [
            'attendances-index' => 'Attendance',
            'shifts-index' => 'Shift Roster',
            'holidays-index' => 'Holidays',
        ]],
        'exits' => ['Exits', [
            'resignation-management-index' => 'Resignations',
            'employee-exit-index' => 'Exit',
            'employee-termination-index' => 'Termination',
            'resignations-index' => 'My Resignation',
        ]],
        // Payroll
        'monthly-payroll' => ['Monthly Payroll', [
            'payroll-run-index' => 'Run Payroll',
            'payslips-generated' => 'Issued Payslips',
            'payslips-index' => 'Payslip Calculator',
            'payroll-register-index' => 'Salary Statement',
        ]],
        'pay-elements' => ['Pay Elements & OT', [
            'pay-elements-index' => 'Pay Elements',
            'ot-hours' => 'OT Hours',
        ]],
        'tax' => ['Tax & Returns', [
            'income-tax-index' => 'Income Tax',
            'statutory-returns-index' => 'Statutory Returns',
        ]],
    ];

    /** A plainer sidebar name for a page that stands on its own. */
    const RENAMED = [
        'settlements-index' => 'Final Settlement',
        'payroll-dashboard-index' => 'Payroll Dashboard',
    ];

    public static function groupOf(?string $slug): ?string
    {
        foreach (self::GROUPS as $group => [, $pages]) {
            if (isset($pages[$slug])) {
                return $group;
            }
        }

        return null;
    }

    /**
     * A heading's children with each group drawn once: the first page of the
     * group the person can open stands in for all of them, under the group's name.
     */
    public static function collapse(Collection $children): Collection
    {
        $seen = [];

        return $children->filter(function ($menu) use (&$seen) {
            $group = self::groupOf($menu->slug);

            if ($group === null) {
                return true;
            }

            if (isset($seen[$group])) {
                return false;
            }

            $seen[$group] = true;
            $members = self::members($group);

            // A group the person can open only one page of is just that page.
            if (count($members) > 1) {
                $menu->setAttribute('group_label', self::GROUPS[$group][0]);
                $menu->setAttribute('group_routes', array_column($members, 'route'));
                // The entry opens the group's first tab (Employees, Run Payroll ...),
                // not whichever of its pages comes first in the old menu order.
                $menu->setAttribute('group_url', $members[0]['url']);
            }

            return true;
        })->values();
    }

    /** The group's pages this person may open: [['route', 'label', 'url'], ...]. */
    public static function members(string $group): array
    {
        $visible = self::visibleSlugs();
        $members = [];

        foreach (self::GROUPS[$group][1] as $slug => $label) {
            if (isset($visible[$slug]) && \Route::has($visible[$slug])) {
                $members[] = ['route' => $visible[$slug], 'label' => $label, 'url' => route($visible[$slug])];
            }
        }

        return $members;
    }

    /** Tabs for the page being viewed, or [] when it is not one of a group's pages. */
    public static function tabsFor(?string $routeName): array
    {
        if (!$routeName || !user()) {
            return [];
        }

        foreach (array_keys(self::GROUPS) as $group) {
            $members = self::members($group);

            if (count($members) > 1 && in_array($routeName, array_column($members, 'route'), true)) {
                return $members;
            }
        }

        return [];
    }

    /** Whether the page being viewed belongs to one of these routes (their index or the pages under it). */
    public static function routeIsWithin(array $routes): bool
    {
        $prefixes = array_map(fn ($route) => preg_replace('/\.index$/', '.*', $route), $routes);

        return request()->routeIs(...$prefixes);
    }

    /** slug => route for every page in this person's sidebar. */
    private static function visibleSlugs(): array
    {
        static $cache = [];
        $key = user()->id;

        if (!isset($cache[$key])) {
            $slugs = [];
            $walk = function ($menus) use (&$walk, &$slugs) {
                foreach ($menus as $menu) {
                    if ($menu->route && (int) $menu->is_active === 1) {
                        $slugs[$menu->slug] = $menu->route;
                    }

                    $walk($menu->children);
                }
            };

            $walk(SidebarMenu::forCompany(company()?->id)->where('is_active', 1)->exists() ? user()->visibleMenus() : []);
            $cache[$key] = $slugs;
        }

        return $cache[$key];
    }
}
