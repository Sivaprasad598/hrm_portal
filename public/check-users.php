<?php
require_once __DIR__ . '/manual-db-helper.php';

try {
    $conn = getManualDBConnection();
    $stmt = $conn->query('SELECT id, name, email, status FROM users LIMIT 5');
    $users = $stmt->fetchAll();

    echo "Available users in database:\n";
    echo "================================\n";
    foreach ($users as $user) {
        echo "ID: " . $user['id'] . "\n";
        echo "Name: " . $user['name'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Status: " . $user['status'] . "\n";
        echo "---\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
