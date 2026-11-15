<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param \Illuminate\Http\Request $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (!$request->expectsJson()) {
            return route('login');
        }
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param string[] ...$guards
     * @return mixed
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle($request, Closure $next, ...$guards)
    {
        $companyHashId = $request->route('hash');
        $routeName = $request->route()->getName();

        if ($routeName === 'settings.qr-login') {

            $company = Company::where('hash', $companyHashId)->first();

            $qrEnable = DB::table('attendance_settings')
                ->where('company_id', $company->id)
                ->value('qr_enable');

            if ($qrEnable == 0) {
                abort(403, __('messages.qrDisabled'));
            }
        }

        if (user()) {
            $isActive = cache()->rememberForever('user_is_active_' . user()->id, function () {
                return User::where('id', user()->id)
                    ->where('status', 'active')
                    ->exists();
            });

            if (!$isActive) {
                auth()->logout();
                session()->flush();

                return redirect()->route('login');
            }
        }

        $this->authenticate($request, $guards);

        // The user's last activity: at most once a minute, written straight to the
        // row. Through the model it fired the User observers and cost 30-100 ms on
        // every request; "online a minute ago" is all anybody reads from it.
        if (Auth::check()) {
            $user = Auth::user();
            $last = $user->last_activity ? \Carbon\Carbon::parse($user->last_activity) : null;

            if (!$last || $last->lt(now()->subMinute())) {
                \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->update(['last_activity' => now()]);
                $user->last_activity = now();
                $user->syncOriginalAttribute('last_activity');
            }

            // As before (the save cleared it through UserObserver::saving): user() is read
            // fresh each request, so a change made by somebody else shows at once (one query)
            session()->forget('user');
        }

        return $next($request);
    }

}
