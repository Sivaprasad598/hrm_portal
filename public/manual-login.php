<?php
/**
 * Manual Login Handler - NO LARAVEL, NO .env
 * Standalone PHP login using token-based sessions
 */

require_once __DIR__ . '/manual-db-helper.php';

// Ensure session uses secure cookies
$cookieOptions = [
    'expires' => time() + 86400, // 24 hours
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // HTTPS only if available
    'httponly' => true,
    'samesite' => 'Lax',
];

$error = '';
$success = '';

// Handle POST login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Email and password are required.';
    } else {
        try {
            $conn = getManualDBConnection();

            // Query user by email
            $stmt = $conn->prepare('SELECT id, name, email, password, status FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Invalid email or password.';
            } elseif ($user['status'] !== 'active') {
                $error = 'Your account is inactive.';
            } else {
                // Verify password
                if (!password_verify($password, $user['password'])) {
                    $error = 'Invalid email or password.';
                } else {
                    // Ensure table exists before inserting
                    ensureManualSessionsTable();

                    // Create manual session token
                    $token = generateManualToken();
                    $tokenHash = hashManualToken($token);
                    $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24 hours
                    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

                    // Insert session into DB
                    try {
                        $insertStmt = $conn->prepare('
                            INSERT INTO manual_sessions (user_id, token_hash, expires_at, ip_address, user_agent, created_at)
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ');
                        $insertStmt->execute([$user['id'], $tokenHash, $expiresAt, $ipAddress, $userAgent]);

                        // Set secure cookie with raw token
                        setcookie('manual_auth_token', $token, $cookieOptions);

                        $success = 'Login successful! Redirecting...';
                        header('Refresh: 2; url=account/dashboard');
                    } catch (PDOException $tableError) {
                        $error = 'Session storage error. Please try again. Error: ' . $tableError->getMessage();
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manual Login (No Laravel, No .env)</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 400px; margin: 50px auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="email"], input[type="password"] { width: 100%; padding: 8px; box-sizing: border-box; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        button:hover { background: #0056b3; }
        .error { color: red; margin-bottom: 15px; }
        .success { color: green; margin-bottom: 15px; }
    </style>
</head>
<body>
    <h1>Manual Login</h1>
    <p><strong>No Laravel. No .env. Pure PHP.</strong></p>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit">Login</button>
    </form>

    <p><small>Test credentials: Use any existing user from the database</small></p>
</body>
</html>
