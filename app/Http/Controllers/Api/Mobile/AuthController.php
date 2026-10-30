<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Middleware\MobileApiEmployee;
use App\Models\User;
use App\Notifications\MobilePasswordResetCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Fortify\Rules\Password;

/**
 * Sign in to the app with e-mail and password, and out again; a forgotten
 * password, and changing it.
 *
 * A successful sign-in returns a bearer token for every later call. Five wrong
 * passwords for the same e-mail from the same address lock it for a minute.
 *
 * Forgot password works with a six-digit code sent by e-mail, typed into the
 * app with the new password - a link would open the website, not the app. The
 * code lasts CODE_MINUTES, allows CODE_ATTEMPTS tries and works once. A new
 * password, by either route, signs the account out of every other device.
 */
class AuthController extends MobileController
{
    const MAX_ATTEMPTS = 5;

    const CODE_MINUTES = 15;

    const CODE_ATTEMPTS = 5;

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

        // Past their last day: closed now, as the website's sign-in does.
        $separation = app(\App\Services\Employment\Separation::class);

        if ($separation->hasLeft((int) $user->id)) {
            $separation->closeAccount((int) $user->id);
            $user->tokens()->delete();

            return $this->fail('This account cannot use the app. Please contact HR.', 403);
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

    /**
     * E-mail a reset code. The answer is the same whether or not the e-mail
     * belongs to anyone, so it cannot be used to find out who works here.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $email = Str::lower(trim($request->email));
        $key = 'mobile-forgot:' . $email . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return $this->fail('Too many requests. Try again in ' . RateLimiter::availableIn($key) . ' seconds.', 429);
        }

        RateLimiter::hit($key, 600);
        $user = User::withoutGlobalScopes()->where('email', $email)->first();

        if ($user && MobileApiEmployee::mayUseApp($user)) {
            $code = (string) random_int(100000, 999999);
            $expires = now()->addMinutes(self::CODE_MINUTES);
            Cache::put($this->codeKey($email), ['hash' => Hash::make($code), 'tries' => 0, 'expires' => $expires->timestamp], $expires);
            $user->notify(new MobilePasswordResetCode($code, self::CODE_MINUTES));
        }

        return $this->ok(['expires_in_minutes' => self::CODE_MINUTES],
            'If this e-mail belongs to an employee, a 6-digit code has been sent to it.');
    }

    /** Check a code before asking for the new password (optional: reset checks it again). */
    public function verifyResetCode(Request $request)
    {
        $request->validate(['email' => 'required|email', 'code' => 'required|digits:6']);

        if ($refusal = $this->checkCode(Str::lower(trim($request->email)), $request->code)) {
            return $this->fail($refusal, 422, ['code' => [$refusal]]);
        }

        return $this->ok(null, 'The code is correct.');
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|digits:6',
            'password' => ['required', 'string', 'max:50', new Password, 'confirmed'],
        ]);

        $email = Str::lower(trim($request->email));

        if ($refusal = $this->checkCode($email, $request->code)) {
            return $this->fail($refusal, 422, ['code' => [$refusal]]);
        }

        $user = User::withoutGlobalScopes()->where('email', $email)->first();

        if (!$user || !MobileApiEmployee::mayUseApp($user)) {
            Cache::forget($this->codeKey($email));

            return $this->fail('This account cannot use the app. Please contact HR.', 403);
        }

        $user->forceFill(['password' => Hash::make($request->password)])->save();
        $user->tokens()->delete();
        Cache::forget($this->codeKey($email));

        return $this->ok(null, 'Your password has been changed. Log in with the new password.');
    }

    /** A new password for the signed-in employee; their other devices are signed out. */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'max:50', new Password, 'confirmed', 'different:current_password'],
        ]);

        $user = $this->me();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->fail('The current password is not correct.', 422, ['current_password' => ['The current password is not correct.']]);
        }

        $user->forceFill(['password' => Hash::make($request->password)])->save();
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();

        return $this->ok(null, 'Your password has been changed.');
    }

    private function codeKey(string $email): string
    {
        return 'mobile-reset-code:' . sha1($email);
    }

    /** Why the code is not accepted, or null; each wrong try counts. */
    private function checkCode(string $email, string $code): ?string
    {
        $saved = Cache::get($this->codeKey($email));

        if (!$saved || $saved['tries'] >= self::CODE_ATTEMPTS) {
            return 'The code has expired. Ask for a new one.';
        }

        if (!Hash::check($code, $saved['hash'])) {
            $saved['tries']++;
            Cache::put($this->codeKey($email), $saved, \Carbon\Carbon::createFromTimestamp($saved['expires']));

            return 'The code is not correct.';
        }

        return null;
    }
}
