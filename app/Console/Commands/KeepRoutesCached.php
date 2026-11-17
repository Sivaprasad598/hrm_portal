<?php

namespace App\Console\Commands;

use App\Support\RouteCacheGuard;
use Illuminate\Console\Command;

/**
 * Builds the route cache when there is none (first run, or after an update made
 * RouteCacheGuard discard the old one), and notes which files it was built from.
 * Run by the scheduler every few minutes; safe to run by hand at any time.
 */
class KeepRoutesCached extends Command
{

    protected $signature = 'routes:keep-cached {--force : Rebuild even if a cache is there}';

    protected $description = 'Build the route cache when it is missing (it is discarded automatically when routes change)';

    public function handle(): int
    {
        if (!$this->option('force') && $this->laravel->routesAreCached()) {
            $this->line('Route cache is in place.');

            return self::SUCCESS;
        }

        try {
            $this->callSilent('route:cache');
        } catch (\Throwable $e) {
            // A route that cannot be cached: leave the app on its route files (slower, still right).
            @unlink($this->laravel->getCachedRoutesPath());
            $this->error('Routes could not be cached: ' . $e->getMessage());

            return self::FAILURE;
        }

        RouteCacheGuard::remember();
        $this->info('Route cache rebuilt.');

        return self::SUCCESS;
    }

}
