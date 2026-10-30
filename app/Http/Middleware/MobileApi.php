<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * The mobile app API (/api/mobile/v1): answers are always JSON, errors included.
 *
 * It runs outermost (App\Http\Kernel::$middlewarePriority), so whatever fails
 * inside - the sign-in check, validation, not found, a crash - comes back here
 * already rendered by the REST package's handler, which owns /api/*. That
 * response carries the exception, and it is answered in the app's own shape
 * instead (App\Support\MobileApiErrors). Who may use the app: MobileApiEmployee.
 */
class MobileApi
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        $response = $next($request);

        if (($response->exception ?? null) !== null) {
            return \App\Support\MobileApiErrors::render($response->exception);
        }

        return $response;
    }
}
