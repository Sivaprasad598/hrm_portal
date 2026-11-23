<?php

namespace App\Services\Access;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modules still being built (Settings -> Assign Menus -> Modules, sidebar_menus.coming_soon).
 *
 * A marked module keeps its menu (tagged "Soon"), but its pages answer with a Coming soon page
 * for everybody except the Super Admin, who goes on seeing the real pages to build them.
 *
 * Which pages belong to a module is read from its menu entries (the module's own entry and
 * everything under it):
 *  - the entry's route itself, e.g. invoices.index;
 *  - and the rest of that route's family, e.g. invoices.create / invoices.show, as long as no
 *    module that is open uses the same family (My Space links into employees.show, so marking
 *    My Space must not close the employee pages HR works in).
 * An entry that opens a shared page with a tab or a person ("employees.show|{me}?tab=kpi") is
 * left alone for the same reason.
 */
class ComingSoon
{

    private const CACHE = 'coming_soon_rules_';

    /** @var array<int, array>|null per company, for this request */
    private static array $rules = [];

    /** The module a route belongs to when that module is marked Coming soon, else null. */
    public static function moduleFor(?string $routeName): ?string
    {
        if (!$routeName || !user()) {
            return null;
        }

        $rules = self::rules();

        if (isset($rules['routes'][$routeName])) {
            return $rules['routes'][$routeName];
        }

        foreach ($rules['families'] as $family => $module) {
            if (str_starts_with($routeName, $family)) {
                return $module;
            }
        }

        return null;
    }

    /** Whether the signed-in person sees this route as Coming soon (the Super Admin never does). */
    public static function blocks(?string $routeName): ?string
    {
        $module = self::moduleFor($routeName);

        if ($module === null || in_array('admin', user_roles(), true)) {
            return null;
        }

        return $module;
    }

    /** The modules (top-level menus) and whether each is marked, for the settings page. */
    public static function modules(): \Illuminate\Support\Collection
    {
        return \App\Models\SidebarMenu::forCompany(company()?->id)
            ->whereNull('parent_id')
            ->where('is_active', 1)
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    public static function forget(): void
    {
        self::$rules = [];

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            cache()->forget(self::CACHE . $companyId);
        }
    }

    /** ['routes' => [route => module], 'families' => ['invoices.' => module]] for this company. */
    private static function rules(): array
    {
        $companyId = (int) company()?->id;

        return self::$rules[$companyId] ??= cache()->remember(self::CACHE . $companyId, now()->addDay(), function () use ($companyId) {
            $none = ['routes' => [], 'families' => []];

            if (!Schema::hasColumn('sidebar_menus', 'coming_soon')) {
                return $none;
            }

            $menus = \App\Models\SidebarMenu::forCompany($companyId ?: null)->where('is_active', 1)
                ->get(['id', 'parent_id', 'name', 'route', 'coming_soon'])->keyBy('id');

            if (!$menus->contains('coming_soon', true)) {
                return $none;
            }

            // the module (top-level entry) an entry sits in, when it or an entry above it is marked; else null
            $markedModule = function ($menu) use ($menus) {
                $marked = false;
                $m = $menu;

                for ($guard = 0; $m && $guard < 10; $guard++) {
                    $marked = $marked || $m->coming_soon;
                    if (!$m->parent_id || !$menus->has($m->parent_id)) {
                        break;
                    }
                    $m = $menus->get($m->parent_id);
                }

                return $marked && $m ? $m->name : null;
            };

            $split = function (?string $route): array {
                $route = trim((string) $route);
                $base = strtok($route, '|?') ?: '';

                return [$base, $base !== $route];
            };
            $family = fn (string $base) => str_contains($base, '.') ? substr($base, 0, strrpos($base, '.') + 1) : null;

            $openRoutes = [];
            $openFamilies = [];

            foreach ($menus as $menu) {
                [$base] = $split($menu->route);

                if ($base !== '' && $markedModule($menu) === null) {
                    $openRoutes[$base] = true;
                    if ($f = $family($base)) {
                        $openFamilies[$f] = true;
                    }
                }
            }

            $rules = $none;

            foreach ($menus as $menu) {
                [$base, $shared] = $split($menu->route);
                $module = $base !== '' ? $markedModule($menu) : null;

                if ($module === null || $shared || isset($openRoutes[$base])) {
                    continue;
                }

                $rules['routes'][$base] = $module;

                if (($f = $family($base)) && !isset($openFamilies[$f])) {
                    $rules['families'][$f] = $module;
                }
            }

            return $rules;
        });
    }

}
