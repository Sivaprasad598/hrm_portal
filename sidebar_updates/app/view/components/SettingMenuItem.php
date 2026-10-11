<?php

namespace App\View\Components;

use App\Models\SidebarMenu;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

class SettingMenuItem extends Component
{

    public $href;
    public $text;
    public $active;
    public $menu;

    /**
     * Active "Settings" sub menus from the menus table (with their children), loaded once per request.
     */
    protected static ?Collection $settingsMenus = null;

    /**
     * URLs already rendered as settings menu items in this request.
     */
    protected static array $renderedHrefs = [];

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($href, $text, $menu, $active = false)
    {
        $this->text = $text;
        $this->href = $href;
        $this->active = $active;
        $this->menu = $menu;

        static::$renderedHrefs[] = static::normalizeUrl($href);
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return View|string
     */
    public function render()
    {
        return view('components.setting-menu-item');
    }

    /**
     * XXXXXXXXXXX
     *
     * @return \Illuminate\Http\Response
     */
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
     * Settings sub menus added in Menu Management that have no hard-coded entry in the settings sidebar.
     * Call this after all hard-coded items have been rendered.
     */
    public static function extraSettingsMenus(): Collection
    {
        // Routes the hard-coded sidebar knows about (shown or hidden by its own permission/module checks)
        preg_match_all("/route\\('([^']+)'/", file_get_contents(resource_path('views/components/setting-sidebar.blade.php')), $matches);
        $hardCodedRoutes = $matches[1];

        return static::settingsMenus()->reject(function ($item) use ($hardCodedRoutes) {
            if ($item->route && in_array($item->route, $hardCodedRoutes, true)) {
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
        if (static::$settingsMenus === null) {
            $settings = SidebarMenu::whereNull('parent_id')->where('slug', 'settings')->where('is_active', 1)->first();

            static::$settingsMenus = $settings
                ? SidebarMenu::with('children.children')
                    ->where('parent_id', $settings->id)
                    ->where('is_active', 1)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                : collect();
        }

        return static::$settingsMenus;
    }

    protected static function normalizeUrl(?string $url): string
    {
        return rtrim(strtok((string) $url, '?'), '/');
    }

}
