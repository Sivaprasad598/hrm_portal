<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Which sidebar entry is the page in view.
 *
 * Two kinds of entry can claim a page: one that names it exactly (a route, the
 * person and the tab: My Overtime is "employees.show|{me}?tab=ot-bank") and a
 * group entry that stands for several pages (Employees covers every employee's
 * profile, so also your own). Both lit up on My Overtime, and HR opened next to
 * My Space. When an entry names the page exactly, that entry is the page; the
 * group entries step back.
 */
class MenuMatch
{

    /** @var array<int, bool> per request */
    protected static array $exact = [];

    /** A route field ("name|param,{me}?key=value") as [name, params, query]. */
    public static function parse(?string $route): array
    {
        $route = (string) $route;
        $query = [];

        if (str_contains($route, '?')) {
            [$route, $qs] = explode('?', $route, 2);
            parse_str($qs, $query);
            $query = array_map(fn ($v) => str_replace('{me}', (string) user()->id, (string) $v), $query);
        }

        $params = [];

        if (str_contains($route, '|')) {
            [$route, $ps] = explode('|', $route, 2);
            $params = array_map(fn ($p) => str_replace('{me}', (string) user()->id, trim($p)), explode(',', $ps));
        }

        return [$route, $params, $query];
    }

    /** Whether an entry names the page in view exactly: its route, its person and its query. */
    public static function names(object $item): bool
    {
        if (!empty($item->group_routes) || !$item->route) {
            return false;
        }

        [$route, $params, $query] = self::parse($item->route);
        $current = request()->route();

        if (!$current || $route !== $current->getName() || ($params === [] && $query === [])) {
            return false;   // a bare route is not more exact than a group entry
        }

        $routeParams = array_values($current->parameters());

        foreach ($params as $i => $p) {
            if ((string) ($routeParams[$i] ?? '') !== (string) $p) {
                return false;
            }
        }

        foreach ($query as $k => $v) {
            if ((string) request($k) !== (string) $v) {
                return false;
            }
        }

        return true;
    }

    /** Whether any entry of this person's sidebar names the page in view exactly (worked out once a request). */
    public static function pageHasOwnEntry(?Collection $menus): bool
    {
        $key = spl_object_id(request());

        if (!array_key_exists($key, self::$exact)) {
            self::$exact[$key] = $menus !== null && self::search($menus);
        }

        return self::$exact[$key];
    }

    protected static function search(Collection $items): bool
    {
        foreach ($items as $item) {
            if ((int) ($item->is_active ?? 1) === 1 && self::names($item)) {
                return true;
            }

            if ($item->relationLoaded('children') && self::search($item->children)) {
                return true;
            }
        }

        return false;
    }

}
