<?php

namespace AppModules\ClientNotes;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Client notes ("Notes" menu), split out of core (branch hrm_v1_module_notes).
 * Loaded by App\Providers\AppModulesServiceProvider when this folder is present.
 * Needs the Clients module (notes belong to a client). Same tables, URLs, route names
 * and view names as before the split.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        View::addLocation(__DIR__ . '/views');

        // rows already stored under the old class names (morph types) keep resolving,
        // and new rows are written the same way in every branch
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'App\\Models\\ClientNote' => Models\ClientNote::class,
            'App\\Models\\ClientUserNote' => Models\ClientUserNote::class,
        ]);

        Models\ClientNote::observe(Observers\ClientNoteObserver::class);
        Models\ClientUserNote::observe(Observers\ClientUserNotesObserver::class);
    }
}
