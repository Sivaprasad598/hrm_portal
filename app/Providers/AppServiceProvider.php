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
        // Outgoing HTTPS (IFSC lookup, AI providers) needs a CA certificate
        // bundle. A PHP built without one configured (curl.cainfo /
        // openssl.cafile empty, as on this WAMP install) fails every call with
        // "unable to get local issuer certificate", so fall back to the bundle
        // composer/ca-bundle ships. A request that sets its own 'verify' (the
        // biometric client does) still decides for itself.
        if (!ini_get('curl.cainfo') && !ini_get('openssl.cafile') && class_exists(\Composer\CaBundle\CaBundle::class)) {
            \Illuminate\Support\Facades\Http::globalOptions([
                'verify' => \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath(),
            ]);
        }

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
