<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppLauncher extends Model
{
    protected $table   = 'app_launchers';
    protected $guarded = ['id'];
    protected $casts   = ['is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1)->orderBy('sort_order');
    }

    /**
     * The app loads Font Awesome 5, so map Font Awesome 6 style prefixes
     * ("fa-solid fa-flask") to their FA5 equivalents ("fas fa-flask").
     */
    public static function normalizeIcon(?string $icon): ?string
    {
        if (!$icon) {
            return $icon;
        }

        $map = [
            'fa-solid' => 'fas',
            'fa-regular' => 'far',
            'fa-brands' => 'fab',
            'fa-light' => 'fas',
            'fa-thin' => 'fas',
            'fa-duotone' => 'fas',
        ];

        $classes = preg_split('/\s+/', trim($icon));
        $classes = array_map(fn ($class) => $map[$class] ?? $class, $classes);

        // An "fa-xxx" icon name with no style class renders as an empty box
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
        if ($this->url) return $this->url;
        if ($this->route && \Route::has($this->route)) return route($this->route);
        return '#';
    }
}
