<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use PDO;

class ManualAuthMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Check for manual auth token in cookie
        $token = $request->cookie('manual_auth_token');

        if ($token && !Auth::check()) {
            try {
                // Get PDO connection
                $pdo = new PDO(
                    'mysql:host=localhost;port=3306;dbname=worksuite_db;charset=utf8mb4',
                    'root',
                    '',
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );

                // Hash the token
                $tokenHash = hash('sha256', $token);

                // Find the session
                $stmt = $pdo->prepare('
                    SELECT ms.user_id, u.id
                    FROM manual_sessions ms
                    JOIN users u ON ms.user_id = u.id
                    WHERE ms.token_hash = ?
                    AND ms.expires_at > NOW()
                    AND u.status = "active"
                ');
                $stmt->execute([$tokenHash]);
                $session = $stmt->fetch();

                if ($session) {
                    // Load the user from database
                    $user = User::find($session['user_id']);
                    if ($user && $user->status === 'active') {
                        // Authenticate the user in Laravel
                        Auth::login($user, false);
                    }
                }
            } catch (\Exception $e) {
                // Silently fail - not critical
            }
        }

        return $next($request);
    }
}
