<?php
/**
 * Manual Auth Database Helper
 * Extracted database credentials - no Laravel dependencies
 */

// Database credentials (hardcoded from config/database.php to avoid Laravel overhead)
$MANUAL_DB_HOST = 'localhost';
$MANUAL_DB_PORT = 3306;
$MANUAL_DB_NAME = 'worksuite_db';
$MANUAL_DB_USER = 'root';
$MANUAL_DB_PASS = '';
$MANUAL_DB_CHARSET = 'utf8mb4';

/**
 * Get PDO database connection
 */
function getManualDBConnection() {
    global $MANUAL_DB_HOST, $MANUAL_DB_PORT, $MANUAL_DB_NAME, $MANUAL_DB_USER, $MANUAL_DB_PASS, $MANUAL_DB_CHARSET;

    try {
        $dsn = 'mysql:host=' . $MANUAL_DB_HOST . ';port=' . $MANUAL_DB_PORT . ';dbname=' . $MANUAL_DB_NAME . ';charset=' . $MANUAL_DB_CHARSET;
        $conn = new PDO($dsn, $MANUAL_DB_USER, $MANUAL_DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $conn;
    } catch (PDOException $e) {
        die('Database connection failed: ' . $e->getMessage());
    }
}

/**
 * Generate secure random token
 */
function generateManualToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Hash token for storage
 */
function hashManualToken($token) {
    return hash('sha256', $token);
}

/**
 * Create manual_sessions table if it doesn't exist
 */
function ensureManualSessionsTable() {
    try {
        $conn = getManualDBConnection();

        // Check if table exists
        $result = $conn->query("SHOW TABLES LIKE 'manual_sessions'");
        if ($result->rowCount() > 0) {
            return true; // Table already exists
        }

        // Create table
        $conn->exec('
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
        ');
        return true;
    } catch (PDOException $e) {
        // If it fails, just log it - might be a permissions issue
        error_log('Manual sessions table error: ' . $e->getMessage());
        return false;
    }
}

// Auto-create table on first load
@ensureManualSessionsTable();
