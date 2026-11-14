<?php

namespace AppModules\Finance;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Finance, split out of core (branch hrm_v1_module_finance).
 * Loaded by App\Providers\AppModulesServiceProvider when this folder is present.
 * Same tables, URLs, route names and view names as before the split.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->commands([
            Console\Commands\AutoCreateRecurringInvoices::class,
            Console\Commands\AutoCreateRecurringExpenses::class,
            Console\Commands\SendInvoiceReminder::class,
        ]);
        $this->callAfterResolving(\Illuminate\Console\Scheduling\Schedule::class, function ($schedule) {
            $schedule->command('recurring-invoice-create')->dailyAt('01:30');
            $schedule->command('recurring-expenses-create')->dailyAt('01:40');
            $schedule->command('send-invoice-reminder')->hourly();
        });
        View::addLocation(__DIR__ . '/views');

    }
}
