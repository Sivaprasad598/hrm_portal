<?php
require_once 'manual-db-helper.php';

echo "<h2>Resetting Manual Sessions Table</h2>";

try {
    $conn = getManualDBConnection();
    echo "<p>✅ Database connection successful</p>";

    // Drop the table if it exists
    $conn->exec('DROP TABLE IF EXISTS manual_sessions');
    echo "<p>✅ Dropped existing table (if any)</p>";

    // Now create the table fresh
    $sql = '
        CREATE TABLE manual_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token_hash (token_hash),
            INDEX idx_expires_at (expires_at),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ';

    $conn->exec($sql);
    echo "<p>✅ Table created successfully</p>";

    // Verify
    $result = $conn->query("SHOW TABLES LIKE 'manual_sessions'");
    if ($result->rowCount() > 0) {
        echo "<p>✅ Verification: Table confirmed to exist</p>";

        // Show table structure
        $info = $conn->query("DESCRIBE manual_sessions")->fetchAll();
        echo "<p><strong>Table Structure:</strong></p>";
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        foreach ($info as $col) {
            echo "<tr><td>" . $col['Field'] . "</td><td>" . $col['Type'] . "</td><td>" . $col['Null'] . "</td><td>" . $col['Key'] . "</td></tr>";
        }
        echo "</table>";

        echo "<hr>";
        echo "<p><a href='manual-login.php'><strong>✅ Ready! Go to Login</strong></a></p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
