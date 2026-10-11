<?php

namespace App\Providers;

use App\Models\Company;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Craftsys\Msg91\Client;
use Craftsys\Notifications\Channels\Msg91Channel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{

    /**
     * Register any application services.
     *
     * @return void
     */

    public function register()
    {
//        Cashier::ignoreMigrations();
//        Sanctum::ignoreMigrations();

        if (config('app.redirect_https')) {
            $this->app['request']->server->set('HTTPS', true);
        }
    }

    public function boot()
    {
        // Laravel paginates with its Tailwind view by default. This app loads
        // Bootstrap 4, so the Tailwind classes that size the prev/next arrows
        // do nothing and the inline SVG grows to fill whatever box it is in —
        // which is why the Device Attendance list was covered by a chevron the
        // height of the page. Bootstrap 4 markup instead, matching the rest of
        // the UI.
        Paginator::useBootstrapFour();

        Cashier::useCustomerModel(Company::class);

        if (config('app.redirect_https')) {
            URL::forceScheme('https');
        }

        Schema::defaultStringLength(191);

        DB::prohibitDestructiveCommands(app()->isProduction());

        Notification::extend('msg91', function ($app) {
            return new Msg91Channel($app->make(Client::class));
        });

        if (app()->environment('development')) {
            $this->app->register(IdeHelperServiceProvider::class);
        }

        CarbonInterval::macro('formatHuman', function ($totalMinutes, $seconds = false): string {

            if ($seconds) {
                return static::seconds($totalMinutes)->cascade()->forHumans(['short' => true, 'options' => 0]);
                /** @phpstan-ignore-line */
            }

            return static::minutes($totalMinutes)->cascade()->forHumans(['short' => true, 'options' => 0]);
            /** @phpstan-ignore-line */
        });

            //    Model::preventLazyLoading(app()->environment('development'));

    }

}
