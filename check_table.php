<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require 'config/db.php';
$r = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'password_reset_attempts'");
echo 'password_reset_attempts table exists: ' . $r->fetchColumn() . PHP_EOL;