<?php

namespace AppModules\Gdpr;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Gdpr, split out of core (branch hrm_v1_module_gdpr).
 * Loaded by App\Providers\AppModulesServiceProvider when this folder is present.
 * Same tables, URLs, route names and view names as before the split.
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
            'App\\Models\\GdprSetting' => Models\GdprSetting::class,
            'App\\Models\\PurposeConsent' => Models\PurposeConsent::class,
            'App\\Models\\PurposeConsentLead' => Models\PurposeConsentLead::class,
            'App\\Models\\PurposeConsentUser' => Models\PurposeConsentUser::class,
        ]);

    }
}
