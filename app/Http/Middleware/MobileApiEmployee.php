<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * After the token is accepted: the person behind it must still be someone who
 * may use the app - an active employee whose login is enabled, not the Super
 * Admin system account. A token issued before they were deactivated stops
 * working, and is removed.
 */
class MobileApiEmployee
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !self::mayUseApp($user)) {
            $user?->currentAccessToken()?->delete();

            return response()->json(['success' => false, 'message' => 'This account cannot use the app.', 'errors' => (object) []], 403);
        }

        return $next($request);
    }

    /** Whether this account may sign in to the app. */
    public static function mayUseApp($user): bool
    {
        return $user->status === 'active'
            && $user->login === 'enable'
            && !\App\Services\Access\SystemAccounts::is((int) $user->id)
            && \App\Models\EmployeeDetails::where('user_id', $user->id)->exists();
    }
}
