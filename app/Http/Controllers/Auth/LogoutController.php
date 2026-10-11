<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PDO;

class LogoutController extends Controller
{
    /**
     * Log the user out of the application.
     */
    public function __invoke(Request $request)
    {
        // Clear manual auth session
        try {
            $token = $request->cookie('manual_auth_token');
            if ($token) {
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

                $tokenHash = hash('sha256', $token);
                $stmt = $pdo->prepare('DELETE FROM manual_sessions WHERE token_hash = ?');
                $stmt->execute([$tokenHash]);
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        // Clear Laravel auth
        Auth::logout();

        // Clear manual auth cookie
        \Cookie::queue(\Cookie::forget('manual_auth_token'));

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('manual-login.php?logged_out=1');
    }
}
