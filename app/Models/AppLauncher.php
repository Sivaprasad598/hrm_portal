<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

/**
 * A tile in the topbar's app launcher.
 *
 * Appearance and order only. Whether a person may see a tile is still decided
 * by the permission map in sections/app-launcher.blade.php, keyed on `slug` —
 * nothing here widens access.
 */
class AppLauncher extends Model
{

    protected $table = 'app_launchers';

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Tiles for a company, newest schema first.
     *
     * company_id is matched loosely: rows seeded before a company existed carry
     * null and belong to everyone.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where(function ($q) use ($companyId) {
            $q->where('company_id', $companyId)->orWhereNull('company_id');
        });
    }

    /**
     * The app loads Font Awesome 5, so map Font Awesome 6 style prefixes
     * ("fa-solid fa-flask") to their FA5 equivalents ("fas fa-flask").
     *
     * Bootstrap Icons ("bi bi-house") are left alone — the layout loads those
     * too, and the sidebar's own defaults use them.
     */
    public static function normalizeIcon(?string $icon): ?string
    {
        if (!$icon) {
            return $icon;
        }

        $classes = preg_split('/\s+/', trim($icon));

        if (in_array('bi', $classes, true)) {
            return implode(' ', array_unique($classes));
        }

        $map = [
            'fa-solid' => 'fas',
            'fa-regular' => 'far',
            'fa-brands' => 'fab',
            'fa-light' => 'fas',
            'fa-thin' => 'fas',
            'fa-duotone' => 'fas',
        ];

        $classes = array_map(fn ($class) => $map[$class] ?? $class, $classes);

        // An "fa-xxx" icon name with no style class renders as an empty box.
        $hasStyle = (bool) array_intersect($classes, ['fa', 'fas', 'far', 'fab', 'fal', 'fad']);

        if (!$hasStyle && preg_grep('/^fa-/', $classes)) {
            array_unshift($classes, 'fas');
        }

        return implode(' ', array_unique($classes));
    }

    public function getIconAttribute($value)
    {
        return static::normalizeIcon($value);
    }

    public function setIconAttribute($value)
    {
        $this->attributes['icon'] = static::normalizeIcon($value);
    }

    public function getLinkAttribute()
    {
        if ($this->url) {
            return $this->url;
        }

        if ($this->route && Route::has($this->route)) {
            return route($this->route);
        }

        return '#';
    }

}
