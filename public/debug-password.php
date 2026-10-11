<?php
require_once 'manual-db-helper.php';

$conn = getManualDBConnection();
$stmt = $conn->prepare('SELECT id, email, password FROM users WHERE email = ? LIMIT 1');
$stmt->execute(['prudhvinarayana2002@gmail.com']);
$user = $stmt->fetch();

if ($user) {
    echo "Email: " . htmlspecialchars($user['email']) . "<br>";
    echo "Password Hash: " . htmlspecialchars($user['password']) . "<br>";
    echo "Hash Length: " . strlen($user['password']) . "<br>";

    // Test verification with 123456
    $testPassword = '123456';
    $verified = password_verify($testPassword, $user['password']);
    echo "password_verify('123456', hash): " . ($verified ? 'TRUE ✅' : 'FALSE ❌') . "<br>";

    // Show first 20 chars of hash
    echo "Hash starts with: " . substr($user['password'], 0, 20) . "...<br>";
} else {
    echo "User not found";
}
