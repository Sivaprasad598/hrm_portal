<?php
/**
 * Manual Logout Handler
 * Deletes the session token and clears the cookie
 */

require_once __DIR__ . '/manual-db-helper.php';

try {
    $token = $_COOKIE['manual_auth_token'] ?? null;

    if ($token) {
        $tokenHash = hashManualToken($token);
        $conn = getManualDBConnection();

        // Delete session from database
        $stmt = $conn->prepare('DELETE FROM manual_sessions WHERE token_hash = ?');
        $stmt->execute([$tokenHash]);
    }

    // Clear cookie
    setcookie('manual_auth_token', '', time() - 3600, '/', '', false, true);

} catch (PDOException $e) {
    // Log error but continue with logout
    error_log('Logout error: ' . $e->getMessage());
}

// Redirect to login page
header('Location: manual-login.php?logged_out=1');
exit;
