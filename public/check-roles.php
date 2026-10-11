<?php
require_once __DIR__ . '/manual-db-helper.php';

$conn = getManualDBConnection();

// Get a sample user with their roles
$user = $conn->query("SELECT u.id, u.name, u.email FROM users u LIMIT 1")->fetch();
if ($user) {
    echo "User: " . $user['name'] . " (ID: " . $user['id'] . ")\n\n";

    // Get user roles
    $stmt = $conn->prepare("
        SELECT r.id, r.name, r.display_name
        FROM roles r
        JOIN role_user ru ON r.id = ru.role_id
        WHERE ru.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "User Roles:\n";
    if (empty($roles)) {
        echo "No roles assigned\n";
    } else {
        foreach($roles as $role) {
            echo "- " . $role['display_name'] . " (name: " . $role['name'] . ")\n";
        }
    }

    // List all available roles
    echo "\n\nAll Available Roles:\n";
    $allRoles = $conn->query("SELECT id, name, display_name FROM roles LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    foreach($allRoles as $role) {
        echo "- " . $role['display_name'] . " (name: " . $role['name'] . ", id: " . $role['id'] . ")\n";
    }
}
