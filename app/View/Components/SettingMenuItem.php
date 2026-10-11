<?php

namespace App\View\Components;

use App\Models\SidebarMenu;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

/**
 * One row in the Settings page's own left-hand menu.
 *
 * The settings menu stays hand-written — every item keeps the permission check
 * it already had. What is new is that a "Settings" branch added in Menu
 * Management can hang extra items underneath, and that an item may now carry
 * children of its own.
 */
class SettingMenuItem extends Component
{

    public $href;

    public $text;

    public $active;

    public $menu;

    /** Active Settings sub-menus from the database, loaded once per request. */
    protected static ?Collection $settingsMenus = null;

    /** URLs already rendered as settings items in this request. */
    protected static array $renderedHrefs = [];

    /** Routes the hand-written settings sidebar knows about, read once. */
    protected static ?array $hardCodedRoutes = null;

    /** route name => menu id, for the entries seeded under Settings. */
    protected static ?array $settingsRouteIds = null;

    /** The route names this person's roles have been granted, or null if unseeded. */
    protected static ?array $grantedRoutes = null;

    /**
     * Entries that cannot be revoked, or an administrator could lock themselves
     * out of the screens that hand access back.
     */
    protected static array $alwaysShown = ['role_management', 'menu_management', 'profile_settings'];

    public function __construct($href, $text, $menu, $active = false)
    {
        $this->text = $text;
        $this->href = $href;
        $this->active = $active;
        $this->menu = $menu;

        static::$renderedHrefs[] = static::normalizeUrl($href);
    }

    public function render(): View|string
    {
        return view('components.setting-menu-item');
    }

    /**
     * Whether this entry survives the role's menu grants.
     *
     * The permission checks around each entry in setting-sidebar.blade.php
     * still decide who may see it; this only lets an administrator take an
     * entry away from a role in Settings > Assign Menus. An entry with no row
     * of its own is always rendered, so nothing is hidden by accident.
     */
    public function shouldRender(): bool
    {
        if ($this->menu !== null && in_array($this->menu, static::$alwaysShown, true)) {
            return true;
        }

        $granted = static::grantedSettingsRoutes();

        // Nothing seeded yet, or a route that is not a menu: show it.
        if ($granted === null) {
            return true;
        }

        $route = static::routeNameFor($this->href);

        if ($route === null || !array_key_exists($route, static::settingsRouteIds())) {
            return true;
        }

        return in_array($route, $granted, true);
    }

    public function isActive($option)
    {
        return $option === $this->active;
    }

    /**
     * Child menus added under this settings page in Menu Management.
     */
    public function children(): Collection
    {
        $dbMenu = static::settingsMenus()->first(
            fn ($item) => static::normalizeUrl(static::menuUrl($item)) === static::normalizeUrl($this->href)
        );

        return $dbMenu ? static::activeChildren($dbMenu) : collect();
    }

    /**
     * Settings sub-menus added in Menu Management that have no hand-written
     * entry. Rendered after every hand-written item, so the comparison against
     * $renderedHrefs is complete by then.
     */
    public static function extraSettingsMenus(): Collection
    {
        $hardCoded = static::hardCodedRoutes();

        return static::settingsMenus()->reject(function ($item) use ($hardCoded) {
            // Matched by route name first: an item the hand-written sidebar
            // owns but hides for this person must not reappear here.
            if ($item->route && in_array($item->route, $hardCoded, true)) {
                return true;
            }

            $url = static::menuUrl($item);

            return $url && in_array(static::normalizeUrl($url), static::$renderedHrefs, true);
        })->values();
    }

    public static function activeChildren($menu): Collection
    {
        return $menu->children
            ->filter(fn ($child) => (int) $child->is_active === 1)
            ->sortBy(fn ($child) => (int) ($child->sort_order ?? 0))
            ->values();
    }

    public static function menuUrl($menu): ?string
    {
        $route = $menu->route;

        if (!$route) {
            return null;
        }

        if (Route::has($route)) {
            return route($route);
        }

        if (filter_var($route, FILTER_VALIDATE_URL)) {
            return $route;
        }

        return url(preg_replace('#^/?public/#', '', ltrim($route, '/')));
    }

