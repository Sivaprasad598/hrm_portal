<?php
require_once 'manual-db-helper.php';

$newPassword = '123456';
$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);

$conn = getManualDBConnection();
$stmt = $conn->prepare('UPDATE users SET password = ? WHERE email = ?');
$result = $stmt->execute([$hashedPassword, 'prudhvinarayana2002@gmail.com']);

if ($result) {
    echo "<h3>✅ Password Reset Successful!</h3>";
    echo "<p><strong>Email:</strong> prudhvinarayana2002@gmail.com</p>";
    echo "<p><strong>New Password:</strong> <code>123456</code></p>";
    echo "<p><strong>New Hash:</strong> " . htmlspecialchars($hashedPassword) . "</p>";
    echo "<hr>";
    echo "<p><a href='manual-login.php'>Go to Login</a></p>";
} else {
    echo "Error updating password";
}
