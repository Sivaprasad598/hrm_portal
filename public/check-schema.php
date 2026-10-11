<?php
require_once __DIR__ . '/manual-db-helper.php';

$conn = getManualDBConnection();
$stmt = $conn->query('DESCRIBE users');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Users Table Schema:\n";
echo "-------------------\n";
foreach($columns as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
}