    /**
     * True when the current page is this menu or one of its descendants.
     */
    public static function isTreeActive($menu): bool
    {
        $url = static::menuUrl($menu);

        if ($url && static::normalizeUrl($url) === static::normalizeUrl(url()->current())) {
            return true;
        }

        foreach (static::activeChildren($menu) as $child) {
            if (static::isTreeActive($child)) {
                return true;
            }
        }

        return false;
    }

    protected static function settingsMenus(): Collection
    {
        if (static::$settingsMenus !== null) {
            return static::$settingsMenus;
        }

        $companyId = company()?->id;

        $settings = SidebarMenu::whereNull('parent_id')
            ->where('slug', 'settings')
            ->where('is_active', 1)
            ->when($companyId, fn ($q) => $q->forCompany($companyId))
            ->first();

        static::$settingsMenus = $settings
            ? SidebarMenu::with(['children' => fn ($q) => $q->where('is_active', 1)->orderBy('sort_order')->orderBy('id')])
                ->where('parent_id', $settings->id)
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
            : collect();

        return static::$settingsMenus;
    }

    /**
     * The route names named in the hand-written settings sidebar.
     *
     * Read from the Blade source because a permission-hidden item still "owns"
     * its route and must not be duplicated below by the database list. Read
     * once per request, not once per item.
     */
    protected static function hardCodedRoutes(): array
    {
        if (static::$hardCodedRoutes !== null) {
            return static::$hardCodedRoutes;
        }

        $path = resource_path('views/components/setting-sidebar.blade.php');
        static::$hardCodedRoutes = [];

        if (is_file($path)) {
            preg_match_all("/route\('([^']+)'/", file_get_contents($path), $matches);
            static::$hardCodedRoutes = $matches[1];
        }

        return static::$hardCodedRoutes;
    }

    /**
     * The route names seeded as children of the Settings menu, mapped to their
     * ids. Read once per request.
     */
    protected static function settingsRouteIds(): array
    {
        if (static::$settingsRouteIds !== null) {
            return static::$settingsRouteIds;
        }

        $settingsId = SidebarMenu::whereNull('parent_id')->where('slug', 'settings')->value('id');

        static::$settingsRouteIds = $settingsId
            ? SidebarMenu::where('parent_id', $settingsId)
                ->whereNotNull('route')
                ->where('is_active', 1)
                ->pluck('id', 'route')
                ->all()
            : [];

        return static::$settingsRouteIds;
    }

    /**
     * Which of those this person's roles hold, or null when nothing has been
     * seeded — in which case no filtering happens at all.
     */
    protected static function grantedSettingsRoutes(): ?array
    {
        if (static::$grantedRoutes !== null) {
            return static::$grantedRoutes;
        }

        $byRoute = static::settingsRouteIds();

        if ($byRoute === []) {
            return null;
        }

        $granted = DB::table('role_menus')
            ->join('role_user', 'role_user.role_id', '=', 'role_menus.role_id')
            ->where('role_user.user_id', user()?->id)
            ->whereIn('role_menus.menu_id', array_values($byRoute))
            ->pluck('role_menus.menu_id')
            ->unique()
            ->all();

        static::$grantedRoutes = array_keys(array_filter(
            $byRoute,
            fn ($id) => in_array($id, $granted)
        ));

        return static::$grantedRoutes;
    }

    /**
     * The route name an href belongs to, by matching it against the seeded
     * settings routes — the component is handed a URL, not a name.
     */
    protected static function routeNameFor(?string $href): ?string
    {
        $wanted = static::normalizeUrl($href);

        foreach (array_keys(static::settingsRouteIds()) as $route) {
            if (Route::has($route) && static::normalizeUrl(route($route)) === $wanted) {
                return $route;
            }
        }

        return null;
    }

    protected static function normalizeUrl(?string $url): string
    {
        return rtrim(strtok((string) $url, '?'), '/');
    }

}
