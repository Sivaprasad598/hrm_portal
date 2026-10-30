<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Middleware\MobileApiEmployee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Sign in to the app with e-mail and password, and out again.
 *
 * A successful sign-in returns a bearer token for every later call. Five wrong
 * passwords for the same e-mail from the same address lock it for a minute.
 */
class AuthController extends MobileController
{
    const MAX_ATTEMPTS = 5;

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        $key = 'mobile-login:' . Str::lower($request->email) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return $this->fail('Too many attempts. Try again in ' . RateLimiter::availableIn($key) . ' seconds.', 429);
        }

        $user = User::withoutGlobalScopes()->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, 60);

            return $this->fail('The e-mail or password is not correct.', 401);
        }

        if (!MobileApiEmployee::mayUseApp($user)) {
            return $this->fail('This account cannot use the app. Please contact HR.', 403);
        }

        RateLimiter::clear($key);

        $token = $user->createToken(trim((string) $request->device_name) ?: 'mobile-app')->plainTextToken;
        auth()->setUser($user);

        return $this->ok([
            'token' => $token,
            'token_type' => 'Bearer',
            'employee' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee_id' => $user->employeeDetail?->employee_id,
                'photo_url' => $user->image_url,
            ],
        ], 'Logged in.');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->ok(null, 'Logged out.');
    }
}
