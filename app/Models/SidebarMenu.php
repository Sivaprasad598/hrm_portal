<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One entry in the sidebar.
 *
 * The table is `sidebar_menus`, not `menus`: Worksuite's own `menus` table
 * (menu_name / translate_name / module / setting_menu) was created by the 2018
 * base migration and App\Models\Menu still maps to it.
 *
 * `permission_key`, `permission_rule` and `module` carry the condition this
 * item sits behind in the hand-written sidebar. They are read by
 * User::visibleMenus() so that granting a role a menu can never reveal a page
 * the person's permissions refuse — the grant narrows access, it never widens
 * it.
 */
class SidebarMenu extends Model
{

    protected $table = 'sidebar_menus';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'level' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Font Awesome 6 names are converted to the Font Awesome 5 this app loads.
     * Bootstrap Icons pass through untouched.
     */
    public function getIconAttribute($value)
    {
        return AppLauncher::normalizeIcon($value);
    }

    public function setIconAttribute($value)
    {
        $this->attributes['icon'] = AppLauncher::normalizeIcon($value);
    }

    public function getDisplayIconAttribute(): string
    {
        $icon = trim((string) $this->icon);

        if ($icon === '') {
            return 'fa fa-folder';
        }

        // Already carries its own family (Bootstrap Icons or any FA style).
        if (preg_match('/^(bi|fa|fas|far|fab|fal|fad|fat)\s/', $icon)) {
            return $icon;
        }

        if (str_starts_with($icon, 'bi-')) {
            return 'bi ' . $icon;
        }

        if (str_starts_with($icon, 'fa-')) {
            return 'fa ' . $icon;
        }

        return 'fa fa-' . $icon;
    }

    public function children()
    {
        return $this->hasMany(SidebarMenu::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Children that are switched on, ordered — used wherever the tree is
     * loaded for rendering rather than for management.
     */
    public function activeChildren()
    {
        return $this->children()->where('is_active', 1);
    }

    public function parent()
    {
        return $this->belongsTo(SidebarMenu::class, 'parent_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_menus', 'menu_id', 'role_id')->withTimestamps();
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where(function ($q) use ($companyId) {
            $q->where('company_id', $companyId)->orWhereNull('company_id');
        });
    }

    /**
     * The eager-load path for a full tree.
     *
     * Three levels, because the management screen allows three. The shipped
     * version loaded only `children.children`, so a level-3 item was fetched
     * one query at a time and without the is_active filter.
     */
    public static function treeRelations(): array
    {
        $active = fn ($q) => $q->where('is_active', 1)->orderBy('sort_order')->orderBy('id');

        return [
            'children' => $active,
            'children.children' => $active,
            'children.children.children' => $active,
        ];
    }

}
