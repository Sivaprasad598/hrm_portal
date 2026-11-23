<?php

namespace AppModules\Finance;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Finance -> Invoices, for India (GST): a list, a form with the invoice drawn beside it, PDFs.
 * Restored from branch hrm_v1_module_finance in a smaller form: invoices only (no payments,
 * orders, estimates, subscriptions or online gateways). Same tables and route names as before.
 * Loaded by App\Providers\AppModulesServiceProvider when this folder is present.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        View::addLocation(__DIR__ . '/views');
    }
}
