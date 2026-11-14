<?php

namespace AppModules\Events;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Events, split out of core (branch hrm_v1_module_events).
 * Loaded by App\Providers\AppModulesServiceProvider when this folder is present.
 * Same tables, URLs, route names and view names as before the split.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        View::addLocation(__DIR__ . '/views');

    }
}
