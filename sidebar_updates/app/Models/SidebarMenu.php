<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SidebarMenu extends Model
{
    /**
     * The table associated with the model.
     * We explicitly set this to 'menus' because the model name is different.
     */
    protected $table = 'menus';

    protected $guarded = ['id'];

    /**
     * Font Awesome 6 names ("fa-solid fa-vial") are converted to Font Awesome 5 ("fas fa-vial"),
     * the version this app loads.
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

        if (preg_match('/^(fa|fas|far|fab|fal|fad|fat)\s/', $icon)) {
            return $icon;
        }

        if (str_starts_with($icon, 'fa-')) {
            return 'fa ' . $icon;
        }

        return 'fa fa-' . $icon;
    }

    /**
     * Get the submenus (level 1) for the main menu (level 0).
     */
    public function children()
    {
        return $this->hasMany(SidebarMenu::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Get the parent menu.
     */
    public function parent()
    {
        return $this->belongsTo(SidebarMenu::class, 'parent_id');
    }

    /**
     * Get the roles that have access to this menu.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_menus', 'menu_id', 'role_id')->withTimestamps();
    }
}
