<?php

namespace App\Http\Middleware;

use App\Models\RecruitSetting;
use Closure;
use Illuminate\Http\Request;

/**
 * The website intake API (/api/recruit/v1): the request must carry the key
 * made on Recruitment -> Settings (header X-Api-Key, or "Authorization:
 * Bearer <key>"), and the API must be switched on. The settings row found is
 * handed on as the request attribute "recruit_setting" - it says which
 * company the submission belongs to.
 */
class RecruitApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        $setting = RecruitSetting::findByApiKey($request->header('X-Api-Key') ?: $request->bearerToken());

        if (!$setting) {
            return response()->json(['status' => 'error', 'message' => 'Invalid or missing API key, or the website API is switched off.'], 401);
        }

        $request->attributes->set('recruit_setting', $setting);

        return $next($request);
    }
}
