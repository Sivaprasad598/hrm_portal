<?php

// --- Single source of truth: Database credentials ---
$dbCredentials = [
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => '3306',
    'database' => 'arowana_db',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];

// --- Laravel Configuration ---
$config = [
    'default' => 'mysql',

    // mysqldump for the backup Data Clean takes first; found on its own when unset.
    'dump_binary' => env('DB_DUMP_BINARY'),
    'connections' => [
        'mysql' => array_merge($dbCredentials, [
            'url' => null,
            'unix_socket' => '',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => 'InnoDB',
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::ATTR_EMULATE_PREPARES => true
            ]) : [],
        ]),
    ],
    'migrations' => 'migrations',
];

// --- Legacy PHP Scripts: Create MySQLi connection and helper function ---
// The connection is kept for the whole request and handed to every scope that
// includes this file. It used to be created only alongside the helper below,
// so once the framework had loaded this config the legacy pages included it a
// second time, skipped the block and found no $conn at all.
if (!isset($GLOBALS['legacyMysqli']) || !($GLOBALS['legacyMysqli'] instanceof mysqli)) {
    $GLOBALS['legacyMysqli'] = new mysqli(
        $dbCredentials['host'],
        $dbCredentials['username'],
        $dbCredentials['password'],
        $dbCredentials['database'],
        $dbCredentials['port']
    );

    if ($GLOBALS['legacyMysqli']->connect_error) {
        die("Database Connection failed: " . $GLOBALS['legacyMysqli']->connect_error);
    }
}

$conn = $GLOBALS['legacyMysqli'];

if (!function_exists('safeQuery')) {
    function safeQuery($conn, $sql) {
        try {
            $result = $conn->query($sql);
            return ($result === false) ? null : $result;
        } catch (Exception $e) {
            return null;
        }
    }
}

return $config;
