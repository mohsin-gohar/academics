<?php
require 'config/db.php';
$r = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'password_reset_attempts'");
echo 'password_reset_attempts table exists: ' . $r->fetchColumn() . PHP_EOL;