<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Module split (hrm_v1_core + one hrm_v1_module_<name> branch per optional module).
 *
 * Every branch shares one database, so the menu rows, permissions and settings rows of
 * every module are always there. Whether a module's CODE is in this branch is decided
 * here, so core can leave out what is not present instead of linking to a missing page.
 *
 * A module is present when one of its routes is registered: in core while it still lives
 * in app/, or from its Modules/<Name> folder once it has been moved out and merged back.
 */
class ModulePresence
{
    /** Optional modules => a route that exists only when the module's code is present. */
    public const MODULES = [
        'leads' => 'lead-contact.index',
        'clients' => 'clients.index',
        'notes' => 'client-notes.index',
        'finance' => 'invoices.index',
        'tickets' => 'tickets.index',
        'events' => 'events.index',
        'messages' => 'messages.index',
        'notice-board' => 'notices.index',
        'knowledge-base' => 'knowledgebase.index',
        'reports' => 'task-report.index',
        'gdpr' => 'gdpr.index',
    ];

    /** Is this optional module's code in this branch? Unknown names count as present (core). */
    public static function has(string $module): bool
    {
        $route = self::MODULES[$module] ?? null;

        return $route === null || Route::has($route);
    }

    /** A route's URL, or '' when the route's module is not in this branch (the link then hides). */
    public static function href(string $name, ...$params): string
    {
        return Route::has($name) ? route($name, ...$params) : '';
    }

    /** A route's URL when it exists in this branch, else the fallback route's (the dashboard). */
    public static function url(string $name, ...$params): string
    {
        return Route::has($name) ? route($name, ...$params) : route('dashboard');
    }

    /**
     * May a menu entry be shown? A menu route is a route name, optionally followed by
     * "?query" or "|param" (e.g. "employees.show|{me}?tab=assets"); headings have none.
     * Plain URLs and headings are always allowed.
     */
    public static function menuAvailable(?string $menuRoute): bool
    {
        $menuRoute = trim((string) $menuRoute);

        if ($menuRoute === '' || str_contains($menuRoute, '/')) {
            return true;
        }

        $name = strtok($menuRoute, '?|');

        return $name === false || Route::has($name);
    }
}
