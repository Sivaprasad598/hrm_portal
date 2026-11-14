<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Optional modules split out of core (one hrm_v1_module_<name> branch each).
 *
 * Each lives in app-modules/<Name>/ with a ModuleServiceProvider.php, under the
 * AppModules\<Name>\ namespace. Merging a module's branch adds its folder; this picks
 * it up with no other change - no composer run, no activation step. Without the folder
 * the module simply is not there (its menu entries hide: App\Support\ModulePresence).
 */
class AppModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $base = base_path('app-modules');

        if (!is_dir($base)) {
            return;
        }

        // AppModules\<Name>\Foo\Bar -> app-modules/<Name>/Foo/Bar.php
        spl_autoload_register(function ($class) use ($base) {
            if (!str_starts_with($class, 'AppModules\\')) {
                return;
            }

            $file = $base . '/' . str_replace('\\', '/', substr($class, strlen('AppModules\\'))) . '.php';

            if (is_file($file)) {
                require $file;
            }
        });

        foreach (glob($base . '/*/ModuleServiceProvider.php') ?: [] as $file) {
            $this->app->register('AppModules\\' . basename(dirname($file)) . '\\ModuleServiceProvider');
        }
    }
}
