<?php
/**
 * Manual Auth Check - Include this on protected pages
 * Verifies token and loads current user
 * Returns: $currentUser array or null if not authenticated
 */

require_once __DIR__ . '/manual-db-helper.php';

$currentUser = null;
$authError = '';

try {
    $token = $_COOKIE['manual_auth_token'] ?? null;

    if (!$token) {
        $authError = 'Not authenticated';
    } else {
        $tokenHash = hashManualToken($token);
        $conn = getManualDBConnection();

        // Verify token exists, hasn't expired, and matches IP/User-Agent
        $stmt = $conn->prepare('
            SELECT ms.id, ms.user_id, ms.expires_at, ms.ip_address, ms.user_agent,
                   u.id, u.name, u.email, u.status
            FROM manual_sessions ms
            JOIN users u ON ms.user_id = u.id
            WHERE ms.token_hash = ?
            AND ms.expires_at > NOW()
        ');
        $stmt->execute([$tokenHash]);
        $session = $stmt->fetch();

        if (!$session) {
            $authError = 'Session expired or invalid';
            setcookie('manual_auth_token', '', time() - 3600, '/');
        } else {
            // Optional: Verify IP hasn't changed dramatically (or skip for dynamic IPs)
            $currentIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            // Uncomment to enforce IP consistency:
            // if ($session['ip_address'] !== $currentIp) {
            //     $authError = 'IP address mismatch';
            //     setcookie('manual_auth_token', '', time() - 3600, '/');
            // }

            // If user is inactive, deny access
            if ($session['status'] !== 'active') {
                $authError = 'User account is inactive';
                setcookie('manual_auth_token', '', time() - 3600, '/');
            } else {
                // Authentication successful - load user data
                $currentUser = [
                    'id' => $session['id'],
                    'user_id' => $session['user_id'],
                    'name' => $session['name'],
                    'email' => $session['email'],
                    'status' => $session['status'],
                ];
            }
        }
    }
} catch (PDOException $e) {
    $authError = 'Auth error: ' . $e->getMessage();
}

// If not authenticated and we're on a protected page, redirect to login
function requireAuth() {
    global $currentUser, $authError;
    if (!$currentUser) {
        header('Location: manual-login.php?error=' . urlencode($authError));
        exit;
    }
}

// Helper to get current user or null
function auth() {
    global $currentUser;
    return $currentUser;
}
