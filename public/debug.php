<?php
// TEMPORARY DEBUG FILE v9 - DELETE AFTER FIXING
echo '<pre>';
echo "=== DEBUG v9 - Clear Caches ===\n\n";

foreach (glob(__DIR__ . '/../storage/framework/views/*.php') as $f) { unlink($f); }
foreach (glob(__DIR__ . '/../storage/framework/cache/data/*') as $f) { if (is_file($f)) unlink($f); }
foreach (['packages.php', 'services.php', 'config.php'] as $f) {
    $p = __DIR__ . '/../bootstrap/cache/' . $f;
    if (file_exists($p)) { unlink($p); echo "Deleted: $f\n"; }
}

echo "Caches cleared.\n";
echo "Now visit: http://arowana.uk/hrm/public/login\n";
echo "Then DELETE this debug.php file.\n";
echo '</pre>';
