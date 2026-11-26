<?php
require 'config/db.php';

$migrations = [
    '0009_password_reset_rate_limits' => 'database/migrations/0009_password_reset_rate_limits.sql',
    '0010_tenant_schema_reconciliation' => 'database/migrations/0010_tenant_schema_reconciliation.sql',
    '0011_disable_public_default_super_admin' => 'database/migrations/0011_disable_public_default_super_admin.sql',
];

foreach ($migrations as $version => $file) {
    $sql = file_get_contents($file);
    if ($sql === false) {
        echo "$version: Could not read file\n";
        continue;
    }
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
    
    // Split and execute each statement
    $statements = explode(';', $sql);
    $ok = true;
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt !== '') {
            try {
                $pdo->exec($stmt);
            } catch (PDOException $e) {
                echo "$version: FAILED - " . $e->getMessage() . "\n";
                $ok = false;
            }
        }
    }
    if ($ok) {
        echo "$version: OK\n";
    }
}