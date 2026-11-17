<?php

namespace App\Support;

/**
 * Keeps the route cache honest.
 *
 * Registering the application's ~1,500 routes costs every request about 0.2 s;
 * `php artisan route:cache` (bootstrap/cache/routes-v7.php) removes that. But a
 * cache left over from before an update would serve the OLD routes: a new page
 * 404s, a removed one still answers. So on every request the cache is compared
 * with the files routes come from, and thrown away the moment one of them has
 * changed - the routes are then simply read from the files again (slower, never
 * wrong) until the cache is rebuilt (routes:keep-cached, run by the scheduler).
 */
class RouteCacheGuard
{

    /** The files routes are read from: the app's, each module's, and installed packages. */
    public static function sources(): array
    {
        $files = array_merge(
            glob(base_path('routes/*.php')) ?: [],
            glob(base_path('app-modules/*/ModuleServiceProvider.php')) ?: [],
            glob(base_path('app-modules/*/routes/*.php')) ?: [],
            glob(base_path('app-modules/*/routes.php')) ?: [],
            glob(app_path('Providers/*.php')) ?: [],
            [base_path('payslips.php'), base_path('vendor/composer/installed.json')]
        );

        sort($files);

        return array_values(array_filter($files, 'is_file'));
    }

    /** Changes whenever a source file is edited, added or removed. */
    public static function signature(): string
    {
        $parts = [];

        foreach (self::sources() as $file) {
            $parts[] = $file . '|' . filemtime($file) . '|' . filesize($file);
        }

        return md5(implode("\n", $parts));
    }

    protected static function signatureFile(): string
    {
        return base_path('bootstrap/cache/routes.sig');
    }

    /** Called while the application registers: a cache that no longer matches the files is removed. */
    public static function check(string $cachedRoutesPath): void
    {
        if (!is_file($cachedRoutesPath)) {
            return;
        }

        if (self::isAppRootInSubFolder()) {
            // This one request reads the route files, as without a cache (see the method).
            $_SERVER['APP_ROUTES_CACHE'] = $_ENV['APP_ROUTES_CACHE'] = 'bootstrap/cache/routes-not-used-for-this-request.php';

            return;
        }

        $known = @file_get_contents(self::signatureFile());

        if ($known === false || trim($known) !== self::signature()) {
            @unlink($cachedRoutesPath);
            @unlink(self::signatureFile());
        }
    }

    /**
     * The address of the application itself when it lives in a sub folder
     * (http://localhost/hrm_portal/ here; never on a domain of its own).
     *
     * With cached routes the framework drops the trailing slash before matching,
     * "/hrm_portal/" becomes "/hrm_portal", the sub folder is then taken for the
     * page asked for, nothing matches and the answer is "405 Method Not Allowed"
     * instead of the home route. Every other address is unaffected.
     */
    protected static function isAppRootInSubFolder(): bool
    {
        if (!isset($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME'])) {
            return false;   // a console command
        }

        $base = rtrim(str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME'])), '/');
        $path = (string) strtok((string) $_SERVER['REQUEST_URI'], '?');

        return $base !== '' && rtrim($path, '/') === $base;
    }

    /** After the cache has been (re)built: remember which files it was built from. */
    public static function remember(): void
    {
        @file_put_contents(self::signatureFile(), self::signature(), LOCK_EX);
    }

}
