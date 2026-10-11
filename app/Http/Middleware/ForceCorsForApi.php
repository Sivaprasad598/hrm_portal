<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceCorsForApi
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to API endpoints.
        if (! $request->is('api/*')) {
            return $next($request);
        }

        $origin = $request->headers->get('Origin');

        // Handle preflight (OPTIONS) requests.
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 204);

            if (! empty($origin)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Vary', 'Origin');
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            } else {
                $response->headers->set('Access-Control-Allow-Origin', '*');
            }

            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');

            $reqHeaders = $request->headers->get('Access-Control-Request-Headers');
            if (! empty($reqHeaders)) {
                $response->headers->set('Access-Control-Allow-Headers', $reqHeaders);
            } else {
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-TABLETRACK-KEY');
            }

            $response->headers->set('Access-Control-Max-Age', '86400');

            return $response;
        }

        $response = $next($request);

        if (! empty($origin)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Vary', 'Origin');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        } else {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');

        $reqHeaders = $request->headers->get('Access-Control-Request-Headers');
        if (! empty($reqHeaders)) {
            $response->headers->set('Access-Control-Allow-Headers', $reqHeaders);
        } else {
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-TABLETRACK-KEY');
        }

        return $response;
    }
}

