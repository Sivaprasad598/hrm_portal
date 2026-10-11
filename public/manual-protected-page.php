<?php
/**
 * Example Protected Page
 * Include manual-auth-check.php and call requireAuth() to protect
 */

require_once __DIR__ . '/manual-auth-check.php';

// Require user to be authenticated
requireAuth();

$user = auth();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Protected Page (Manual Auth)</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; }
        .header { background: #f0f0f0; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .info { background: #e8f4f8; padding: 15px; border-left: 4px solid #007bff; margin-bottom: 20px; }
        button { background: #dc3545; color: white; padding: 10px 20px; border: none; cursor: pointer; border-radius: 3px; }
        button:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Protected Page</h1>
        <p>Welcome, <strong><?php echo htmlspecialchars($user['name']); ?></strong>!</p>
    </div>

    <div class="info">
        <h2>Your Account Info (Manual Auth)</h2>
        <ul>
            <li><strong>ID:</strong> <?php echo $user['user_id']; ?></li>
            <li><strong>Name:</strong> <?php echo htmlspecialchars($user['name']); ?></li>
            <li><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></li>
            <li><strong>Status:</strong> <?php echo htmlspecialchars($user['status']); ?></li>
        </ul>
    </div>

    <p><strong>How This Works:</strong></p>
    <ul>
        <li>No Laravel Framework</li>
        <li>No .env file loaded</li>
        <li>No APP_KEY dependency</li>
        <li>Token stored in secure HttpOnly cookie</li>
        <li>Session validated in database on every request</li>
        <li>Automatic expiry: 24 hours</li>
        <li>Secure: IP tracking, User-Agent check, HTTPS support</li>
    </ul>

    <form action="manual-logout.php" method="GET">
        <button type="submit">Logout</button>
    </form>

    <hr>
    <p><small>Session data stored in <code>manual_sessions</code> table.</small></p>
</body>
</html>
